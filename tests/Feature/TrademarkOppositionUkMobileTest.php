<?php

namespace Tests\Feature;

use App\Models\TrademarkOppositionCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrademarkOppositionUkMobileTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_opposition_forms_request_uk_mobile_numbers(): void
    {
        $user = User::factory()->create();

        foreach ([route('trademark-opposition.create'), route('trademark-opposition.oppose.create')] as $url) {
            $this->actingAs($user)
                ->get($url)
                ->assertOk()
                ->assertSee('type="tel"', false)
                ->assertSee('placeholder="07123 456789"', false)
                ->assertSee('UK mobile numbers only: use 07 or +44 7.');
        }
    }

    public function test_legacy_non_uk_account_mobile_is_not_prefilled(): void
    {
        $user = User::factory()->create(['mobile' => '+919876543210']);

        foreach ([route('trademark-opposition.create'), route('trademark-opposition.oppose.create')] as $url) {
            $this->actingAs($user)
                ->get($url)
                ->assertOk()
                ->assertDontSee('value="+919876543210"', false);
        }
    }

    public function test_both_opposition_flows_reject_non_uk_mobile_numbers(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('trademark-opposition.create'))
            ->post(route('trademark-opposition.store'), $this->defenceData('+919876543210'))
            ->assertRedirect(route('trademark-opposition.create'))
            ->assertSessionHasErrors('mobile_number');

        $this->actingAs($user)
            ->from(route('trademark-opposition.oppose.create'))
            ->post(route('trademark-opposition.oppose.store'), $this->opposeData('+919876543210'))
            ->assertRedirect(route('trademark-opposition.oppose.create'))
            ->assertSessionHasErrors('mobile_number');

        $this->assertDatabaseCount('trademark_opposition_cases', 0);
    }

    public function test_both_opposition_flows_normalize_valid_uk_mobile_numbers(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('trademark-opposition.store'), $this->defenceData('07123 456789'))
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('trademark-opposition.oppose.store'), $this->opposeData('+44 7234 567890'))
            ->assertRedirect();

        $this->assertSame(
            ['+447123456789', '+447234567890'],
            TrademarkOppositionCase::query()->orderBy('id')->pluck('mobile_number')->all(),
        );
    }

    private function defenceData(string $mobile): array
    {
        return [
            'applicant_name' => 'Example Applicant',
            'trademark_name' => 'Example Mark',
            'application_number' => 'UK00004123456',
            'trademark_class' => '35',
            'mobile_number' => $mobile,
            'email' => 'applicant@example.test',
            'notice_receipt_date' => now()->subDay()->toDateString(),
        ];
    }

    private function opposeData(string $mobile): array
    {
        return [
            'trademark_you_own' => 'Existing Mark',
            'trademark_to_oppose' => 'Conflicting Mark',
            'opposed_application_number' => 'UK00004654321',
            'trademark_class' => '35',
            'conflict_reason' => 'The marks are confusingly similar for overlapping services.',
            'opposed_applicant_name' => 'Other Applicant',
            'user_business_name' => 'Example Business Limited',
            'mobile_number' => $mobile,
            'email' => 'owner@example.test',
        ];
    }
}
