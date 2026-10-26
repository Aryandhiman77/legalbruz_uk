<?php

namespace Tests\Feature;

use App\Mail\AdminWorkflowNotification;
use App\Mail\EventNotification;
use App\Models\Admin;
use App\Models\ExaminationReportReplyCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExaminationReplyUkTest extends TestCase
{
    use RefreshDatabase;

    public function test_examination_reply_uses_revolut_hosted_checkout_without_exposing_customer_phone_data(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/ExaminationReportReplyController.php'));

        $this->assertStringContainsString('RevolutMerchantService $revolut', $source);
        $this->assertStringNotContainsString('Auth::user()->phone', $source);
    }

    public function test_new_case_uses_the_standard_two_month_ukipo_response_period(): void
    {
        Storage::fake('public');

        $response = $this->actingAs(User::factory()->create())->post(route('examination-reply.store'), [
            'applicant_name' => 'Example Applicant',
            'trademark_name' => 'Example Mark',
            'application_number' => 'UK00004123456',
            'trademark_class' => '35',
            'application_filing_date' => '2026-01-10',
            'current_status' => 'Objected',
            'filed_through_legalbruz' => '1',
            'exam_report' => UploadedFile::fake()->create('examination-report.pdf', 100, 'application/pdf'),
            'exam_report_receipt_date' => '2026-01-31',
        ]);

        $case = ExaminationReportReplyCase::query()->firstOrFail();

        $response->assertRedirect(route('examination-reply.show', $case));
        $this->assertSame('2026-03-31', $case->reply_deadline->toDateString());
    }

    public function test_client_action_center_submission_emails_every_admin(): void
    {
        Storage::fake('public');
        Mail::fake();

        $client = User::factory()->create();
        $admins = collect([
            Admin::create(['name' => 'First Admin', 'email' => 'first-admin@example.test', 'password' => bcrypt('password')]),
            Admin::create(['name' => 'Second Admin', 'email' => 'second-admin@example.test', 'password' => bcrypt('password')]),
        ]);

        $this->actingAs($client)->post(route('examination-reply.store'), [
            'applicant_name' => 'Action Centre Applicant',
            'trademark_name' => 'Action Centre Mark',
            'application_number' => 'UK00004123457',
            'trademark_class' => '35',
            'application_filing_date' => '2026-01-10',
            'current_status' => 'Objected',
            'filed_through_legalbruz' => '1',
            'exam_report' => UploadedFile::fake()->create('examination-report.pdf', 100, 'application/pdf'),
            'exam_report_receipt_date' => '2026-01-31',
        ])->assertRedirect();

        foreach ($admins as $admin) {
            Mail::assertSent(AdminWorkflowNotification::class, fn (AdminWorkflowNotification $mail): bool => $mail->hasTo($admin->email));
        }
    }

    public function test_admin_stage_action_emails_the_client_when_the_action_has_no_specific_email(): void
    {
        Storage::fake('public');

        $client = User::factory()->create(['email' => 'stage-client@example.test']);
        $admin = Admin::create([
            'name' => 'Stage Admin',
            'email' => 'stage-admin@example.test',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($client)->post(route('examination-reply.store'), [
            'applicant_name' => 'Stage Applicant',
            'trademark_name' => 'Stage Mark',
            'application_number' => 'UK00004123458',
            'trademark_class' => '35',
            'application_filing_date' => '2026-01-10',
            'current_status' => 'Objected',
            'filed_through_legalbruz' => '1',
            'exam_report' => UploadedFile::fake()->create('examination-report.pdf', 100, 'application/pdf'),
            'exam_report_receipt_date' => '2026-01-31',
        ])->assertRedirect();

        $case = ExaminationReportReplyCase::query()->firstOrFail();
        Mail::fake();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.examination-reply.start-review', $case), [
                'document_action' => 'start_review',
                'tracking_note' => 'Review started after checking the examination report.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(EventNotification::class, function (EventNotification $mail) use ($client): bool {
            return $mail->hasTo($client->email)
                && $mail->title === 'Your matter has been updated'
                && str_contains($mail->notificationMessage, 'Start Review');
        });
    }
}
