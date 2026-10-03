<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use App\Support\TrademarkWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
