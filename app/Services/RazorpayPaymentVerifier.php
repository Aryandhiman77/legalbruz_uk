<?php

namespace App\Services;

use App\Exceptions\PaymentVerificationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class RazorpayPaymentVerifier
{
    public function verifyCaptured(
        string $paymentId,
        string $submittedOrderId,
        string $signature,
        string $expectedOrderId,
        int $expectedAmount,
        string $expectedCurrency,
    ): array {
        $keyId = (string) config('razorpay.key_id');
        $secret = (string) config('razorpay.key_secret');

        if ($keyId === '' || $secret === '') {
            throw new PaymentVerificationException('Online payment verification is temporarily unavailable.', 503);
        }

        if (! preg_match('/^pay_[A-Za-z0-9_]+$/', $paymentId)
            || ! preg_match('/^order_[A-Za-z0-9_]+$/', $submittedOrderId)
            || ! preg_match('/^[a-f0-9]{64}$/i', $signature)) {
            throw new PaymentVerificationException('The payment response is invalid.');
        }

        if ($expectedOrderId === '' || ! hash_equals($expectedOrderId, $submittedOrderId)) {
            throw new PaymentVerificationException('This payment order does not belong to the requested service.');
        }

        $expectedSignature = hash_hmac('sha256', $expectedOrderId.'|'.$paymentId, $secret);
        if (! hash_equals($expectedSignature, $signature)) {
            throw new PaymentVerificationException('Payment signature verification failed.');
        }

        try {
            $response = Http::withBasicAuth($keyId, $secret)
                ->acceptJson()
                ->timeout((int) config('razorpay.timeout', 30))
                ->get('https://api.razorpay.com/v1/payments/'.rawurlencode($paymentId));
        } catch (ConnectionException) {
            throw new PaymentVerificationException('The payment gateway could not be reached. Please retry verification.', 502);
        } catch (Throwable) {
            throw new PaymentVerificationException('The payment gateway returned an unexpected response. Please retry verification.', 502);
        }

        if (! $response->successful()) {
            $status = $response->serverError() ? 502 : 422;
            throw new PaymentVerificationException('Razorpay could not confirm this payment.', $status);
        }

        $payment = $response->json();
        if (! is_array($payment)) {
            throw new PaymentVerificationException('Razorpay returned an invalid payment response.', 502);
        }

        $currency = strtoupper((string) ($payment['currency'] ?? ''));
        $captured = ($payment['status'] ?? null) === 'captured' && ($payment['captured'] ?? false) === true;

        if (($payment['id'] ?? null) !== $paymentId
            || ($payment['order_id'] ?? null) !== $expectedOrderId
            || (int) ($payment['amount'] ?? -1) !== $expectedAmount
            || $currency !== strtoupper($expectedCurrency)
            || ! $captured
            || (int) ($payment['amount_refunded'] ?? 0) !== 0) {
            throw new PaymentVerificationException('Payment is not captured for the exact order amount and currency.');
        }

        return $payment;
    }
}
