<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Application;
use App\Models\Document;
use App\Models\User;
use App\Support\TrademarkWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminClientPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_read_only_client_dashboard_and_action_center_from_stage_actions(): void
    {
        $admin = Admin::create([
            'name' => 'Preview Admin',
            'email' => 'preview-admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $client = User::factory()->create([
            'name' => 'Current Client',
            'email' => 'current-client@example.com',
        ]);
        $application = Application::create([
            'user_id' => $client->id,
            'type' => 'trademark',
            'entity_type' => 'individual',
            'applicant_name' => 'Current Client',
            'phone' => '9876543210',
            'email' => $client->email,
            'brand_name' => 'Preview Mark',
            'status' => 'pending_admin',
            'service_status' => TrademarkWorkflow::STRATEGY_IN_PROGRESS,
        ]);
        $originalUpdatedAt = $application->updated_at;

        $this->actingAs($admin, 'admin')
            ->get(route('admin.view-application', $application->id))
            ->assertOk()
            ->assertSee('View Client Dashboard')
            ->assertSee('View Client Action Center')
            ->assertSee(route('admin.application.client-dashboard', $application->id), false)
            ->assertSee(route('admin.application.client-action-center', $application->id), false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.application.client-dashboard', $application->id))
            ->assertOk()
            ->assertSee('Read-only client preview')
            ->assertSee('Welcome, Current Client!')
            ->assertSee('Preview Mark')
            ->assertSee(route('admin.application.client-action-center', $application->id), false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.application.client-action-center', [
                'id' => $application->id,
                'stage_action' => 1,
            ]))
            ->assertOk()
            ->assertSee('Read-only client action center')
            ->assertSee('Inputs and client actions are disabled')
            ->assertSee('Search in Progress')
            ->assertDontSee('Power of Attorney');

        $application->refresh();

        $this->assertTrue($application->updated_at->equalTo($originalUpdatedAt));
        $this->assertSame(TrademarkWorkflow::STRATEGY_IN_PROGRESS, $application->service_status);
    }

    public function test_client_preview_routes_require_admin_authentication(): void
    {
        $client = User::factory()->create();
        $application = Application::create([
            'user_id' => $client->id,
            'type' => 'trademark',
            'entity_type' => 'individual',
            'applicant_name' => $client->name,
            'phone' => '9876543210',
            'email' => $client->email,
            'brand_name' => 'Protected Preview Mark',
            'status' => 'pending_admin',
            'service_status' => TrademarkWorkflow::UNDER_REVIEW,
        ]);

        $this->get(route('admin.application.client-dashboard', $application->id))
            ->assertRedirect(route('admin.login'));

        $this->get(route('admin.application.client-action-center', $application->id))
            ->assertRedirect(route('admin.login'));
    }

    public function test_the_existing_client_action_center_remains_interactive(): void
    {
        $client = User::factory()->create([
            'name' => 'Signed In Client',
            'email' => 'signed-in-client@example.com',
        ]);
        $application = Application::create([
            'user_id' => $client->id,
            'type' => 'trademark',
            'entity_type' => 'individual',
            'applicant_name' => $client->name,
            'phone' => '9876543210',
            'email' => $client->email,
            'brand_name' => 'Interactive Mark',
            'status' => 'approved',
            'service_status' => TrademarkWorkflow::STRATEGY_IN_PROGRESS,
        ]);

        $this->actingAs($client)
            ->get(route('trademark.status', [
                'id' => $application->id,
                'stage_action' => 1,
            ]))
            ->assertOk()
            ->assertDontSee('Read-only client action center')
            ->assertSee('Search and specification review is active.')
            ->assertDontSee('Power of Attorney')
            ->assertDontSee('Affidavit');
    }

    public function test_client_can_see_the_engagement_letter_signing_action_without_poa_or_affidavit(): void
    {
        $client = User::factory()->create();
        $application = Application::create([
            'user_id' => $client->id,
            'type' => 'trademark',
            'entity_type' => 'individual',
            'applicant_name' => $client->name,
            'phone' => '+447123456789',
            'email' => $client->email,
            'brand_name' => 'Signing Flow Mark',
            'status' => TrademarkWorkflow::ONBOARDING_PENDING,
            'service_status' => TrademarkWorkflow::ONBOARDING_PENDING,
            'workflow_meta' => [
                'signature_fields' => [
                    'engagement_letter' => [[
                        'type' => 'signature',
                        'page' => 1,
                        'x' => 20,
                        'y' => 220,
                        'width' => 60,
                        'height' => 20,
                    ]],
                ],
            ],
        ]);
        Document::create([
            'application_id' => $application->id,
            'user_id' => $client->id,
            'document_type' => 'engagement_letter',
            'file_path' => 'workflow/admin/'.$application->id.'/engagement-letter.pdf',
            'file_name' => 'engagement-letter.pdf',
            'file_type' => 'pdf',
            'file_size' => 1024,
            'status' => 'approved',
        ]);
        Document::create([
            'application_id' => $application->id,
            'user_id' => $client->id,
            'document_type' => 'other_document',
            'file_path' => 'workflow/admin/'.$application->id.'/filing-guidance.pdf',
            'file_name' => 'filing-guidance.pdf',
            'file_type' => 'pdf',
            'file_size' => 1024,
            'status' => 'approved',
            'verification_notes' => 'Document sent by admin.',
        ]);

        $this->actingAs($client)
            ->get(route('trademark.status', ['id' => $application->id, 'stage_action' => 1]))
            ->assertOk()
            ->assertSee('Engagement Letter Signature')
            ->assertSee('E-Sign')
            ->assertSee('action="'.route('workflow.onboarding.submit', $application->id).'"', false)
            ->assertSee('filing-guidance.pdf')
            ->assertDontSee('Signed POA')
            ->assertDontSee('Signed Affidavit');
    }
}
