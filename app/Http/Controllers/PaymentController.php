<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentVerificationException;
use App\Models\Application;
use App\Models\DiscountCoupon;
use App\Models\Payment;
use App\Models\TrademarkPricing;
use App\Services\TrademarkWorkflowService;
use App\Services\RazorpayPaymentVerifier;
use App\Support\TrademarkWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    /**
     * Show payment page
     */
    public function showPayment($applicationId)
    {
        $application = Application::findOrFail($applicationId);

        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        $originalTotalAmount = TrademarkPricing::amountForApplicantType($application->entity_type);
        $autoApplyCoupon = DiscountCoupon::autoApplyForPayment('trademark_filing', Auth::id());
        $totalAmount = $autoApplyCoupon
            ? $autoApplyCoupon->discountedAmountFor($originalTotalAmount)
            : $originalTotalAmount;
        $advanceAmount = round($totalAmount * 0.50, 2);
        $completedPayments = $application->payments()
            ->whereIn('status', ['completed', 'approved'])
            ->get();
        $servicePayments = $completedPayments->filter(
            fn (Payment $payment) => in_array($this->paymentKind($payment), ['advance', 'final', 'full'], true)
        );
        $hasFullPayment = $servicePayments->contains(
            fn (Payment $payment) => $this->paymentKind($payment) === 'full'
        );
        $hasAdvancePayment = $servicePayments->contains(
            fn (Payment $payment) => $this->paymentKind($payment) === 'advance'
        );
        $paidServiceAmount = (float) $servicePayments->sum('amount');
        $finalAmount = max($totalAmount - $paidServiceAmount, 0);

        if ($hasFullPayment || $finalAmount <= 0) {
            return redirect()->route('trademark.status', $application->id)
                ->with('info', 'Your professional fee is already paid.');
        }

        if (! $hasAdvancePayment) {
            // Payment history is authoritative. This also safely repairs legacy
            // applications that were incorrectly labelled as final-payment due.
            $paymentType = 'advance';
        } elseif ($application->current_status === TrademarkWorkflow::PAYMENT_PENDING_FINAL) {
            $paymentType = 'final';
        } else {
            return redirect()->route('trademark.status', $application->id)
                ->with('info', 'Your 50% advance has been received. The final balance becomes due after you approve the filing draft.');
        }

        return view('payments.razorpay-form', [
            'application' => $application,
            'totalAmount' => $totalAmount,
            'originalTotalAmount' => $originalTotalAmount,
            'advanceAmount' => $paymentType === 'advance' ? $advanceAmount : $finalAmount,
            'paymentType' => $paymentType,
            'razorpayKeyId' => config('razorpay.key_id'),
            'paymentCoupons' => DiscountCoupon::availableForPayment('trademark_filing', Auth::id()),
            'autoApplyCoupon' => $autoApplyCoupon,
            'paidServiceAmount' => $paidServiceAmount,
        ]);
    }

    /**
     * Create Razorpay order
     */
    public function createOrder(Request $request, $applicationId)
    {
        $application = Application::findOrFail($applicationId);

        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        // Get min and max from config
        $minAmount = config('razorpay.custom_payments.min_amount', 1);
        $maxAmount = config('razorpay.custom_payments.max_amount', 100000);

        $validated = $request->validate([
            'amount' => "required|numeric|min:$minAmount|max:$maxAmount",
            'payment_type' => 'required|in:advance,final',
            'discount_coupon_id' => ['nullable', 'integer'],
        ]);

        $coupon = null;
        if (! empty($validated['discount_coupon_id'])) {
            $coupon = DiscountCoupon::availableForPayment('trademark_filing', Auth::id())
                ->firstWhere('id', (int) $validated['discount_coupon_id']);

            if (! $coupon) {
                throw ValidationException::withMessages([
                    'discount_coupon_id' => 'This discount coupon is no longer available.',
                ]);
            }
        }

        $serviceTotalAmount = TrademarkPricing::amountForApplicantType($application->entity_type);
        $discountedTotalAmount = $coupon
            ? $coupon->discountedAmountFor($serviceTotalAmount)
            : $serviceTotalAmount;
        $completedPayments = $application->payments()
            ->whereIn('status', ['completed', 'approved'])
            ->get();
        $servicePayments = $completedPayments->filter(
            fn (Payment $payment) => in_array($this->paymentKind($payment), ['advance', 'final', 'full'], true)
        );
        $hasAdvancePayment = $servicePayments->contains(
            fn (Payment $payment) => $this->paymentKind($payment) === 'advance'
        );
        $hasFinalOrFullPayment = $servicePayments->contains(
            fn (Payment $payment) => in_array($this->paymentKind($payment), ['final', 'full'], true)
        );
        $paidServiceAmount = (float) $servicePayments->sum('amount');

        if ($validated['payment_type'] === 'advance' && ($hasAdvancePayment || $hasFinalOrFullPayment)) {
            throw ValidationException::withMessages([
                'payment_type' => 'The initial professional-fee payment has already been completed.',
            ]);
        }

        if ($validated['payment_type'] === 'final'
            && (! $hasAdvancePayment
                || $hasFinalOrFullPayment
                || $application->current_status !== TrademarkWorkflow::PAYMENT_PENDING_FINAL)) {
            throw ValidationException::withMessages([
                'payment_type' => 'The final 50% balance is available only after the advance payment and client approval.',
            ]);
        }

        $verifiedAmount = match ($validated['payment_type']) {
            'final' => max($discountedTotalAmount - $paidServiceAmount, 0),
            default => round($discountedTotalAmount * 0.5, 2),
        };
        $paymentAmountBeforeDiscount = match ($validated['payment_type']) {
            'final' => $serviceTotalAmount - round($serviceTotalAmount * 0.5, 2),
            default => round($serviceTotalAmount * 0.5, 2),
        };
        $paymentDiscountAmount = round(max($paymentAmountBeforeDiscount - $verifiedAmount, 0), 2);

        if ($verifiedAmount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'There is no remaining balance to pay.',
            ]);
        }

        try {
            // Initialize Razorpay API using cURL (to avoid dependency)
            $razorpayKeyId = config('razorpay.key_id');
            $razorpaySecret = config('razorpay.key_secret');

            // Razorpay expects the amount in the currency's smallest unit (pence for GBP).
            $amountInSmallestUnit = (int) round($verifiedAmount * 100);
            $currency = config('razorpay.currency', 'GBP');

            // Create Razorpay order via REST API
            $ch = curl_init('https://api.razorpay.com/v1/orders');
            curl_setopt($ch, CURLOPT_USERPWD, "$razorpayKeyId:$razorpaySecret");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'amount' => $amountInSmallestUnit,
                'currency' => $currency,
                'receipt' => 'order_' . $application->id . '_' . time(),
                'description' => 'UK Trade Mark Application - ' . $application->brand_name,
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new \Exception('Failed to create Razorpay order');
            }

            $order = json_decode($response, true);

            // Save payment record with pending status
            $normalizedPaymentType = match ($validated['payment_type']) {
                'final' => 'final',
                default => 'advance',
            };
            $paymentData = [
                'application_id' => $applicationId,
                'user_id' => Auth::id(),
                'amount' => $verifiedAmount,
                'total_amount' => $serviceTotalAmount,
                'percentage' => '50%',
                'payment_method' => 'razorpay',
                'status' => 'pending',
                'reference_number' => $order['id'],
            ];

            if ($this->hasPaymentsColumn('payment_type')) {
                $paymentData['payment_type'] = $normalizedPaymentType;
            }

            if ($this->hasPaymentsColumn('discount_coupon_id')) {
                $paymentData['discount_coupon_id'] = $coupon?->id;
            }
            if ($this->hasPaymentsColumn('coupon_code')) {
                $paymentData['coupon_code'] = $coupon?->code;
            }
            if ($this->hasPaymentsColumn('discount_amount')) {
                $paymentData['discount_amount'] = $paymentDiscountAmount;
            }
            if ($this->hasPaymentsColumn('discounted_total_amount')) {
                $paymentData['discounted_total_amount'] = $discountedTotalAmount;
            }

            $payment = Payment::create($paymentData);

            return response()->json([
                'status' => 'success',
                'order_id' => $order['id'],
                'amount' => $amountInSmallestUnit,
                'currency' => $currency,
                'key' => $razorpayKeyId,
                'user_email' => Auth::user()->email,
                'user_phone' => Auth::user()->phone ?? '',
                'user_name' => Auth::user()->name,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create payment order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify Razorpay payment signature
     */
    public function verifySignature(
        Request $request,
        $applicationId,
        TrademarkWorkflowService $workflow,
        RazorpayPaymentVerifier $verifier,
    )
    {
        $application = Application::findOrFail($applicationId);

        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        try {
            $validated = $request->validate([
                'razorpay_payment_id' => 'required|string',
                'razorpay_order_id' => 'required|string',
                'razorpay_signature' => 'required|string',
            ]);

            $orderId = $validated['razorpay_order_id'];
            $paymentId = $validated['razorpay_payment_id'];

            $payment = Payment::where([
                'application_id' => $applicationId,
                'user_id' => Auth::id(),
                'reference_number' => $orderId,
            ])->firstOrFail();

            if (in_array($payment->status, ['completed', 'approved'], true)) {
                if (! hash_equals((string) $payment->transaction_id, $paymentId)) {
                    throw new PaymentVerificationException('This order is already linked to a different payment.');
                }
            } else {
                if ($payment->status !== 'pending') {
                    throw new PaymentVerificationException('This payment order is no longer payable.');
                }

                $verifier->verifyCaptured(
                    $paymentId,
                    $orderId,
                    $validated['razorpay_signature'],
                    (string) $payment->reference_number,
                    (int) round((float) $payment->amount * 100),
                    (string) config('razorpay.currency', 'GBP'),
                );

                $payment = DB::transaction(function () use ($payment, $paymentId, $workflow, $application) {
                    $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

                    if (! in_array($lockedPayment->status, ['completed', 'approved'], true)) {
                        $lockedPayment->update([
                            'status' => 'completed',
                            'paid_at' => now(),
                            'transaction_id' => $paymentId,
                        ]);

                        if ($this->paymentType($lockedPayment) === 'final') {
                            $workflow->markFinalPaymentComplete($application->fresh());
                        } else {
                            $workflow->markAdvancePaymentComplete($application->fresh());
                        }
                    }

                    return $lockedPayment->fresh();
                });
            }

            $paymentType = $this->paymentType($payment);

            return response()->json([
                'status' => 'success',
                'message' => $paymentType === 'final'
                    ? 'Final payment verified successfully.'
                    : 'Payment verified successfully. Your application has been sent for admin review.',
                'payment_id' => $payment->id,
                'redirect_url' => route('trademark.status', $application->id),
            ]);
        } catch (PaymentVerificationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment verification failed: '.$e->getMessage(),
            ], $e->httpStatus());
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment verification failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Verify payment status (for status checking)
     */
    public function checkPaymentStatus($applicationId)
    {
        $application = Application::findOrFail($applicationId);

        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        $payment = $application->payments()->where('status', 'completed')->latest('id')->first();

        return response()->json([
            'paid' => $payment ? true : false,
            'payment' => $payment,
        ]);
    }

    /**
     * Process payment (Legacy method - redirects to showPayment)
     */
    public function processPayment($applicationId)
    {
        $application = Application::findOrFail($applicationId);

        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        return redirect()->route('payment.show', $application->id);
    }

    /**
     * Show payment history
     */
    public function paymentHistory()
    {
        $payments = Auth::user()->payments()->with('application')->latest()->get();
        return view('payments.history', ['payments' => $payments]);
    }

    public function viewInvoice(Payment $payment)
    {
        if ($payment->user_id !== Auth::id()) {
            abort(403);
        }

        return $this->renderInvoice($payment);
    }

    public function viewAdminInvoice(Payment $payment)
    {
        if (! Auth::guard('admin')->check()) {
            abort(403);
        }

        return $this->renderInvoice($payment);
    }

    private function renderInvoice(Payment $payment)
    {
        if (!in_array(strtolower((string) $payment->status), ['completed', 'approved'], true)) {
            abort(404);
        }

        $payment->loadMissing(['application', 'user']);
        $application = $payment->application;

        $pdf = \PDF::loadView('emails.attachments.invoice', [
            'application' => $application,
            'user' => $payment->user,
            'payment' => $payment,
            'invoiceNumber' => $this->invoiceNumber($payment),
            'paymentLabel' => $this->paymentLabel($payment),
            'issuedAt' => $payment->paid_at ?? $payment->created_at,
            'firmName' => config('app.name', 'Legal Bruz'),
            'firmEmail' => config('mail.from.address'),
        ])->setPaper('a4');

        return $pdf->stream('invoice-' . $application->id . '-' . $payment->id . '.pdf');
    }

    private function hasPaymentsColumn(string $column): bool
    {
        return Schema::hasColumn('payments', $column);
    }

    private function paymentType(Payment $payment): string
    {
        if ($this->hasPaymentsColumn('payment_type') && filled($payment->payment_type)) {
            return $payment->payment_type;
        }

        return (string) $payment->percentage === '100%' ? 'final' : 'advance';
    }

    private function paymentLabel(Payment $payment): string
    {
        return match ($this->paymentKind($payment)) {
            'full' => 'Full Payment',
            'final' => 'Final Payment',
            default => 'Advance Payment (50%)',
        };
    }

    private function paymentKind(Payment $payment): string
    {
        $paymentType = strtolower((string) ($payment->payment_type ?? ''));

        if (in_array($paymentType, ['advance', 'final', 'full'], true)) {
            return $paymentType;
        }

        if ((float) $payment->amount >= (float) $payment->total_amount && (float) $payment->total_amount > 0) {
            return 'full';
        }

        return (string) $payment->percentage === '100%' ? 'final' : 'advance';
    }

    private function invoiceNumber(Payment $payment): string
    {
        $issuedAt = $payment->paid_at ?? $payment->created_at ?? now();

        return 'INV-' . $issuedAt->format('Y') . '-' . $payment->application_id . '-' . $payment->id;
    }
}
