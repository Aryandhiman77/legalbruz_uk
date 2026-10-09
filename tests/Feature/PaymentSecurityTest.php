<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\StuckTrademarkCase;
use App\Models\User;
use App\Support\StuckTrademarkWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_activate_paid_execution_without_a_verified_payment(): void
    {
        config(['uk_site.legacy_services_enabled' => true]);

        $admin = Admin::query()->create([
            'name' => 'Payment Security Admin',
            'email' => 'payments@example.test',
            'password' => bcrypt('password'),
        ]);
        $user = User::factory()->create();
        $case = StuckTrademarkCase::query()->create([
            'case_number' => 'STR-SECURE-1',
            'user_id' => $user->id,
            'applicant_name' => $user->name,
            'email' => $user->email,
            'phone' => '+447123456789',
            'trademark_name' => 'Secure Mark',
            'problem_summary' => 'A recovery case used to verify payment access control.',
            'status' => StuckTrademarkWorkflow::EXECUTION_PAYMENT_PENDING,
            'audit_payment_status' => 'paid',
            'execution_payment_status' => 'pending',
            'execution_fee' => 500,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.stuck-trademark.update', $case), [
                'status' => StuckTrademarkWorkflow::EXECUTION_ACTIVE,
            ])
            ->assertRedirect(route('admin.stuck-trademark.show', $case))
            ->assertSessionHas('error');

        $case->refresh();
        $this->assertSame(StuckTrademarkWorkflow::EXECUTION_PAYMENT_PENDING, $case->status);
        $this->assertSame('pending', $case->execution_payment_status);
        $this->assertNull($case->execution_paid_at);
    }
}
