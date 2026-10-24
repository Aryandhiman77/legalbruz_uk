<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Application;
use App\Models\ConsultationBooking;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\TrademarkSearchReportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class RevolutPaymentHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_and_admin_can_view_completed_payment_history_and_invoice(): void
    {
        $user = User::factory()->create();
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'service' => 'consultation',
            'provider' => 'revolut',
            'provider_order_id' => 'order_history_1',
            'provider_payment_id' => 'payment_history_1',
            'amount' => 25,
            'currency' => 'GBP',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('payment.history'))
            ->assertOk()
            ->assertSee('Consultation call')
            ->assertSee('order_history_1')
            ->assertSee(route('payment.transaction.invoice', $transaction), false);

        $this->actingAs($user)
            ->get(route('payment.transaction.invoice', $transaction))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => bcrypt('password')]);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('Payments &amp; Invoices', false)
            ->assertSee('payment_history_1');
    }

    public function test_admin_transaction_invoice_uses_the_original_consultation_booking_details(): void
    {
        config([
            'company.registered_office' => 'Registered Office: 10 Example Street, London, SW1A 1AA',
            'company.number' => '12345678',
        ]);
        $booking = ConsultationBooking::create([
            'public_id' => (string) Str::uuid(),
            'name' => 'Alex Morgan',
            'email' => 'alex@example.test',
            'phone' => '+447123456789',
            'business_name' => 'Morgan Studio Ltd',
            'service_topic' => 'trademark_application',
            'preferred_date' => now()->addDay()->toDateString(),
            'preferred_time' => '10_12',
            'message' => 'I would like advice about a UK trade mark application.',
            'amount' => 25,
            'currency' => 'GBP',
            'payment_status' => 'paid',
            'payment_reference' => 'consultation_order_original',
            'transaction_id' => 'consultation_payment_original',
            'paid_at' => now(),
        ]);
        $transaction = PaymentTransaction::create([
            'payable_type' => ConsultationBooking::class,
            'payable_id' => $booking->id,
            'service' => 'consultation',
            'provider' => 'revolut',
            'provider_order_id' => 'consultation_order_original',
            'provider_payment_id' => 'consultation_payment_original',
            'amount' => 25,
            'currency' => 'GBP',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $invoice = view('consultations.payment-invoice', [
            'booking' => $booking,
            'transaction' => $transaction,
            'invoiceNumber' => 'CON-INV-TEST-1',
            'issuedAt' => $transaction->paid_at,
            'firmName' => 'Legal Bruz Ltd.',
            'firmEmail' => 'test@example.com',
        ])->render();

        $this->assertStringContainsString('Alex Morgan', $invoice);
        $this->assertStringContainsString('alex@example.test', $invoice);
        $this->assertStringContainsString('Morgan Studio Ltd', $invoice);
        $this->assertStringContainsString('UK trade mark application', $invoice);
        $this->assertStringContainsString('Registered office:</strong> 10 Example Street', $invoice);
        $this->assertStringNotContainsString('Registered Office: Registered Office:', $invoice);

        $admin = Admin::create(['name' => 'Admin', 'email' => 'invoice-admin@example.test', 'password' => bcrypt('password')]);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.payment.transaction.invoice', $transaction))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename=consultation-invoice-'.$booking->id.'.pdf');
    }

    public function test_customer_history_includes_legacy_paid_services_and_uses_the_original_application_invoice(): void
    {
        $user = User::factory()->create();
        $application = Application::create([
            'user_id' => $user->id,
            'type' => 'trademark',
            'entity_type' => 'individual',
            'applicant_name' => 'History Applicant',
            'phone' => '+447123456789',
            'email' => $user->email,
            'brand_name' => 'History Mark',
        ]);
        $legacyPayment = Payment::create([
            'application_id' => $application->id,
            'user_id' => $user->id,
            'amount' => 200,
            'total_amount' => 400,
            'percentage' => '50%',
            'payment_method' => 'legacy',
            'status' => 'completed',
            'reference_number' => 'legacy_application_order',
            'transaction_id' => 'legacy_application_payment',
            'paid_at' => now()->subDays(3),
        ]);
        $revolutPayment = Payment::create([
            'application_id' => $application->id,
            'user_id' => $user->id,
            'amount' => 200,
            'total_amount' => 400,
            'payment_type' => 'final',
            'percentage' => '50%',
            'payment_method' => 'revolut',
            'status' => 'completed',
            'reference_number' => 'revolut_application_order',
            'transaction_id' => 'revolut_application_payment',
            'paid_at' => now(),
        ]);
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'payable_type' => Payment::class,
            'payable_id' => $revolutPayment->id,
            'service' => 'trademark_application_final',
            'provider' => 'revolut',
            'provider_order_id' => 'revolut_application_order',
            'provider_payment_id' => 'revolut_application_payment',
            'amount' => 200,
            'currency' => 'GBP',
            'status' => 'completed',
            'paid_at' => now(),
        ]);
        ConsultationBooking::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '+447123456789',
            'service_topic' => 'trademark_application',
            'preferred_date' => now()->addDay()->toDateString(),
            'preferred_time' => '10_12',
            'message' => 'History test',
            'amount' => 25,
            'currency' => 'GBP',
            'payment_status' => 'paid',
            'payment_reference' => 'legacy_consultation_order',
            'transaction_id' => 'legacy_consultation_payment',
            'paid_at' => now()->subDays(2),
        ]);
        TrademarkSearchReportRequest::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '+447123456789',
            'brand_name' => 'History Search Mark',
            'business_activity' => 'Legal services',
            'amount' => 149,
            'currency' => 'GBP',
            'payment_status' => 'paid',
            'report_status' => TrademarkSearchReportRequest::PAYMENT_RECEIVED,
            'payment_reference' => 'legacy_search_order',
            'transaction_id' => 'legacy_search_payment',
            'paid_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->get(route('payment.history'))
            ->assertOk()
            ->assertSee('legacy_application_payment')
            ->assertSee('revolut_application_payment')
            ->assertSee('legacy_consultation_payment')
            ->assertSee('legacy_search_payment')
            ->assertSee(route('payment.invoice', $legacyPayment), false)
            ->assertSee(route('payment.invoice', $revolutPayment), false)
            ->assertDontSee(route('payment.transaction.invoice', $transaction), false);

        $this->actingAs($user)
            ->get(route('payment.transaction.invoice', $transaction))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename=invoice-'.$application->id.'-'.$revolutPayment->id.'.pdf');
    }

    public function test_signed_webhook_retrieves_and_records_authoritative_order_state(): void
    {
        config([
            'revolut.secret_key' => 'sk_test_secret',
            'revolut.webhook_secret' => 'wsk_test_secret',
            'revolut.environment' => 'sandbox',
            'revolut.api_version' => '2026-08-17',
        ]);
        PaymentTransaction::create([
            'service' => 'consultation',
            'provider' => 'revolut',
            'provider_order_id' => 'order_webhook_1',
            'amount' => 25,
            'currency' => 'GBP',
            'status' => 'pending',
        ]);
        Http::fake([
            'sandbox-merchant.revolut.com/api/orders/order_webhook_1' => Http::response([
                'id' => 'order_webhook_1',
                'state' => 'completed',
                'amount' => 2500,
                'currency' => 'GBP',
                'payments' => [['id' => 'payment_webhook_1', 'state' => 'completed']],
            ]),
        ]);

        $payload = '{"event":"ORDER_COMPLETED","order_id":"order_webhook_1"}';
        $timestamp = (string) (now('UTC')->timestamp * 1000);
        $signature = 'v1='.hash_hmac('sha256', 'v1.'.$timestamp.'.'.$payload, 'wsk_test_secret');

        $this->call('POST', route('webhooks.revolut'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_REVOLUT_REQUEST_TIMESTAMP' => $timestamp,
            'HTTP_REVOLUT_SIGNATURE' => $signature,
        ], $payload)->assertOk()->assertJson(['received' => true]);

        $this->assertDatabaseHas('payment_transactions', [
            'provider_order_id' => 'order_webhook_1',
            'provider_payment_id' => 'payment_webhook_1',
            'status' => 'completed',
        ]);
    }

    public function test_webhook_rejects_an_invalid_signature_without_contacting_revolut(): void
    {
        config(['revolut.webhook_secret' => 'wsk_test_secret']);
        Http::fake();

        $this->withHeaders([
            'Revolut-Request-Timestamp' => (string) (now('UTC')->timestamp * 1000),
            'Revolut-Signature' => 'v1=invalid',
        ])->postJson(route('webhooks.revolut'), [
            'event' => 'ORDER_COMPLETED',
            'order_id' => 'unknown',
        ])->assertUnauthorized();

        Http::assertNothingSent();
    }
}
