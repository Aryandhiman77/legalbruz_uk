<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use App\Support\TrademarkWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentSplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_without_a_completed_payment_is_shown_the_advance_even_if_legacy_status_is_final(): void
    {
        [$user, $application] = $this->application(TrademarkWorkflow::PAYMENT_PENDING_FINAL);

        $this->actingAs($user)
            ->get(route('payment.show', $application->id))
            ->assertOk()
            ->assertSee('50% Advance Payment')
            ->assertSee('£199.50')
            ->assertDontSee('Full Payment')
            ->assertDontSee('Final 50% Balance');
    }

    public function test_final_half_is_only_shown_after_the_advance_and_client_approval(): void
    {
        [$user, $application] = $this->application(TrademarkWorkflow::PAYMENT_PENDING_FINAL);
        $this->completedPayment($user, $application, 'advance', 199.50, '50%');

        $this->actingAs($user)
            ->get(route('payment.show', $application->id))
            ->assertOk()
            ->assertSee('Final 50% Balance')
            ->assertSee('£199.50');
    }

    public function test_final_payment_cannot_be_created_before_an_advance_payment(): void
    {
        [$user, $application] = $this->application(TrademarkWorkflow::PAYMENT_PENDING_FINAL);

        $this->actingAs($user)
            ->postJson(route('payment.create-order', $application->id), [
                'amount' => 199.50,
                'payment_type' => 'final',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_type');
    }

    public function test_full_payment_option_cannot_be_created_from_the_order_api(): void
    {
        [$user, $application] = $this->application(TrademarkWorkflow::DRAFT);

        $this->actingAs($user)
            ->postJson(route('payment.create-order', $application->id), [
                'amount' => 399,
                'payment_type' => 'full',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_type');
    }

    public function test_final_payment_cannot_be_created_before_client_approval(): void
    {
        [$user, $application] = $this->application(TrademarkWorkflow::UNDER_REVIEW);
        $this->completedPayment($user, $application, 'advance', 199.50, '50%');

        $this->actingAs($user)
            ->postJson(route('payment.create-order', $application->id), [
                'amount' => 199.50,
                'payment_type' => 'final',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_type');
    }

    public function test_application_is_not_advanced_until_razorpay_confirms_exact_captured_payment(): void
    {
        Mail::fake();
        config([
            'razorpay.key_id' => 'rzp_test_key',
            'razorpay.key_secret' => 'test_secret',
            'razorpay.currency' => 'GBP',
        ]);

        [$user, $application] = $this->application(TrademarkWorkflow::DRAFT);
        $payment = Payment::query()->create([
            'application_id' => $application->id,
            'user_id' => $user->id,
            'amount' => 199.50,
            'total_amount' => 399,
            'payment_type' => 'advance',
            'percentage' => '50%',
            'payment_method' => 'razorpay',
            'status' => 'pending',
            'reference_number' => 'order_application_secure_1',
        ]);
        $signature = hash_hmac('sha256', 'order_application_secure_1|pay_application_secure_1', 'test_secret');

        Http::fake([
            'api.razorpay.com/v1/payments/pay_application_secure_1' => Http::sequence()
                ->push([
                    'id' => 'pay_application_secure_1',
                    'order_id' => 'order_application_secure_1',
                    'amount' => 1,
                    'currency' => 'GBP',
                    'status' => 'captured',
                    'captured' => true,
                    'amount_refunded' => 0,
                ])
                ->push([
                    'id' => 'pay_application_secure_1',
                    'order_id' => 'order_application_secure_1',
                    'amount' => 19950,
                    'currency' => 'GBP',
                    'status' => 'captured',
                    'captured' => true,
                    'amount_refunded' => 0,
                ]),
        ]);

        $this->actingAs($user)
            ->postJson(route('payment.verify-signature', $application), [
                'razorpay_payment_id' => 'pay_application_secure_1',
                'razorpay_order_id' => 'order_application_secure_1',
                'razorpay_signature' => $signature,
            ])
            ->assertUnprocessable();

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame(TrademarkWorkflow::DRAFT, $application->fresh()->current_status);

        $this->actingAs($user)
            ->postJson(route('payment.verify-signature', $application), [
                'razorpay_payment_id' => 'pay_application_secure_1',
                'razorpay_order_id' => 'order_application_secure_1',
                'razorpay_signature' => $signature,
            ])
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame('pay_application_secure_1', $payment->fresh()->transaction_id);
        $this->assertSame(TrademarkWorkflow::UNDER_REVIEW, $application->fresh()->current_status);
    }

    public function test_invoice_shows_the_saved_coupon_code_and_exact_discount_breakdown(): void
    {
        [$user, $application] = $this->application(TrademarkWorkflow::UNDER_REVIEW);
        $payment = Payment::query()->create([
            'application_id' => $application->id,
            'user_id' => $user->id,
            'amount' => 174.50,
            'total_amount' => 399,
            'payment_type' => 'advance',
            'discount_coupon_id' => 123,
            'coupon_code' => 'TM50',
            'discount_amount' => 25,
            'discounted_total_amount' => 349,
            'percentage' => '50%',
            'payment_method' => 'razorpay',
            'status' => 'completed',
            'reference_number' => 'order_discount_snapshot',
            'transaction_id' => 'pay_discount_snapshot',
            'paid_at' => now(),
        ]);

        $invoice = view('emails.attachments.invoice', [
            'application' => $application,
            'user' => $user,
            'payment' => $payment,
            'invoiceNumber' => 'INV-TEST-1',
            'paymentLabel' => 'Advance Payment (50%)',
            'issuedAt' => $payment->paid_at,
            'firmName' => 'Legal Bruz Ltd.',
            'firmEmail' => 'test@example.com',
        ])->render();

        $this->assertStringContainsString('50% advance before discount', $invoice);
        $this->assertStringContainsString('GBP 199.50', $invoice);
        $this->assertStringContainsString('Coupon discount', $invoice);
        $this->assertStringContainsString('TM50', $invoice);
        $this->assertStringContainsString('- GBP 25.00', $invoice);
        $this->assertStringContainsString('GBP 174.50', $invoice);
    }

    private function application(string $status): array
    {
        $user = User::factory()->create();
        $application = Application::query()->create([
            'user_id' => $user->id,
            'type' => 'trademark',
            'entity_type' => 'individual',
            'applicant_name' => 'Test Applicant',
            'phone' => '+447123456789',
            'email' => $user->email,
            'brand_name' => 'Split Payment Mark',
            'status' => $status,
            'service_status' => $status,
        ]);

        return [$user, $application];
    }

    private function completedPayment(
        User $user,
        Application $application,
        string $type,
        float $amount,
        string $percentage
    ): Payment {
        return Payment::query()->create([
            'application_id' => $application->id,
            'user_id' => $user->id,
            'amount' => $amount,
            'total_amount' => 399,
            'payment_type' => $type,
            'percentage' => $percentage,
            'payment_method' => 'razorpay',
            'status' => 'completed',
            'reference_number' => 'test_' . $type . '_' . $application->id,
            'paid_at' => now(),
        ]);
    }
}
