<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentVerificationException;
use App\Models\Application;
use App\Models\DiscountCoupon;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\TrademarkPricing;
use App\Services\ClientPaymentHistoryService;
use App\Services\PaymentInvoiceEmailService;
use App\Services\PaymentInvoiceService;
use App\Services\RevolutMerchantService;
use App\Services\TrademarkWorkflowService;
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

        return view('payments.revolut-form', [
            'application' => $application,
            'totalAmount' => $totalAmount,
            'originalTotalAmount' => $originalTotalAmount,
            'advanceAmount' => $paymentType === 'advance' ? $advanceAmount : $finalAmount,
            'paymentType' => $paymentType,
            'paymentCoupons' => DiscountCoupon::availableForPayment('trademark_filing', Auth::id()),
            'autoApplyCoupon' => $autoApplyCoupon,
            'paidServiceAmount' => $paidServiceAmount,
        ]);
    }

    /**
     * Create a Revolut Hosted Checkout order.
     */
    public function createOrder(Request $request, $applicationId)
    {
        $application = Application::findOrFail($applicationId);

        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        // Get min and max from config
        $minAmount = config('revolut.custom_payments.min_amount', 1);
        $maxAmount = config('revolut.custom_payments.max_amount', 100000);

        $validated = $request->validate([
            'amount' => "required|numeric|min:$minAmount|max:$maxAmount",
            'payment_type' => 'required|in:advance,final,full',
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

        if ($validated['payment_type'] === 'full' && $servicePayments->isNotEmpty()) {
            throw ValidationException::withMessages([
                'payment_type' => 'Full payment is available only before an advance or final payment has been completed.',
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
            'full' => $discountedTotalAmount,
            default => round($discountedTotalAmount * 0.5, 2),
        };
        $paymentAmountBeforeDiscount = match ($validated['payment_type']) {
            'final' => $serviceTotalAmount - round($serviceTotalAmount * 0.5, 2),
            'full' => $serviceTotalAmount,
            default => round($serviceTotalAmount * 0.5, 2),
        };
        $paymentDiscountAmount = round(max($paymentAmountBeforeDiscount - $verifiedAmount, 0), 2);

        if ($verifiedAmount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'There is no remaining balance to pay.',
            ]);
        }

        $payment = null;

        try {
            $currency = config('revolut.currency', 'GBP');
            $normalizedPaymentType = match ($validated['payment_type']) {
                'final' => 'final',
                'full' => 'full',
                default => 'advance',
            };
            $paymentData = [
                'application_id' => $applicationId,
                'user_id' => Auth::id(),
                'amount' => $verifiedAmount,
                'total_amount' => $serviceTotalAmount,
                'percentage' => $normalizedPaymentType === 'full' ? '100%' : '50%',
                'payment_method' => 'revolut',
                'status' => 'pending',
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
            $transaction = app(RevolutMerchantService::class)->createHostedOrder(
                $payment,
                'trademark_application_'.$normalizedPaymentType,
                $verifiedAmount,
                $currency,
                'UK Trade Mark Application - '.$application->brand_name,
                route('payment.confirm', ['id' => $application->id, 'payment' => $payment->id]),
                Auth::id(),
                Auth::user()->email,
                ['application_id' => $application->id, 'payment_type' => $normalizedPaymentType],
            );
            $payment->update(['reference_number' => $transaction->provider_order_id]);

            return response()->json([
                'status' => 'success',
                'order_id' => $transaction->provider_order_id,
                'checkout_url' => $transaction->checkout_url,
                'amount' => (int) round($verifiedAmount * 100),
                'currency' => $currency,
            ]);
        } catch (PaymentVerificationException $e) {
            $payment?->update(['status' => 'failed']);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->httpStatus());
        } catch (\Exception $e) {
            $payment?->update(['status' => 'failed']);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create payment order: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirm the returned Revolut order server-side before advancing workflow.
     */
    public function confirm(
        Request $request,
        $applicationId,
        TrademarkWorkflowService $workflow,
        RevolutMerchantService $revolut,
        PaymentInvoiceEmailService $invoiceEmails,
    ) {
        $application = Application::findOrFail($applicationId);

        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        try {
            $payment = Payment::where('application_id', $applicationId)
                ->where('user_id', Auth::id())
                ->findOrFail((int) $request->query('payment'));

            if (in_array($payment->status, ['completed', 'approved'], true)) {
                return redirect()->route('trademark.status', $application->id)
                    ->with('success', 'Payment is already confirmed.');
            } else {
                if ($payment->status !== 'pending') {
                    throw new PaymentVerificationException('This payment order is no longer payable.');
                }

                $transaction = $revolut->verifyCompleted($payment, (string) $payment->reference_number);

                $payment = DB::transaction(function () use ($payment, $transaction, $workflow, $application) {
                    $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

                    if (! in_array($lockedPayment->status, ['completed', 'approved'], true)) {
                        $lockedPayment->update([
                            'status' => 'completed',
                            'paid_at' => $transaction->paid_at ?? now(),
                            'transaction_id' => $transaction->provider_payment_id,
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

            $invoiceEmails->sendToClientAndAdmins($transaction);

            $paymentType = $this->paymentType($payment);

            $successMessage = match ($paymentType) {
                'final' => 'Final payment verified successfully.',
                'full' => 'Full payment verified successfully. Your application has been sent for admin review.',
                default => 'Payment verified successfully. Your application has been sent for admin review.',
            };

            return redirect()->route('trademark.status', $application->id)->with('success', $successMessage);
        } catch (PaymentVerificationException $e) {
            return redirect()->route('payment.show', $application->id)
                ->with('error', 'Payment verification failed: '.$e->getMessage());
        } catch (\Exception $e) {
            return redirect()->route('payment.show', $application->id)
                ->with('error', 'Payment verification failed: '.$e->getMessage());
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
    public function paymentHistory(ClientPaymentHistoryService $history)
    {
        $payments = $history->paginate(Auth::user());

        return view('payments.history', compact('payments'));
    }

    public function adminPaymentHistory(Request $request)
    {
        $transactions = PaymentTransaction::query()
            ->with(['user', 'payable'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->string('status')))
            ->when($request->filled('service'), fn ($query) => $query->where('service', (string) $request->string('service')))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.payments.index', compact('transactions'));
    }

    public function viewTransactionInvoice(PaymentTransaction $transaction)
    {
        abort_unless($transaction->user_id === Auth::id(), 403);

        return $this->renderTransactionInvoice($transaction);
    }

    public function viewAdminTransactionInvoice(PaymentTransaction $transaction)
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        return $this->renderTransactionInvoice($transaction);
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
        if (! in_array(strtolower((string) $payment->status), ['completed', 'approved'], true)) {
            abort(404);
        }

        return $this->invoiceResponse(
            app(PaymentInvoiceService::class)->forApplicationPayment($payment)
        );
    }

    private function renderTransactionInvoice(PaymentTransaction $transaction)
    {
        abort_unless($transaction->status === 'completed' && $transaction->paid_at, 404);

        return $this->invoiceResponse(
            app(PaymentInvoiceService::class)->forTransaction($transaction)
        );
    }

    private function invoiceResponse(\App\Support\GeneratedPaymentInvoice $invoice)
    {
        return response($invoice->contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename='.$invoice->filename,
        ]);
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

}
