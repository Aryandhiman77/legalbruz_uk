<?php

namespace App\Services;

use App\Exceptions\PaymentVerificationException;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class RevolutMerchantService
{
    public function createHostedOrder(
        Model $payable,
        string $service,
        float $amount,
        string $currency,
        string $description,
        string $redirectUrl,
        ?int $userId = null,
        ?string $customerEmail = null,
        array $metadata = [],
    ): PaymentTransaction {
        $secret = trim((string) config('revolut.secret_key'));
        if ($secret === '') {
            throw new PaymentVerificationException('Online payment is temporarily unavailable. Please contact us for assistance.', 503);
        }

        $amountMinor = (int) round($amount * 100);
        $currency = strtoupper($currency);
        $existingTransaction = PaymentTransaction::query()
            ->whereMorphedTo('payable', $payable)
            ->where('service', $service)
            ->where('provider', 'revolut')
            ->whereIn('status', ['pending', 'processing'])
            ->where('amount', $amount)
            ->where('currency', $currency)
            ->whereNotNull('checkout_url')
            ->latest('id')
            ->first();

        if ($existingTransaction) {
            return $existingTransaction;
        }

        $idempotencyKey = (string) Str::uuid();
        $payload = [
            'amount' => $amountMinor,
            'currency' => $currency,
            'description' => Str::limit($description, 255, ''),
            'redirect_url' => $redirectUrl,
            'capture_mode' => 'automatic',
            'metadata' => array_map(static fn ($value) => (string) $value, array_merge($metadata, [
                'service' => $service,
                'payable_type' => class_basename($payable),
                'payable_id' => $payable->getKey(),
            ])),
        ];

        if (filled($customerEmail)) {
            $payload['customer'] = ['email' => $customerEmail];
        }

        try {
            $response = $this->client()
                ->withHeader('Idempotency-Key', $idempotencyKey)
                ->post($this->baseUrl().'/api/orders', $payload);
        } catch (Throwable $exception) {
            report($exception);
            throw new PaymentVerificationException('The payment service is temporarily unavailable. Please try again.', 502);
        }

        if (! $response->successful() || blank($response->json('id')) || blank($response->json('checkout_url'))) {
            throw new PaymentVerificationException('The payment order could not be created. Please try again.', 502);
        }

        return PaymentTransaction::create([
            'user_id' => $userId,
            'payable_type' => $payable::class,
            'payable_id' => $payable->getKey(),
            'service' => $service,
            'provider' => 'revolut',
            'provider_order_id' => $response->json('id'),
            'amount' => $amount,
            'currency' => $currency,
            'status' => $this->normaliseStatus((string) $response->json('state', 'pending')),
            'checkout_url' => $response->json('checkout_url'),
            'metadata' => $metadata,
            'provider_payload' => $response->json(),
        ]);
    }

    public function verifyCompleted(Model $payable, string $orderId): PaymentTransaction
    {
        $transaction = PaymentTransaction::query()
            ->where('provider', 'revolut')
            ->where('provider_order_id', $orderId)
            ->where('payable_type', $payable::class)
            ->where('payable_id', $payable->getKey())
            ->first();

        if (! $transaction) {
            throw new PaymentVerificationException('This payment order does not belong to this service.', 422);
        }

        $order = $this->retrieveOrder($orderId);
        if (! hash_equals($transaction->provider_order_id, (string) ($order['id'] ?? ''))
            || (int) ($order['amount'] ?? -1) !== (int) round((float) $transaction->amount * 100)
            || strtoupper((string) ($order['currency'] ?? '')) !== strtoupper($transaction->currency)) {
            throw new PaymentVerificationException('The payment details returned by Revolut do not match this order.', 422);
        }

        $status = $this->normaliseStatus((string) ($order['state'] ?? ''));
        $providerPaymentId = $this->completedPaymentId($order);
        $transaction->update([
            'status' => $status,
            'provider_payment_id' => $providerPaymentId ?: $transaction->provider_payment_id,
            'provider_payload' => $order,
            'paid_at' => $status === 'completed' ? ($transaction->paid_at ?? now()) : null,
        ]);

        if ($status !== 'completed') {
            throw new PaymentVerificationException('Revolut has not confirmed this payment as completed.', 422);
        }

        return $transaction->fresh();
    }

    public function retrieveOrder(string $orderId): array
    {
        try {
            $response = $this->client()->get($this->baseUrl().'/api/orders/'.rawurlencode($orderId));
        } catch (Throwable $exception) {
            report($exception);
            throw new PaymentVerificationException('Revolut could not confirm this payment.', 502);
        }

        if (! $response->successful() || ! is_array($response->json())) {
            throw new PaymentVerificationException('Revolut could not confirm this payment.', $response->status() >= 500 ? 502 : 422);
        }

        return $response->json();
    }

    public function verifyWebhook(string $rawPayload, ?string $timestamp, ?string $signatureHeader): bool
    {
        $secret = trim((string) config('revolut.webhook_secret'));
        if ($secret === '' || blank($timestamp) || blank($signatureHeader) || ! ctype_digit($timestamp)) {
            return false;
        }

        $timestampSeconds = strlen($timestamp) > 10 ? (int) floor(((int) $timestamp) / 1000) : (int) $timestamp;
        if (abs(now('UTC')->timestamp - $timestampSeconds) > (int) config('revolut.webhook_tolerance_seconds', 300)) {
            return false;
        }

        $expected = 'v1='.hash_hmac('sha256', 'v1.'.$timestamp.'.'.$rawPayload, $secret);

        return collect(explode(',', $signatureHeader))
            ->map(static fn ($signature) => trim($signature))
            ->contains(static fn ($signature) => hash_equals($expected, $signature));
    }

    public function syncWebhookOrder(string $orderId): ?PaymentTransaction
    {
        $transaction = PaymentTransaction::where('provider_order_id', $orderId)->first();
        if (! $transaction) {
            return null;
        }

        $order = $this->retrieveOrder($orderId);
        if (! hash_equals($transaction->provider_order_id, (string) ($order['id'] ?? ''))
            || (int) ($order['amount'] ?? -1) !== (int) round((float) $transaction->amount * 100)
            || strtoupper((string) ($order['currency'] ?? '')) !== strtoupper($transaction->currency)) {
            throw new PaymentVerificationException('The webhook order does not match the stored payment.', 422);
        }
        $status = $this->normaliseStatus((string) ($order['state'] ?? ''));
        $transaction->update([
            'status' => $status,
            'provider_payment_id' => $this->completedPaymentId($order) ?: $transaction->provider_payment_id,
            'provider_payload' => $order,
            'paid_at' => $status === 'completed' ? ($transaction->paid_at ?? now()) : $transaction->paid_at,
        ]);

        return $transaction->fresh();
    }

    private function client(): PendingRequest
    {
        return Http::withToken((string) config('revolut.secret_key'))
            ->withHeaders(['Revolut-Api-Version' => (string) config('revolut.api_version')])
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('revolut.timeout', 30));
    }

    private function baseUrl(): string
    {
        return config('revolut.environment') === 'production'
            ? 'https://merchant.revolut.com'
            : 'https://sandbox-merchant.revolut.com';
    }

    private function normaliseStatus(string $status): string
    {
        return match (strtolower($status)) {
            'completed' => 'completed',
            'failed' => 'failed',
            'cancelled' => 'cancelled',
            'processing', 'authorised' => 'processing',
            default => 'pending',
        };
    }

    private function completedPaymentId(array $order): ?string
    {
        $payments = collect($order['payments'] ?? []);
        $payment = $payments->first(fn ($payment) => in_array(strtolower((string) ($payment['state'] ?? '')), ['completed', 'captured'], true))
            ?? $payments->last();

        return filled($payment['id'] ?? null) ? (string) $payment['id'] : null;
    }
}
