<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use App\Support\PostFilingJourney;
use App\Support\TrademarkOppositionWorkflow;
use App\Support\TrademarkWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrademarkOppositionJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_opposition_is_labelled_opposed_on_the_client_dashboard(): void
    {
        $client = User::factory()->create();
        $application = $this->postFilingApplication($client, PostFilingJourney::OPPOSED);

        $this->assertSame('Opposed', $application->fresh()->status_label);

        $this->actingAs($client)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Opposed');

        $this->actingAs($client)
            ->get(route('trademark.status', $application->id))
            ->assertOk()
            ->assertSee('post-filing-status-badge opposed opposed">Opposed', false);
    }

    public function test_completed_stage_after_opposition_is_shown_as_victory_in_the_journey(): void
    {
        $client = User::factory()->create();
        $application = $this->postFilingApplication($client, PostFilingJourney::COMPLETED);

        $this->actingAs($client)
            ->get(route('trademark.status', $application->id))
            ->assertOk()
            ->assertSee('post-filing-status-badge completed case-victory">Victory', false)
            ->assertSee('Opposition Details')
            ->assertSee('data-opposition-outcome="victory"', false);
    }

    public function test_lost_opposition_remains_visible_after_the_journey_continues(): void
    {
        $client = User::factory()->create();
        $application = $this->postFilingApplication($client, PostFilingJourney::COMPLETED);
        $application->update([
            'final_opposition_result' => TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_OPPOSITION_SUCCESSFUL,
        ]);

        $this->actingAs($client)
            ->get(route('trademark.status', $application->id))
            ->assertOk()
            ->assertSee('post-filing-status-badge completed case-lost">Lost', false)
            ->assertSee('Opposition Details')
            ->assertSee('data-opposition-outcome="lost"', false);
    }

    private function postFilingApplication(User $client, string $oppositionStageStatus): Application
    {
        return Application::create([
            'user_id' => $client->id,
            'type' => 'trademark',
            'entity_type' => 'company',
            'applicant_name' => 'Opposition Test Limited',
            'phone' => '+447123456789',
            'email' => $client->email,
            'brand_name' => 'Opposition Journey Mark',
            'status' => TrademarkWorkflow::POST_FILING,
            'service_status' => TrademarkWorkflow::POST_FILING,
            'application_number' => 'UK00004123456',
            'registry_status' => TrademarkWorkflow::REGISTRY_OPPOSITION,
            'workflow_meta' => [
                'post_filing_journey' => [
                    'stages' => [
                        'accepted_advertised' => [
                            'status' => $oppositionStageStatus,
                            'opposed_at' => now()->subDay()->toDateTimeString(),
                            'completed_at' => $oppositionStageStatus === PostFilingJourney::COMPLETED
                                ? now()->toDateTimeString()
                                : null,
                        ],
                    ],
                ],
            ],
        ]);
    }
}
