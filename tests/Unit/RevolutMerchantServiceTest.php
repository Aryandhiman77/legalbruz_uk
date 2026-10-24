<?php

namespace Tests\Unit;

use App\Exceptions\PaymentVerificationException;
use App\Services\RevolutMerchantService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RevolutMerchantServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'revolut.secret_key' => 'sk_test_secret',
            'revolut.webhook_secret' => 'wsk_test_secret',
            'revolut.environment' => 'sandbox',
            'revolut.api_version' => '2026-08-17',
            'revolut.webhook_tolerance_seconds' => 300,
        ]);
    }

    public function test_it_retrieves_orders_with_bearer_auth_and_version_header(): void
    {
        Http::fake([
            'sandbox-merchant.revolut.com/api/orders/order_secure_1' => Http::response([
                'id' => 'order_secure_1',
                'state' => 'completed',
                'amount' => 14900,
                'currency' => 'GBP',
            ]),
        ]);

        $order = app(RevolutMerchantService::class)->retrieveOrder('order_secure_1');

        $this->assertSame('completed', $order['state']);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk_test_secret')
            && $request->hasHeader('Revolut-Api-Version', '2026-08-17'));
    }

    public function test_it_verifies_a_valid_webhook_signature_and_rejects_tampering(): void
    {
        $timestamp = (string) (now('UTC')->timestamp * 1000);
        $payload = '{"event":"ORDER_COMPLETED","order_id":"order_secure_1"}';
        $signature = 'v1='.hash_hmac('sha256', 'v1.'.$timestamp.'.'.$payload, 'wsk_test_secret');
        $service = app(RevolutMerchantService::class);

        $this->assertTrue($service->verifyWebhook($payload, $timestamp, $signature));
        $this->assertFalse($service->verifyWebhook($payload.' ', $timestamp, $signature));
    }

    public function test_it_rejects_stale_webhooks(): void
    {
        $timestamp = (string) ((now('UTC')->subMinutes(6)->timestamp) * 1000);
        $payload = '{"event":"ORDER_COMPLETED"}';
        $signature = 'v1='.hash_hmac('sha256', 'v1.'.$timestamp.'.'.$payload, 'wsk_test_secret');

        $this->assertFalse(app(RevolutMerchantService::class)->verifyWebhook($payload, $timestamp, $signature));
    }

    public function test_it_fails_safely_when_revolut_returns_a_malformed_success_response(): void
    {
        Http::fake([
            'sandbox-merchant.revolut.com/api/orders/order_secure_4' => Http::response('<html>gateway error</html>', 200),
        ]);

        $this->expectException(PaymentVerificationException::class);
        app(RevolutMerchantService::class)->retrieveOrder('order_secure_4');
    }
}
