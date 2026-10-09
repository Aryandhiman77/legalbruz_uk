<?php

namespace Tests\Unit;

use App\Exceptions\PaymentVerificationException;
use App\Services\RazorpayPaymentVerifier;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RazorpayPaymentVerifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'razorpay.key_id' => 'rzp_test_key',
            'razorpay.key_secret' => 'test_secret',
            'razorpay.currency' => 'GBP',
        ]);
    }

    public function test_it_accepts_only_an_exact_captured_payment_from_razorpay(): void
    {
        Http::fake([
            'api.razorpay.com/v1/payments/pay_secure_1' => Http::response([
                'id' => 'pay_secure_1',
                'order_id' => 'order_secure_1',
                'amount' => 14900,
                'currency' => 'GBP',
                'status' => 'captured',
                'captured' => true,
                'amount_refunded' => 0,
            ]),
        ]);

        $payment = app(RazorpayPaymentVerifier::class)->verifyCaptured(
            'pay_secure_1',
            'order_secure_1',
            hash_hmac('sha256', 'order_secure_1|pay_secure_1', 'test_secret'),
            'order_secure_1',
            14900,
            'GBP',
        );

        $this->assertSame('captured', $payment['status']);
    }

    public function test_it_rejects_a_forged_signature_without_calling_razorpay(): void
    {
        Http::fake();

        $this->expectException(PaymentVerificationException::class);

        try {
            app(RazorpayPaymentVerifier::class)->verifyCaptured(
                'pay_secure_2',
                'order_secure_2',
                str_repeat('0', 64),
                'order_secure_2',
                2500,
                'GBP',
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_it_rejects_authorized_or_wrong_amount_payments(): void
    {
        Http::fake([
            'api.razorpay.com/v1/payments/pay_secure_3' => Http::response([
                'id' => 'pay_secure_3',
                'order_id' => 'order_secure_3',
                'amount' => 1,
                'currency' => 'GBP',
                'status' => 'authorized',
                'captured' => false,
                'amount_refunded' => 0,
            ]),
        ]);

        $this->expectException(PaymentVerificationException::class);

        app(RazorpayPaymentVerifier::class)->verifyCaptured(
            'pay_secure_3',
            'order_secure_3',
            hash_hmac('sha256', 'order_secure_3|pay_secure_3', 'test_secret'),
            'order_secure_3',
            2500,
            'GBP',
        );
    }

    public function test_it_fails_safely_when_gateway_returns_malformed_success_response(): void
    {
        Http::fake([
            'api.razorpay.com/v1/payments/pay_secure_4' => Http::response('<html>gateway error</html>', 200),
        ]);

        try {
            app(RazorpayPaymentVerifier::class)->verifyCaptured(
                'pay_secure_4',
                'order_secure_4',
                hash_hmac('sha256', 'order_secure_4|pay_secure_4', 'test_secret'),
                'order_secure_4',
                2500,
                'GBP',
            );

            $this->fail('Malformed gateway responses must never be accepted.');
        } catch (PaymentVerificationException $exception) {
            $this->assertSame(502, $exception->httpStatus());
            $this->assertStringContainsString('invalid payment response', $exception->getMessage());
        }
    }
}
