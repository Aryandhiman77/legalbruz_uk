<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentVerificationException;
use App\Models\ConsultationBooking;
use App\Models\ContactMessage;
use App\Models\PaymentTransaction;
use App\Models\TrademarkPricing;
use App\Services\RevolutMerchantService;
use App\Services\PaymentInvoiceEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConsultationBookingController extends Controller
{
    public function create(): View
    {
        return view('pages.book-call', [
            'consultationFee' => TrademarkPricing::amountFor(TrademarkPricing::CONSULTATION),
            'topics' => ConsultationBooking::topicOptions(),
            'timeSlots' => ConsultationBooking::timeSlotOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', 'max:30'],
            'business_name' => ['nullable', 'string', 'max:180'],
            'service_topic' => ['required', Rule::in(array_keys(ConsultationBooking::topicOptions()))],
            'preferred_date' => ['required', 'date', 'after_or_equal:today'],
            'preferred_time' => ['required', Rule::in(array_keys(ConsultationBooking::timeSlotOptions()))],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['nullable', 'max:0'],
        ]);

        $amount = TrademarkPricing::amountFor(TrademarkPricing::CONSULTATION);
        $topic = ConsultationBooking::topicOptions()[$validated['service_topic']];
        $time = ConsultationBooking::timeSlotOptions()[$validated['preferred_time']];
        $contactMessage = ContactMessage::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'business_name' => $validated['business_name'] ?? null,
            'service_interested' => 'Consultation Call',
            'subject' => 'Book a Call — Payment pending',
            'message' => $this->contactMessageBody($topic, $validated['preferred_date'], $time, $amount, 'Pending', $validated['message']),
            'status' => 'new',
        ]);

        $booking = ConsultationBooking::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => Auth::guard('web')->id(),
            'contact_message_id' => $contactMessage->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'business_name' => $validated['business_name'] ?? null,
            'service_topic' => $validated['service_topic'],
            'preferred_date' => $validated['preferred_date'],
            'preferred_time' => $validated['preferred_time'],
            'message' => $validated['message'],
            'amount' => $amount,
            'currency' => 'GBP',
            'payment_status' => 'pending',
        ]);

        return redirect()->route('book-call.payment', $booking);
    }

    public function payment(ConsultationBooking $booking): View
    {
        return view('pages.book-call-payment', [
            'booking' => $booking,
            'topic' => $booking->topic_label,
            'timeSlot' => $booking->time_slot_label,
        ]);
    }

    public function createOrder(ConsultationBooking $booking, RevolutMerchantService $revolut): JsonResponse
    {
        if ($booking->payment_status === 'paid') {
            return response()->json(['status' => 'error', 'message' => 'This consultation has already been paid.'], 422);
        }

        try {
            $transaction = $revolut->createHostedOrder(
                $booking,
                'consultation',
                (float) $booking->amount,
                $booking->currency,
                'Legal Bruz consultation call',
                route('book-call.payment.confirm', $booking),
                $booking->user_id,
                $booking->email,
                ['booking_id' => $booking->public_id],
            );
        } catch (PaymentVerificationException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], $exception->httpStatus());
        }

        $booking->update(['payment_reference' => $transaction->provider_order_id]);

        return response()->json([
            'status' => 'success',
            'order_id' => $transaction->provider_order_id,
            'checkout_url' => $transaction->checkout_url,
            'amount' => (int) round((float) $booking->amount * 100),
            'currency' => $booking->currency,
        ]);
    }

    public function confirm(
        ConsultationBooking $booking,
        RevolutMerchantService $revolut,
        PaymentInvoiceEmailService $invoiceEmails,
    ): RedirectResponse
    {
        if ($booking->payment_status === 'paid') {
            return redirect()->route('book-call.success', $booking);
        }

        $transaction = PaymentTransaction::query()->whereMorphedTo('payable', $booking)->latest('id')->first();
        if (! $transaction) {
            return redirect()->route('book-call.payment', $booking)->with('error', 'No payment order was found.');
        }
        try {
            $transaction = $revolut->verifyCompleted($booking, $transaction->provider_order_id);
        } catch (PaymentVerificationException $exception) {
            return redirect()->route('book-call.payment', $booking)->with('error', $exception->getMessage());
        }

        $booking->update([
            'payment_status' => 'paid',
            'payment_reference' => $transaction->provider_order_id,
            'transaction_id' => $transaction->provider_payment_id,
            'paid_at' => $transaction->paid_at ?? now(),
        ]);

        $booking->contactMessage?->update([
            'subject' => 'Book a Call — Paid',
            'message' => $this->contactMessageBody(
                $booking->topic_label,
                $booking->preferred_date->toDateString(),
                $booking->time_slot_label,
                (float) $booking->amount,
                'Paid — '.$transaction->provider_payment_id,
                $booking->message,
            ),
        ]);

        $invoiceEmails->sendToClientAndAdmins($transaction);

        return redirect()->route('book-call.success', $booking)->with('success', 'Payment confirmed successfully.');
    }

    public function success(ConsultationBooking $booking): View|RedirectResponse
    {
        if ($booking->payment_status !== 'paid') {
            return redirect()->route('book-call.payment', $booking);
        }

        return view('pages.book-call-success', compact('booking'));
    }

    private function contactMessageBody(string $topic, string $date, string $time, float $amount, string $paymentStatus, string $message): string
    {
        return "Consultation topic: {$topic}\nPreferred date: {$date}\nPreferred time: {$time}\nConsultation fee: £".number_format($amount, 2)."\nPayment status: {$paymentStatus}\n\nClient note:\n{$message}";
    }
}
