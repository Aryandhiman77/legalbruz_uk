<?php

namespace App\Http\Controllers;

use App\Models\ConsultationBooking;
use App\Models\ContactMessage;
use App\Models\TrademarkPricing;
use App\Exceptions\PaymentVerificationException;
use App\Services\RazorpayPaymentVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

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
            'razorpayKeyId' => config('razorpay.key_id'),
        ]);
    }

    public function createOrder(ConsultationBooking $booking): JsonResponse
    {
        if ($booking->payment_status === 'paid') {
            return response()->json(['status' => 'error', 'message' => 'This consultation has already been paid.'], 422);
        }

        $keyId = (string) config('razorpay.key_id');
        $secret = (string) config('razorpay.key_secret');

        if ($keyId === '' || $secret === '') {
            return response()->json(['status' => 'error', 'message' => 'Online payment is temporarily unavailable. Please contact us for assistance.'], 503);
        }

        try {
            $response = Http::withBasicAuth($keyId, $secret)
                ->acceptJson()
                ->timeout((int) config('razorpay.timeout', 30))
                ->post('https://api.razorpay.com/v1/orders', [
                    'amount' => (int) round((float) $booking->amount * 100),
                    'currency' => $booking->currency,
                    'receipt' => 'call_'.$booking->id.'_'.now()->timestamp,
                    'notes' => [
                        'booking_id' => $booking->public_id,
                        'service' => 'Consultation Call',
                    ],
                ]);
        } catch (Throwable) {
            return response()->json(['status' => 'error', 'message' => 'The payment service is temporarily unavailable. Please try again.'], 502);
        }

        if (! $response->successful() || blank($response->json('id'))) {
            return response()->json(['status' => 'error', 'message' => 'The payment order could not be created. Please try again.'], 502);
        }

        $booking->update(['razorpay_order_id' => $response->json('id')]);

        return response()->json([
            'status' => 'success',
            'order_id' => $response->json('id'),
            'amount' => (int) round((float) $booking->amount * 100),
            'currency' => $booking->currency,
            'key' => $keyId,
            'description' => 'Legal Bruz consultation call',
            'name' => $booking->name,
            'email' => $booking->email,
            'phone' => $booking->phone,
        ]);
    }

    public function verify(Request $request, ConsultationBooking $booking, RazorpayPaymentVerifier $verifier): JsonResponse
    {
        $validated = $request->validate([
            'razorpay_payment_id' => ['required', 'string', 'max:255'],
            'razorpay_order_id' => ['required', 'string', 'max:255'],
            'razorpay_signature' => ['required', 'string', 'max:255'],
        ]);

        if ($booking->payment_status === 'paid') {
            if (! hash_equals((string) $booking->razorpay_order_id, $validated['razorpay_order_id'])
                || ! hash_equals((string) $booking->transaction_id, $validated['razorpay_payment_id'])) {
                return response()->json(['status' => 'error', 'message' => 'This booking is already linked to a different payment.'], 422);
            }

            return response()->json([
                'status' => 'success',
                'redirect_url' => route('book-call.success', $booking),
            ]);
        }

        try {
            $verifier->verifyCaptured(
                $validated['razorpay_payment_id'],
                $validated['razorpay_order_id'],
                $validated['razorpay_signature'],
                (string) $booking->razorpay_order_id,
                (int) round((float) $booking->amount * 100),
                $booking->currency,
            );
        } catch (PaymentVerificationException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], $exception->httpStatus());
        }

        $booking->update([
            'payment_status' => 'paid',
            'transaction_id' => $validated['razorpay_payment_id'],
            'paid_at' => now(),
        ]);

        $booking->contactMessage?->update([
            'subject' => 'Book a Call — Paid',
            'message' => $this->contactMessageBody(
                $booking->topic_label,
                $booking->preferred_date->toDateString(),
                $booking->time_slot_label,
                (float) $booking->amount,
                'Paid — '.$validated['razorpay_payment_id'],
                $booking->message,
            ),
        ]);

        return response()->json([
            'status' => 'success',
            'redirect_url' => route('book-call.success', $booking),
        ]);
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
