<?php

namespace Tests\Feature;

use App\Models\ExaminationReportReplyCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
}
