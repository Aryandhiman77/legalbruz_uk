<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\TrademarkPricing;
use App\Models\TrademarkSearchReportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TrademarkSearchReportPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_request_uses_dynamic_fee_and_redirects_to_payment(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.trademark-pricing.update'), [
                'prices' => [
                    TrademarkPricing::SEARCH => 179.50,
                    TrademarkPricing::APPLICATION => 399,
                    TrademarkPricing::CONSULTATION => 25,
                ],
            ])
            ->assertRedirect(route('admin.trademark-pricing.edit'));

        $this->get(route('trademark-search-report.create'))
            ->assertOk()
            ->assertSee('£179.50')
            ->assertSee('Continue to payment');

        $response = $this->post(route('trademark-search-report.store'), $this->payload());
        $reportRequest = TrademarkSearchReportRequest::query()->firstOrFail();

        $response->assertRedirect(route('trademark-search-report.payment', $reportRequest));
        $this->assertSame('179.50', $reportRequest->amount);
        $this->assertSame('pending', $reportRequest->payment_status);
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_report_payment_order_and_signature_are_recorded(): void
    {
        config([
            'razorpay.key_id' => 'rzp_test_key',
            'razorpay.key_secret' => 'test_secret',
            'razorpay.currency' => 'GBP',
        ]);
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response(['id' => 'order_search_report_1'], 200),
            'api.razorpay.com/v1/payments/pay_search_report_1' => Http::response([
                'id' => 'pay_search_report_1',
                'order_id' => 'order_search_report_1',
                'amount' => 14900,
                'currency' => 'GBP',
                'status' => 'captured',
                'captured' => true,
                'amount_refunded' => 0,
            ], 200),
        ]);

        $this->post(route('trademark-search-report.store'), $this->payload())->assertRedirect();
        $reportRequest = TrademarkSearchReportRequest::query()->firstOrFail();

        $this->postJson(route('trademark-search-report.payment.order', $reportRequest))
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'order_id' => 'order_search_report_1',
                'amount' => 14900,
                'currency' => 'GBP',
            ]);

        $paymentId = 'pay_search_report_1';
        $signature = hash_hmac('sha256', 'order_search_report_1|'.$paymentId, 'test_secret');

        $this->postJson(route('trademark-search-report.payment.verify', $reportRequest), [
            'razorpay_payment_id' => $paymentId,
            'razorpay_order_id' => 'order_search_report_1',
            'razorpay_signature' => $signature,
        ])->assertOk()
            ->assertJson([
                'status' => 'success',
                'redirect_url' => route('trademark-search-report.success', $reportRequest),
            ]);

        $reportRequest->refresh();
        $this->assertSame('paid', $reportRequest->payment_status);
        $this->assertSame(TrademarkSearchReportRequest::PAYMENT_RECEIVED, $reportRequest->report_status);
        $this->assertSame($paymentId, $reportRequest->transaction_id);
        $this->assertNotNull($reportRequest->paid_at);
        $this->get(route('trademark-search-report.success', $reportRequest))
            ->assertOk()
            ->assertSee('Your trademark search report is confirmed');
    }

    public function test_client_and_admin_can_see_payment_and_report_status(): void
    {
        Storage::fake('local');
        $client = User::factory()->create(['email' => 'alex@example.test']);

        $this->actingAs($client)
            ->post(route('trademark-search-report.store'), $this->payload())
            ->assertRedirect();

        $reportRequest = TrademarkSearchReportRequest::query()->firstOrFail();
        $this->assertSame($client->id, $reportRequest->user_id);

        $this->actingAs($client)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My Trademark Search Reports')
            ->assertSee('North Pine')
            ->assertSee('£149.00')
            ->assertSee('Pending')
            ->assertSee('Awaiting payment')
            ->assertSee(route('trademark-search-report.payment', $reportRequest), false);

        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.trademark-search-reports.index'))
            ->assertOk()
            ->assertSee('Trademark Search Reports')
            ->assertSee('North Pine')
            ->assertSee('Pending')
            ->assertSee('£149.00');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.trademark-search-reports.show', $reportRequest))
            ->assertOk()
            ->assertSee('Payment record')
            ->assertSee('Awaiting payment')
            ->assertSee('Add Document')
            ->assertSee('Document Name')
            ->assertSee('name="document_file"', false)
            ->assertDontSee('name="document_file" multiple', false)
            ->assertSee('Admin Internal Notes');

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.trademark-search-reports.update', $reportRequest), [
                'admin_notes' => 'Search assigned to the review team.',
                'report_status' => TrademarkSearchReportRequest::IN_REVIEW,
            ])
            ->assertRedirect(route('admin.trademark-search-reports.show', $reportRequest));

        $this->actingAs($admin, 'admin')
            ->post(route('admin.trademark-search-reports.document.store', $reportRequest), [
                'document_name' => 'Full Trademark Search Report',
                'document_file' => UploadedFile::fake()->create('north-pine-search-report.pdf', 250, 'application/pdf'),
            ])
            ->assertRedirect(route('admin.trademark-search-reports.show', $reportRequest))
            ->assertSessionHas('success', 'Document added successfully.');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.trademark-search-reports.document.store', $reportRequest), [
                'document_name' => 'Risk Summary',
                'document_file' => UploadedFile::fake()->create('north-pine-risk-summary.pdf', 180, 'application/pdf'),
            ])
            ->assertRedirect(route('admin.trademark-search-reports.show', $reportRequest));

        $reportRequest->refresh();
        $this->assertSame(TrademarkSearchReportRequest::REPORT_READY, $reportRequest->report_status);
        $this->assertCount(2, $reportRequest->documents);
        $this->assertSame([
            'Full Trademark Search Report',
            'Risk Summary',
        ], $reportRequest->documents()->oldest('uploaded_at')->oldest('id')->pluck('document_name')->all());
        foreach ($reportRequest->documents as $document) {
            Storage::disk('local')->assertExists($document->file_path);
        }

        $reportRequest->update([
            'payment_status' => 'paid',
            'transaction_id' => 'pay_client_report_test',
            'paid_at' => now(),
        ]);

        $this->actingAs($client)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Report ready')
            ->assertSee('View Reports (2)')
            ->assertSee(route('trademark-search-report.success', $reportRequest), false);

        $firstDocument = $reportRequest->documents()->oldest('id')->firstOrFail();
        $this->actingAs($client)
            ->get(route('trademark-search-report.success', $reportRequest))
            ->assertOk()
            ->assertSee('Full Trademark Search Report')
            ->assertSee('Risk Summary')
            ->assertDontSee('north-pine-search-report.pdf')
            ->assertDontSee('north-pine-risk-summary.pdf');

        $this->actingAs($client)
            ->get(route('trademark-search-report.download', [$reportRequest, $firstDocument]))
            ->assertOk()
            ->assertDownload('north-pine-search-report.pdf');

        $otherClient = User::factory()->create(['email' => 'someone-else@example.test']);
        $this->actingAs($otherClient, 'web')
            ->get(route('trademark-search-report.download', [$reportRequest, $firstDocument]))
            ->assertForbidden();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.trademark-search-reports.document.download', [$reportRequest, $firstDocument]))
            ->assertOk()
            ->assertDownload('north-pine-search-report.pdf');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.trademark-search-reports.show', $reportRequest))
            ->assertOk()
            ->assertSee('Remove')
            ->assertSee(
                route('admin.trademark-search-reports.document.destroy', [$reportRequest, $firstDocument]),
                false,
            );

        $firstDocumentPath = $firstDocument->file_path;
        $this->actingAs($admin, 'admin')
            ->delete(route('admin.trademark-search-reports.document.destroy', [$reportRequest, $firstDocument]))
            ->assertRedirect(route('admin.trademark-search-reports.show', $reportRequest))
            ->assertSessionHas('success', 'PDF report removed successfully.');

        $this->assertDatabaseMissing('trademark_search_report_documents', [
            'id' => $firstDocument->id,
        ]);
        Storage::disk('local')->assertMissing($firstDocumentPath);

        $reportRequest->refresh()->load('documents');
        $this->assertCount(1, $reportRequest->documents);
        $this->assertSame(TrademarkSearchReportRequest::REPORT_READY, $reportRequest->report_status);

        $this->actingAs($client, 'web')
            ->get(route('trademark-search-report.success', $reportRequest))
            ->assertOk()
            ->assertDontSee('Full Trademark Search Report')
            ->assertSee('Risk Summary');

        $remainingDocument = $reportRequest->documents->firstOrFail();
        $remainingDocumentPath = $remainingDocument->file_path;

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.trademark-search-reports.document.destroy', [$reportRequest, $remainingDocument]))
            ->assertRedirect(route('admin.trademark-search-reports.show', $reportRequest));

        Storage::disk('local')->assertMissing($remainingDocumentPath);
        $reportRequest->refresh()->load('documents');
        $this->assertCount(0, $reportRequest->documents);
        $this->assertSame(TrademarkSearchReportRequest::IN_REVIEW, $reportRequest->report_status);
    }

    public function test_guest_report_request_is_visible_after_signing_in_with_same_email(): void
    {
        $this->post(route('trademark-search-report.store'), $this->payload())->assertRedirect();
        $reportRequest = TrademarkSearchReportRequest::query()->firstOrFail();
        $this->assertNull($reportRequest->user_id);

        $client = User::factory()->create(['email' => 'alex@example.test']);

        $this->actingAs($client)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('North Pine')
            ->assertSee(route('trademark-search-report.payment', $reportRequest), false);
    }

    private function admin(): Admin
    {
        return Admin::create([
            'name' => 'Search Report Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
        ]);
    }

    private function payload(): array
    {
        return [
            'name' => 'Alex Morgan',
            'email' => 'alex@example.test',
            'phone' => '+447123456789',
            'brand_name' => 'North Pine',
            'business_activity' => 'Online retail services for sustainable home and lifestyle products.',
            'website' => '',
        ];
    }
}
