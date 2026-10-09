<?php

namespace Tests\Feature;

use App\Mail\EventNotification;
use App\Mail\AdminWorkflowNotification;
use App\Models\Admin;
use App\Models\Application;
use App\Models\Document;
use App\Models\Payment;
use App\Models\User;
use App\Services\TrademarkWorkflowService;
use App\Support\TrademarkWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTrademarkReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_workflow_email_template_renders_the_action_details(): void
    {
        $mail = new AdminWorkflowNotification(
            'Client action submitted',
            "Application #15\nBrand: Example Mark",
            'https://example.test/admin/applications/15'
        );

        $html = $mail->render();

        $this->assertStringContainsString('Dear Admin', $html);
        $this->assertStringContainsString('Example Mark', $html);
        $this->assertStringContainsString('Review Application', $html);
    }

    public function test_admin_can_move_a_submitted_application_into_review(): void
    {
        Mail::fake();
        config(['queue.default' => 'sync']);

        $admin = Admin::create([
            'name' => 'Review Admin',
            'email' => 'review-admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $client = User::factory()->create();
        $application = Application::create([
            'user_id' => $client->id,
            'type' => 'trademark',
            'entity_type' => 'individual',
            'applicant_name' => $client->name,
            'phone' => '9876543210',
            'email' => $client->email,
            'brand_name' => 'Review Queue Mark',
            'status' => TrademarkWorkflow::APPLICATION_SUBMITTED,
            'service_status' => TrademarkWorkflow::APPLICATION_SUBMITTED,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.view-application', $application->id))
            ->assertOk()
            ->assertSee('Start Admin Review')
            ->assertSee(route('admin.start-review', $application->id), false);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.start-review', $application->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $application->refresh();

        $this->assertSame(TrademarkWorkflow::UNDER_REVIEW, $application->status);
        $this->assertSame(TrademarkWorkflow::UNDER_REVIEW, $application->service_status);
        $this->assertDatabaseHas('application_status_logs', [
            'application_id' => $application->id,
            'from_status' => TrademarkWorkflow::APPLICATION_SUBMITTED,
            'to_status' => TrademarkWorkflow::UNDER_REVIEW,
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'under_review',
            'title' => 'Application under review',
        ]);
        Mail::assertSent(EventNotification::class, function (EventNotification $mail) use ($client): bool {
            return $mail->hasTo($client->email)
                && $mail->title === 'Application under review';
        });

        $this->actingAs($admin, 'admin')
            ->get(route('admin.view-application', $application->id))
            ->assertOk()
            ->assertDontSee('Start Admin Review')
            ->assertSee('Review Note')
            ->assertSee('Send Engagement Letter for Signing');
    }

    public function test_admin_registry_status_submission_emails_the_specific_update_to_the_client(): void
    {
        Mail::fake();
        config(['queue.default' => 'sync']);

        $admin = Admin::create([
            'name' => 'Registry Admin',
            'email' => 'registry-admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $client = User::factory()->create();
        $application = Application::create([
            'user_id' => $client->id,
            'type' => 'trademark',
            'entity_type' => 'company',
            'applicant_name' => 'Registry Update Limited',
            'phone' => '+447123456789',
            'email' => $client->email,
            'brand_name' => 'Registry Update Mark',
            'status' => TrademarkWorkflow::POST_FILING,
            'service_status' => TrademarkWorkflow::POST_FILING,
        ]);

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.update-status', $application->id), [
                'trademark_status' => 'Accepted and advertised',
                'status' => TrademarkWorkflow::REGISTRY_ACCEPTED,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'registry_status_updated',
        ]);
        Mail::assertSent(EventNotification::class, function (EventNotification $mail) use ($client): bool {
            return $mail->hasTo($client->email)
                && $mail->title === 'Trade mark status updated: Accepted and advertised'
                && str_contains($mail->notificationMessage, 'Registry Update Mark');
        });
    }

    public function test_client_workflow_action_emails_the_admin_team(): void
    {
        Mail::fake();
        config(['queue.default' => 'sync']);

        $admin = Admin::create([
            'name' => 'Workflow Mail Admin',
            'email' => 'workflow-mail-admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $client = User::factory()->create();
        $application = Application::create([
            'user_id' => $client->id,
            'type' => 'trademark',
            'entity_type' => 'individual',
            'applicant_name' => $client->name,
            'phone' => '+447123456789',
            'email' => $client->email,
            'brand_name' => 'Client Action Mark',
            'status' => TrademarkWorkflow::ONBOARDING_PENDING,
            'service_status' => TrademarkWorkflow::ONBOARDING_PENDING,
        ]);

        $this->actingAs($client)
            ->post(route('workflow.task.complete', [
                'id' => $application->id,
                'taskCode' => 'client_confirmation',
            ]))
            ->assertRedirect(route('trademark.status', $application->id));

        Mail::assertSent(AdminWorkflowNotification::class, function (AdminWorkflowNotification $mail) use ($admin, $application): bool {
            return $mail->hasTo($admin->email)
                && str_contains($mail->title, 'Applicant completed a workflow task')
                && str_contains($mail->notificationMessage, $application->brand_name);
        });
    }

    public function test_bulk_document_reupload_submission_emails_the_required_client_action(): void
    {
        Mail::fake();
        config(['queue.default' => 'sync']);

        $admin = Admin::create([
            'name' => 'Document Admin',
            'email' => 'document-admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $client = User::factory()->create();
        $application = Application::create([
            'user_id' => $client->id,
            'type' => 'trademark',
            'entity_type' => 'company',
            'applicant_name' => 'Document Review Limited',
            'phone' => '+447123456789',
            'email' => $client->email,
            'brand_name' => 'Document Review Mark',
            'status' => TrademarkWorkflow::ONBOARDING_PENDING,
            'service_status' => TrademarkWorkflow::ONBOARDING_PENDING,
        ]);
        $document = Document::create([
            'application_id' => $application->id,
            'user_id' => $client->id,
            'document_type' => 'engagement_letter (Signed)',
            'file_path' => 'workflow/signed/engagement-letter.pdf',
            'file_name' => 'engagement-letter.pdf',
            'file_type' => 'pdf',
            'file_size' => 1024,
            'status' => 'uploaded',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.documents.review', $application->id), [
                'document_ids' => [$document->id],
                'action' => 'reupload_requested',
                'note' => 'Please sign the final page and upload it again.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(EventNotification::class, function (EventNotification $mail) use ($client): bool {
            return $mail->hasTo($client->email)
                && $mail->title === 'Action required: reupload application documents'
                && str_contains($mail->notificationMessage, 'Please sign the final page');
        });
    }

    public function test_signed_engagement_letter_shows_review_action_instead_of_signing_pending(): void
    {
        $admin = Admin::create([
            'name' => 'Signing Review Admin',
            'email' => 'signing-review-admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $client = User::factory()->create();
        $application = Application::create([
            'user_id' => $client->id,
            'type' => 'trademark',
            'entity_type' => 'company',
            'applicant_name' => 'Signed Letter Limited',
            'phone' => '+447123456789',
            'email' => $client->email,
            'brand_name' => 'Signed Letter Mark',
            'status' => TrademarkWorkflow::ONBOARDING_PENDING,
            'service_status' => TrademarkWorkflow::ONBOARDING_PENDING,
        ]);
        Document::create([
            'application_id' => $application->id,
            'user_id' => $client->id,
            'document_type' => 'engagement_letter (Signed)',
            'file_path' => 'workflow/signed/engagement-letter.pdf',
            'file_name' => 'engagement-letter.pdf',
            'file_type' => 'pdf',
            'file_size' => 1024,
            'status' => 'uploaded',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.view-application', $application->id))
            ->assertOk()
            ->assertSee('Signed Engagement Letter received')
            ->assertSee('Awaiting admin verification')
            ->assertSee('data-scroll-to-documents', false)
            ->assertSee('href="#submitted-documents"', false)
            ->assertDontSee('Engagement Letter signing pending');
    }

    public function test_start_review_cannot_change_an_application_in_another_stage(): void
    {
        Mail::fake();

        $admin = Admin::create([
            'name' => 'Review Admin',
            'email' => 'second-review-admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $client = User::factory()->create();
        $application = Application::create([
            'user_id' => $client->id,
            'type' => 'trademark',
            'entity_type' => 'individual',
            'applicant_name' => $client->name,
            'phone' => '9876543210',
            'email' => $client->email,
            'brand_name' => 'Unchanged Mark',
            'status' => TrademarkWorkflow::ONBOARDING_PENDING,
            'service_status' => TrademarkWorkflow::ONBOARDING_PENDING,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.start-review', $application->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(TrademarkWorkflow::ONBOARDING_PENDING, $application->fresh()->current_status);
    }

    public function test_completing_review_starts_engagement_letter_signing_without_poa_or_affidavit(): void
    {
        Mail::fake();
        Storage::fake('public');
        $admin = Admin::create([
            'name' => 'UK Review Admin',
            'email' => 'uk-review-admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $client = User::factory()->create();
        $application = Application::create([
            'user_id' => $client->id,
            'type' => 'trademark',
            'entity_type' => 'company',
            'applicant_name' => 'UK Brand Limited',
            'phone' => '+447123456789',
            'email' => $client->email,
            'brand_name' => 'UK Review Mark',
            'status' => TrademarkWorkflow::UNDER_REVIEW,
            'service_status' => TrademarkWorkflow::UNDER_REVIEW,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.approve', $application->id), [
                'notes' => 'Initial UK review complete.',
                'engagement_letter_file' => UploadedFile::fake()->create('engagement-letter.pdf', 32, 'application/pdf'),
                'other_document_files' => [
                    UploadedFile::fake()->create('filing-guidance.pdf', 24, 'application/pdf'),
                ],
                'signature_fields' => [
                    'engagement_letter' => [
                        'signature' => [
                            'enabled' => '1',
                            'page' => 1,
                            'x' => 20,
                            'y' => 220,
                            'width' => 60,
                            'height' => 20,
                        ],
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $application->refresh();
        $this->assertSame(TrademarkWorkflow::ONBOARDING_PENDING, $application->service_status);
        $this->assertDatabaseHas('documents', [
            'application_id' => $application->id,
            'document_type' => 'engagement_letter',
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('documents', [
            'application_id' => $application->id,
            'document_type' => 'other_document',
            'status' => 'approved',
        ]);
        $this->assertDatabaseMissing('documents', ['application_id' => $application->id, 'document_type' => 'poa']);
        $this->assertDatabaseMissing('documents', ['application_id' => $application->id, 'document_type' => 'affidavit']);
    }

    public function test_final_payment_marks_application_ready_to_file_without_recording_a_filing(): void
    {
        Mail::fake();
        $client = User::factory()->create();
        $application = Application::create([
            'user_id' => $client->id,
            'type' => 'trademark',
            'entity_type' => 'company',
            'applicant_name' => 'Ready Brand Limited',
            'phone' => '+447123456789',
            'email' => $client->email,
            'brand_name' => 'Ready Mark',
            'status' => TrademarkWorkflow::APPROVED_BY_CLIENT,
            'service_status' => TrademarkWorkflow::APPROVED_BY_CLIENT,
        ]);
        Payment::create([
            'application_id' => $application->id,
            'user_id' => $client->id,
            'amount' => 199.50,
            'total_amount' => 399,
            'payment_type' => 'final',
            'percentage' => '50%',
            'payment_method' => 'razorpay',
            'status' => 'completed',
            'reference_number' => 'order_verified_final_test',
            'transaction_id' => 'pay_verified_final_test',
            'paid_at' => now(),
        ]);

        app(TrademarkWorkflowService::class)->markFinalPaymentComplete($application);

        $application->refresh();
        $this->assertSame(TrademarkWorkflow::READY_TO_FILE, $application->service_status);
        $this->assertNull($application->filed_at);
        $this->assertNull($application->application_number);
    }

    public function test_admin_uk_status_list_contains_the_sixteen_client_statuses(): void
    {
        $this->assertCount(16, TrademarkWorkflow::statusOptions());
        $this->assertSame('Filed with UKIPO', TrademarkWorkflow::statusOptions()[TrademarkWorkflow::FILED_WITH_UKIPO]);
        $this->assertSame('Withdrawn or Closed', TrademarkWorkflow::statusOptions()[TrademarkWorkflow::WITHDRAWN_OR_CLOSED]);
    }

    public function test_admin_can_edit_matter_overview_with_the_same_applicant_type_dropdown_as_the_client(): void
    {
        $admin = Admin::create([
            'name' => 'Matter Admin',
            'email' => 'matter-admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $client = User::factory()->create();
        $application = Application::create([
            'user_id' => $client->id,
            'type' => 'trademark',
            'entity_type' => 'individual',
            'applicant_name' => 'Original Applicant',
            'phone' => '+447123456789',
            'email' => 'original@example.test',
            'brand_name' => 'Original Mark',
            'description' => 'Original activities',
            'goods_services' => 'Original activities',
            'status' => TrademarkWorkflow::UNDER_REVIEW,
            'service_status' => TrademarkWorkflow::UNDER_REVIEW,
            'members_details' => [
                'applicant_details' => [
                    'applicant_type' => 'individual',
                    'legal_name' => 'Original Applicant',
                    'email' => 'original@example.test',
                    'phone' => '+447123456789',
                    'postcode' => 'SW1A 1AA',
                ],
                'trademark_details' => [
                    'trade_mark_wording' => 'Original Mark',
                    'business_activities' => 'Original activities',
                    'mark_type' => 'word',
                ],
            ],
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.view-application', $application))
            ->assertOk()
            ->assertSee('name="applicant_type"', false)
            ->assertSee('<option value="limited_company"', false)
            ->assertSee('<option value="charity"', false)
            ->assertSee('name="business_activities"', false)
            ->assertSee('data-matter-overview-fields disabled', false)
            ->assertSee('data-matter-overview-toggle', false)
            ->assertSee('data-matter-overview-cancel', false)
            ->assertSee('data-matter-overview-label>Edit</span>', false)
            ->assertSee("window.confirm('Save these changes to the Matter Overview?')", false)
            ->assertDontSee('Save Matter Overview')
            ->assertSee(route('admin.application.matter-overview.update', $application), false);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.application.matter-overview.update', $application), [
                'brand_name' => 'Updated Mark',
                'applicant_name' => 'Updated Charity',
                'applicant_type' => 'charity',
                'email' => 'updated@example.test',
                'phone' => '07123 456789',
                'business_activities' => 'Updated education and community services.',
            ])
            ->assertRedirect(route('admin.view-application', $application))
            ->assertSessionHas('success');

        $application->refresh();

        $this->assertSame('Updated Mark', $application->brand_name);
        $this->assertSame('Updated Charity', $application->applicant_name);
        $this->assertSame('company', $application->entity_type);
        $this->assertSame('updated@example.test', $application->email);
        $this->assertSame('+447123456789', $application->phone);
        $this->assertSame('Updated education and community services.', $application->description);
        $this->assertSame('Updated education and community services.', $application->goods_services);
        $this->assertSame('charity', data_get($application->members_details, 'applicant_details.applicant_type'));
        $this->assertSame('Updated Charity', data_get($application->members_details, 'applicant_details.legal_name'));
        $this->assertSame('SW1A 1AA', data_get($application->members_details, 'applicant_details.postcode'));
        $this->assertSame('Updated Mark', data_get($application->members_details, 'trademark_details.trade_mark_wording'));
        $this->assertSame('word', data_get($application->members_details, 'trademark_details.mark_type'));

        $this->actingAs($admin, 'admin')
            ->from(route('admin.view-application', $application))
            ->put(route('admin.application.matter-overview.update', $application), [
                'brand_name' => 'Invalid Mark',
                'applicant_name' => 'Invalid Applicant',
                'applicant_type' => 'unsupported_type',
                'email' => 'invalid@example.test',
                'phone' => '+447123456789',
                'business_activities' => 'Invalid update should not be persisted.',
            ])
            ->assertRedirect(route('admin.view-application', $application))
            ->assertSessionHasErrors('applicant_type');

        $this->assertSame('Updated Mark', $application->fresh()->brand_name);
    }

    public function test_admin_review_shows_complete_submission_and_application_payment_proof_with_invoice(): void
    {
        $admin = Admin::create([
            'name' => 'Payment Admin',
            'email' => 'payment-admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $client = User::factory()->create();
        $application = Application::create([
            'user_id' => $client->id,
            'type' => 'trademark',
            'entity_type' => 'company',
            'applicant_name' => 'Complete Brand Limited',
            'phone' => '+447123456789',
            'email' => 'owner@complete-brand.test',
            'brand_name' => 'Complete Brand Mark',
            'status' => TrademarkWorkflow::UNDER_REVIEW,
            'service_status' => TrademarkWorkflow::UNDER_REVIEW,
            'members_details' => [
                'billing_details' => [
                    'billing_name' => 'Complete Brand Accounts',
                    'billing_email' => 'billing@complete-brand.test',
                    'billing_phone' => '+447234567890',
                    'billing_address' => '10 Billing Road, London',
                ],
                'applicant_details' => [
                    'applicant_type' => 'limited_company',
                    'legal_name' => 'Complete Brand Limited',
                    'company_registration_number' => '12345678',
                    'company_registration_country' => 'United Kingdom',
                    'address_line_1' => '20 Applicant Street',
                    'address_line_2' => 'Suite 4',
                    'town_city' => 'Bromley',
                    'county_or_region' => 'London',
                    'nation' => 'England',
                    'postcode' => 'BR6 6BJ',
                    'country' => 'United Kingdom',
                    'email' => 'owner@complete-brand.test',
                    'phone' => '+447123456789',
                    'uk_address_for_service' => '20 Applicant Street, Bromley, BR6 6BJ',
                ],
                'authorised_person' => [
                    'full_name' => 'Alex Director',
                    'position_or_capacity' => 'Director',
                    'email' => 'alex@complete-brand.test',
                    'phone' => '+447345678901',
                    'authority_confirmed' => true,
                ],
                'joint_applicants' => true,
                'additional_applicants' => [[
                    'applicant_type' => 'individual',
                    'legal_name' => 'Jordan Co-owner',
                    'address' => '30 Joint Road, London',
                    'email' => 'jordan@example.test',
                    'phone' => '+447456789012',
                ]],
                'trademark_details' => [
                    'mark_type' => 'combined',
                    'trade_mark_wording' => 'Complete Brand Mark',
                    'language' => 'Welsh',
                    'translation' => 'Complete Brand',
                    'business_activities' => 'Technology consultancy and software services',
                    'currently_in_use' => true,
                    'first_use_date' => '2025-01-15',
                    'proposed_classes' => '9, 42',
                    'special_limitations' => 'No colour claim',
                    'application_route' => 'standard',
                ],
                'priority_details' => [
                    'priority_claim_required' => true,
                    'priority_country' => 'Ireland',
                    'priority_application_number' => 'IE-12345',
                    'priority_filing_date' => '2026-05-01',
                    'earlier_applicant' => 'Complete Brand Limited',
                ],
            ],
        ]);
        $payment = Payment::create([
            'application_id' => $application->id,
            'user_id' => $client->id,
            'amount' => 199.50,
            'total_amount' => 399,
            'payment_type' => 'advance',
            'percentage' => '50%',
            'transaction_id' => 'pay_admin_proof_123',
            'payment_method' => 'razorpay',
            'status' => 'completed',
            'paid_at' => now(),
            'reference_number' => 'order_admin_proof_123',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.view-application', $application->id));

        $response->assertOk()
            ->assertSee('Complete Brand Accounts')
            ->assertSee('12345678')
            ->assertSee('Suite 4')
            ->assertSee('Alex Director')
            ->assertSee('Jordan Co-owner')
            ->assertSee('Welsh')
            ->assertSee('Technology consultancy and software services')
            ->assertSee('IE-12345')
            ->assertSee('Payment Proof &amp; Invoices', false)
            ->assertSee('class="payment-total-badge">Total paid: £199.50', false)
            ->assertSee('pay_admin_proof_123')
            ->assertSee('order_admin_proof_123')
            ->assertSee('£199.50')
            ->assertSee(route('admin.payment.invoice', $payment), false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payment.invoice', $payment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_admin_payment_invoice_requires_admin_authentication(): void
    {
        $client = User::factory()->create();
        $application = Application::create([
            'user_id' => $client->id,
            'type' => 'trademark',
            'entity_type' => 'individual',
            'applicant_name' => $client->name,
            'phone' => '+447123456789',
            'email' => $client->email,
            'brand_name' => 'Protected Invoice Mark',
        ]);
        $payment = Payment::create([
            'application_id' => $application->id,
            'user_id' => $client->id,
            'amount' => 199.50,
            'total_amount' => 399,
            'payment_type' => 'advance',
            'percentage' => '50%',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $this->get(route('admin.payment.invoice', $payment))
            ->assertRedirect(route('admin.login'));
    }
}
