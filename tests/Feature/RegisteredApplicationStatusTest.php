<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use App\Support\PostFilingJourney;
use App\Support\TrademarkWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisteredApplicationStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_application_is_not_labelled_as_examination(): void
    {
        $client = User::factory()->create();
        $application = Application::create([
            'user_id' => $client->id,
            'type' => 'trademark',
            'entity_type' => 'individual',
            'applicant_name' => $client->name,
            'phone' => '+447123456789',
            'email' => $client->email,
            'brand_name' => 'GAINERZ',
            'application_number' => 'UK123456',
            'status' => TrademarkWorkflow::POST_FILING,
            'service_status' => TrademarkWorkflow::POST_FILING,
            'registry_status' => TrademarkWorkflow::REGISTRY_REGISTERED,
            'registered_at' => now(),
        ]);

        $completedStages = collect(PostFilingJourney::stages($application))
            ->mapWithKeys(fn (array $stage) => [
                $stage['key'] => [
                    'status' => PostFilingJourney::COMPLETED,
                    'completed_at' => now()->toDateTimeString(),
                ],
            ])
            ->all();

        $application->update([
            'workflow_meta' => [
                'post_filing_journey' => ['stages' => $completedStages],
            ],
        ]);
        $application->refresh();

        $this->assertSame(TrademarkWorkflow::POST_FILING, $application->current_status);
        $this->assertTrue($application->is_registered);
        $this->assertSame('Registered', $application->status_label);

        $this->actingAs($client)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('dashboard-badge status-green', false)
            ->assertSee('Registered');

        $this->actingAs($client)
            ->get(route('trademark.status', $application->id))
            ->assertOk()
            ->assertSee('<span>Registered</span>', false)
            ->assertSee('Trademark registered successfully.');
    }
}
