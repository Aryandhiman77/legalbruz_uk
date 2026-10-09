<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ConsultationBooking;
use App\Models\ContactMessage;
use App\Models\TrademarkPricing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConsultationBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_a_call_uses_the_dynamic_fee_and_creates_a_pending_booking(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee(route('book-call.create'), false);

        $this->get(route('book-call.create'))
            ->assertOk()
            ->assertSee('Book a call with our team')
            ->assertSee('£25.00')
            ->assertSee('name="service_topic"', false)
            ->assertSee('name="preferred_time"', false);

        $response = $this->post(route('book-call.store'), $this->validPayload());
        $booking = ConsultationBooking::query()->firstOrFail();

        $response->assertRedirect(route('book-call.payment', $booking));
        $this->assertSame('25.00', $booking->amount);
        $this->assertSame('pending', $booking->payment_status);
        $this->assertDatabaseHas(ContactMessage::class, [
            'id' => $booking->contact_message_id,
            'email' => 'alex@example.test',
            'service_interested' => 'Consultation Call',
            'subject' => 'Book a Call — Payment pending',
        ]);

        $this->get(route('book-call.payment', $booking))
            ->assertOk()
            ->assertSee('Complete your consultation booking')
            ->assertSee('£25.00');
    }

    public function test_admin_can_change_the_consultation_fee_used_for_new_bookings(): void
    {
        $admin = Admin::create([
            'name' => 'Pricing Admin',
            'email' => 'consultation-pricing@example.test',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.trademark-pricing.edit'))
            ->assertOk()
            ->assertSee('Consultation Call')
            ->assertSee('name="prices[consultation_call]"', false);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.trademark-pricing.update'), [
                'prices' => [
                    TrademarkPricing::APPLICATION => 399,
                    TrademarkPricing::CONSULTATION => 42.50,
                ],
            ])
            ->assertRedirect(route('admin.trademark-pricing.edit'));

        $this->assertSame(42.5, TrademarkPricing::amountFor(TrademarkPricing::CONSULTATION));
        $this->get(route('book-call.create'))->assertOk()->assertSee('£42.50');

        $this->post(route('book-call.store'), $this->validPayload())->assertRedirect();
        $this->assertDatabaseHas(ConsultationBooking::class, ['amount' => 42.50]);
    }

    public function test_admin_contact_inbox_shows_consultation_payment_status_and_internal_notes(): void
    {
        $this->post(route('book-call.store'), $this->validPayload())->assertRedirect();
        $booking = ConsultationBooking::query()->firstOrFail();
        $admin = Admin::create([
            'name' => 'Consultation Admin',
            'email' => 'consultation-admin@example.test',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.contact-messages.index', ['search' => 'Consultation Call']))
            ->assertOk()
            ->assertSee('Pending')
            ->assertSee('£25.00');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.contact-messages.show', $booking->contact_message_id))
            ->assertOk()
            ->assertSee('Consultation payment')
            ->assertSee('Pending')
            ->assertSee('Not paid yet')
            ->assertSee('Admin Internal Notes')
            ->assertSee('name="internal_notes"', false);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.contact-messages.update', $booking->contact_message_id), [
                'status' => 'in_progress',
                'internal_notes' => 'Follow up after payment is completed.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(ContactMessage::class, [
            'id' => $booking->contact_message_id,
            'internal_notes' => 'Follow up after payment is completed.',
        ]);
    }

    public function test_consultation_payment_order_and_signature_are_verified(): void
    {
        config([
            'razorpay.key_id' => 'rzp_test_key',
            'razorpay.key_secret' => 'test_secret',
            'razorpay.currency' => 'GBP',
        ]);
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response(['id' => 'order_consultation_1'], 200),
            'api.razorpay.com/v1/payments/pay_consultation_1' => Http::response([
                'id' => 'pay_consultation_1',
                'order_id' => 'order_consultation_1',
                'amount' => 2500,
                'currency' => 'GBP',
                'status' => 'captured',
                'captured' => true,
                'amount_refunded' => 0,
            ], 200),
        ]);

        $this->post(route('book-call.store'), $this->validPayload())->assertRedirect();
        $booking = ConsultationBooking::query()->firstOrFail();

        $this->postJson(route('book-call.payment.order', $booking))
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'order_id' => 'order_consultation_1',
                'amount' => 2500,
                'currency' => 'GBP',
            ]);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.razorpay.com/v1/orders'
            && $request['amount'] === 2500
            && $request['currency'] === 'GBP');

        $paymentId = 'pay_consultation_1';
        $signature = hash_hmac('sha256', 'order_consultation_1|'.$paymentId, 'test_secret');
        $this->postJson(route('book-call.payment.verify', $booking), [
            'razorpay_payment_id' => $paymentId,
            'razorpay_order_id' => 'order_consultation_1',
            'razorpay_signature' => $signature,
        ])->assertOk()
            ->assertJson([
                'status' => 'success',
                'redirect_url' => route('book-call.success', $booking),
            ]);

        $booking->refresh();
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame($paymentId, $booking->transaction_id);
        $this->assertNotNull($booking->paid_at);
        $this->assertSame('Book a Call — Paid', $booking->contactMessage->subject);
        $this->assertStringContainsString('Payment status: Paid', $booking->contactMessage->message);

        $this->get(route('book-call.success', $booking))
            ->assertOk()
            ->assertSee('Your consultation request is confirmed');
    }

    public function test_client_dashboard_shows_consultation_information_payment_and_status(): void
    {
        $client = User::factory()->create([
            'name' => 'Alex Morgan',
            'email' => 'alex@example.test',
        ]);

        $this->actingAs($client)
            ->post(route('book-call.store'), $this->validPayload())
            ->assertRedirect();

        $booking = ConsultationBooking::query()->firstOrFail();
        $this->assertSame($client->id, $booking->user_id);

        $this->actingAs($client)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My Consultation Calls')
            ->assertSee('UK trade mark application')
            ->assertSee('£25.00')
            ->assertSee('Pending')
            ->assertSee('Awaiting payment')
            ->assertSee('Pay Now')
            ->assertSee(route('book-call.payment', $booking), false);

        $booking->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
            'transaction_id' => 'pay_dashboard_test',
        ]);

        $this->actingAs($client)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Paid')
            ->assertSee('Awaiting slot confirmation')
            ->assertSee('View Confirmation')
            ->assertSee(route('book-call.success', $booking), false);
    }

    public function test_guest_booking_is_visible_after_signing_in_with_the_same_email(): void
    {
        $this->post(route('book-call.store'), $this->validPayload())->assertRedirect();
        $booking = ConsultationBooking::query()->firstOrFail();
        $this->assertNull($booking->user_id);

        $client = User::factory()->create(['email' => 'alex@example.test']);

        $this->actingAs($client)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My Consultation Calls')
            ->assertSee($booking->topic_label)
            ->assertSee(route('book-call.payment', $booking), false);
    }

    private function validPayload(): array
    {
        return [
            'name' => 'Alex Morgan',
            'email' => 'alex@example.test',
            'phone' => '+447123456789',
            'business_name' => 'Morgan Studio Ltd',
            'service_topic' => 'trademark_application',
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_time' => '10_12',
            'message' => 'I would like to discuss a new UK trade mark application.',
            'website' => '',
        ];
    }
}
