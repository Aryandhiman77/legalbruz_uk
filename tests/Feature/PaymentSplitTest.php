<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Payment;
use App\Models\PaymentTransaction;
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
            ->assertSee('Full Payment')
            ->assertSee('£199.50')
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

    public function test_full_payment_option_creates_an_exact_server_verified_order(): void
    {
        Mail::fake();
        config([
            'revolut.secret_key' => 'sk_test_secret',
            'revolut.environment' => 'sandbox',
            'revolut.api_version' => '2026-08-17',
        ]);
        Http::fake([
            'sandbox-merchant.revolut.com/api/orders' => Http::response([
                'id' => 'order_application_full_1',
                'state' => 'pending',
                'checkout_url' => 'https://checkout.revolut.test/application-full',
            ], 201),
            'sandbox-merchant.revolut.com/api/orders/order_application_full_1' => Http::response([
                'id' => 'order_application_full_1',
                'amount' => 39900,
                'currency' => 'GBP',
                'state' => 'completed',
                'payments' => [['id' => 'pay_application_full_1', 'state' => 'completed']],
            ]),
        ]);
        [$user, $application] = $this->application(TrademarkWorkflow::DRAFT);

        $this->actingAs($user)
            ->postJson(route('payment.create-order', $application->id), [
                'amount' => 1,
                'payment_type' => 'full',
            ])
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'order_id' => 'order_application_full_1',
                'amount' => 39900,
            ]);

        $this->assertDatabaseHas('payments', [
            'application_id' => $application->id,
            'payment_type' => 'full',
            'percentage' => '100%',
            'amount' => 399,
            'status' => 'pending',
        ]);
        Http::assertSent(fn ($request) => $request['amount'] === 39900
            && $request['currency'] === 'GBP'
            && data_get($request->data(), 'metadata.payment_type') === 'full');

        $payment = Payment::where('application_id', $application->id)->latest('id')->firstOrFail();
        $this->actingAs($user)
            ->get(route('payment.confirm', ['id' => $application->id, 'payment' => $payment->id]))
            ->assertRedirect(route('trademark.status', $application->id))
            ->assertSessionHas('success', 'Full payment verified successfully. Your application has been sent for admin review.');

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame('pay_application_full_1', $payment->fresh()->transaction_id);
        $this->assertSame(TrademarkWorkflow::UNDER_REVIEW, $application->fresh()->current_status);
    }

    public function test_full_payment_cannot_be_created_after_an_advance_payment(): void
    {
        [$user, $application] = $this->application(TrademarkWorkflow::UNDER_REVIEW);
        $this->completedPayment($user, $application, 'advance', 199.50, '50%');

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

    public function test_application_is_not_advanced_until_revolut_confirms_exact_completed_payment(): void
    {
        Mail::fake();
        config([
            'revolut.secret_key' => 'sk_test_secret',
            'revolut.environment' => 'sandbox',
            'revolut.api_version' => '2026-08-17',
        ]);

        [$user, $application] = $this->application(TrademarkWorkflow::DRAFT);
        $payment = Payment::query()->create([
            'application_id' => $application->id,
            'user_id' => $user->id,
            'amount' => 199.50,
            'total_amount' => 399,
            'payment_type' => 'advance',
            'percentage' => '50%',
            'payment_method' => 'revolut',
            'status' => 'pending',
            'reference_number' => 'order_application_secure_1',
        ]);
        PaymentTransaction::create([
            'user_id' => $user->id,
            'payable_type' => Payment::class,
            'payable_id' => $payment->id,
            'service' => 'trademark_application_advance',
            'provider' => 'revolut',
            'provider_order_id' => 'order_application_secure_1',
            'amount' => 199.50,
            'currency' => 'GBP',
            'status' => 'pending',
        ]);

        Http::fake([
            'sandbox-merchant.revolut.com/api/orders/order_application_secure_1' => Http::sequence()
                ->push([
                    'id' => 'order_application_secure_1',
                    'amount' => 1,
                    'currency' => 'GBP',
                    'state' => 'completed',
                    'payments' => [['id' => 'pay_application_secure_1', 'state' => 'completed']],
                ])
                ->push([
                    'id' => 'order_application_secure_1',
                    'amount' => 19950,
                    'currency' => 'GBP',
                    'state' => 'completed',
                    'payments' => [['id' => 'pay_application_secure_1', 'state' => 'completed']],
                ]),
        ]);

        $this->actingAs($user)
            ->get(route('payment.confirm', ['id' => $application->id, 'payment' => $payment->id]))
            ->assertRedirect(route('payment.show', $application->id));

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame(TrademarkWorkflow::DRAFT, $application->fresh()->current_status);

        $this->actingAs($user)
            ->get(route('payment.confirm', ['id' => $application->id, 'payment' => $payment->id]))
            ->assertRedirect(route('trademark.status', $application->id));

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
            'payment_method' => 'revolut',
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

    public function test_invoice_shows_the_exact_applicant_type_selected_in_the_application_form(): void
    {
        [$user, $application] = $this->application(TrademarkWorkflow::UNDER_REVIEW);
        $application->update([
            'entity_type' => 'company',
            'members_details' => [
                'applicant_details' => [
                    'applicant_type' => 'limited_company',
                ],
            ],
        ]);
        $payment = $this->completedPayment($user, $application, 'advance', 199.50, '50%');

        $invoice = view('emails.attachments.invoice', [
            'application' => $application->fresh(),
            'user' => $user,
            'payment' => $payment,
            'invoiceNumber' => 'INV-TEST-APPLICANT-TYPE',
            'paymentLabel' => 'Advance Payment (50%)',
            'issuedAt' => $payment->paid_at,
            'firmName' => 'Legal Bruz Ltd.',
            'firmEmail' => 'test@example.com',
        ])->render();

        $this->assertStringContainsString('Limited company', $invoice);
        $this->assertStringNotContainsString('Individual / Proprietor / Trader', $invoice);
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
            'payment_method' => 'revolut',
            'status' => 'completed',
            'reference_number' => 'test_'.$type.'_'.$application->id,
            'paid_at' => now(),
        ]);
    }
}
