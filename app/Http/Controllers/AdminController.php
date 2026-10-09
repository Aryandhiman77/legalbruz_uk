<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Admin;
use App\Models\Document;
use App\Models\Payment;
use App\Models\TrademarkOppositionCase;
use App\Models\ApplicationStatusLog;
use App\Models\User;
use App\Models\StuckTrademarkCase;
use App\Models\WebsiteVisitor;
use App\Models\WebsiteServiceVisit;
use App\Models\CustomerReview;
use App\Models\ConsultationBooking;
use App\Models\TrademarkSearchReportRequest;
use App\Services\DocumentGenerator;
use App\Services\NotificationService;
use App\Services\TrademarkWorkflowService;
use App\Mail\DocumentApprovedNotification;
use App\Mail\DocumentRejectedNotification;
use App\Mail\DocumentVerifiedNotification;
use App\Mail\ApplicationReviewNotification;
use App\Mail\EventNotification;
use App\Support\PostFilingJourney;
use App\Support\TrademarkOppositionWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Support\TrademarkWorkflow;
use Throwable;

class AdminController extends Controller
{
    /**
     * Show admin dashboard
     */
    public function dashboard()
    {
        $statusColumn = $this->statusColumn();

        $pendingApplications = Application::where($statusColumn, $this->pendingReviewStatus())->count();
        $approvedApplications = Application::where($statusColumn, $this->approvedStatus())->count();
        $filedApplications = Application::where($statusColumn, $this->filedStatus())->count();
        $registeredApplications = Application::where(function ($query) {
            $query->where('registry_status', TrademarkWorkflow::REGISTRY_REGISTERED)
                ->orWhereNotNull('registered_at');
        })->count();
        $leads = User::count();
        $websiteVisitors = Schema::hasTable('website_visitors') ? WebsiteVisitor::count() : 0;
        $serviceLeads = Schema::hasTable('website_visitors')
            ? WebsiteVisitor::whereNotNull('service_first_visited_at')->count()
            : 0;
        $reviewsCount = Schema::hasTable('customer_reviews') ? CustomerReview::count() : 0;
        $publishedReviewsCount = Schema::hasTable('customer_reviews') ? CustomerReview::published()->count() : 0;
        $serviceVisitorCounts = collect(config('visitor_services', []))
            ->map(function (array $service, string $key) {
                $adminUrl = isset($service['admin_route'])
                    ? route($service['admin_route'], $service['admin_parameters'] ?? [])
                    : null;

                if ($adminUrl && isset($service['admin_fragment'])) {
                    $adminUrl .= '#'.$service['admin_fragment'];
                }

                return array_merge($service, [
                    'key' => $key,
                    'admin_url' => $adminUrl,
                    'visitors' => Schema::hasTable('website_service_visits')
                        ? WebsiteServiceVisit::where('service_key', $key)->count()
                        : 0,
                ]);
            })
            ->values();

        return view('admin.dashboard', [
            'pendingCount' => $pendingApplications,
            'approvedCount' => $approvedApplications,
            'filedCount' => $filedApplications,
            'registeredCount' => $registeredApplications,
            'leadsCount' => $leads,
            'websiteVisitors' => $websiteVisitors,
            'serviceLeads' => $serviceLeads,
            'reviewsCount' => $reviewsCount,
            'publishedReviewsCount' => $publishedReviewsCount,
            'serviceVisitorCounts' => $serviceVisitorCounts,
        ]);
    }

    /**
     * List pending applications for review
     */
    public function listPendingApplications(Request $request)
    {
        $statusColumn = $this->statusColumn();
        $pendingStatuses = $this->pendingQueueStatuses();
        $applications = Application::whereIn($statusColumn, $pendingStatuses)
            ->when($request->filled('status'), fn ($query) => $query->where($statusColumn, $request->string('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.trim((string) $request->string('search')).'%';
                $query->where(function ($query) use ($search) {
                    $query->where('applicant_name', 'like', $search)
                        ->orWhere('brand_name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('application_number', 'like', $search);
                });
            })
            ->with($this->applicationRelations())
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $applications->getCollection()->transform(function (Application $application) {
            return $this->normalizeApplicationRelations($application);
        });

        return view('admin.pending-applications', [
            'applications' => $applications,
            'pendingStatuses' => collect($pendingStatuses)
                ->mapWithKeys(fn ($status) => [$status => TrademarkWorkflow::label($status)])
                ->all(),
        ]);
    }

    /**
     * Show application details for admin review
     */
    public function viewApplication($applicationId)
    {
        $application = Application::with($this->applicationRelations())->findOrFail($applicationId);
        $application = $this->normalizeApplicationRelations($application);

        return view('admin.review-application', [
            'application' => $application,
            'oppositionApplication' => $application->oppositionApplication,
            'oppositionDefenceCase' => $application->oppositionDefenceCase,
        ]);
    }

    /**
     * Update the client-supplied fields shown in the Matter Overview card.
     */
    public function updateMatterOverview(Request $request, $applicationId)
    {
        $application = Application::findOrFail($applicationId);
        $phone = preg_replace('/[\s()-]+/', '', (string) $request->input('phone'));

        if (str_starts_with($phone, '07')) {
            $phone = '+44'.substr($phone, 1);
        }

        $request->merge(['phone' => $phone]);

        $validated = $request->validate([
            'brand_name' => ['required', 'string', 'max:255'],
            'applicant_name' => ['required', 'string', 'max:255'],
            'applicant_type' => ['required', Rule::in(array_keys(config('uk_site.applicant_types', [])))],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^(?:\+44|0)7[0-9]{9}$/'],
            'business_activities' => ['required', 'string', 'max:3000'],
        ], [
            'phone.regex' => 'Enter a valid UK mobile number beginning with 07 or +44 7.',
        ]);

        $details = $application->members_details ?? [];
        $applicantDetails = $details['applicant_details'] ?? [];
        $tradeMarkDetails = $details['trademark_details'] ?? [];

        $details['applicant_details'] = array_merge($applicantDetails, [
            'applicant_type' => $validated['applicant_type'],
            'legal_name' => $validated['applicant_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
        ]);
        $details['trademark_details'] = array_merge($tradeMarkDetails, [
            'trade_mark_wording' => $validated['brand_name'],
            'business_activities' => $validated['business_activities'],
        ]);

        $application->update([
            'brand_name' => $validated['brand_name'],
            'applicant_name' => $validated['applicant_name'],
            'entity_type' => $validated['applicant_type'] === 'individual' ? 'individual' : 'company',
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'description' => $validated['business_activities'],
            'goods_services' => $validated['business_activities'],
            'members_details' => $details,
        ]);

        return redirect()
            ->route('admin.view-application', $application)
            ->with('success', 'Matter overview updated successfully.');
    }

    /**
     * Show the selected application's owner dashboard without impersonating the client.
     */
    public function viewClientDashboard($applicationId)
    {
        $application = Application::with('user')->findOrFail($applicationId);
        $client = $application->user;
        $applications = $client->applications()->with('payments')->latest()->get();
        $stuckTrademarkCases = $client->stuckTrademarkCases()->latest()->get();
        $trademarkOppositionCases = $client->trademarkOppositionCases()
            ->where('flow_type', TrademarkOppositionWorkflow::FLOW_DEFEND)
            ->with('documents')
            ->latest()
            ->get();
        $trademarkOpposeCases = $client->trademarkOppositionCases()
            ->where('flow_type', TrademarkOppositionWorkflow::FLOW_OPPOSE)
            ->with('evidence')
            ->latest()
            ->get();
        $examinationReplyCases = $client->examinationReportReplyCases()->latest()->get();
        $consultationBookings = Schema::hasTable('consultation_bookings')
            ? ConsultationBooking::query()->visibleTo($client)->latest()->get()
            : collect();
        $trademarkSearchReportRequests = Schema::hasTable('trademark_search_report_requests')
            ? TrademarkSearchReportRequest::query()->visibleTo($client)->with('documents')->latest()->get()
            : collect();
        $pendingPayments = $applications->filter(fn ($clientApplication) => in_array($clientApplication->current_status, [
            TrademarkWorkflow::DRAFT,
            TrademarkWorkflow::PAYMENT_PENDING_FINAL,
        ], true))->count()
            + $consultationBookings->where('payment_status', 'pending')->count()
            + $trademarkSearchReportRequests->where('payment_status', 'pending')->count();
        $underReview = $applications->where('current_status', TrademarkWorkflow::UNDER_REVIEW)->count();
        $registered = $applications->filter(function ($clientApplication) {
            if (Schema::hasColumn('applications', 'registry_status')) {
                return $clientApplication->registry_status === TrademarkWorkflow::REGISTRY_REGISTERED;
            }

            return filled($clientApplication->registered_at);
        })->count();

        return view('dashboard.index', [
            'applications' => $applications,
            'pendingPayments' => $pendingPayments,
            'underReview' => $underReview,
            'registered' => $registered,
            'stuckTrademarkCases' => $stuckTrademarkCases,
            'trademarkOppositionCases' => $trademarkOppositionCases,
            'trademarkOpposeCases' => $trademarkOpposeCases,
            'examinationReplyCases' => $examinationReplyCases,
            'consultationBookings' => $consultationBookings,
            'trademarkSearchReportRequests' => $trademarkSearchReportRequests,
            'adminPreview' => true,
            'client' => $client,
            'previewApplication' => $application,
        ]);
    }

    /**
     * Show the exact client action center in a guarded, read-only admin preview.
     */
    public function viewClientActionCenter($applicationId)
    {
        $application = Application::with($this->applicationRelations())->findOrFail($applicationId);
        $application = $this->normalizeApplicationRelations($application);

        return view('trademark.status', [
            'application' => $application,
            'adminPreview' => true,
            'client' => $application->user,
        ]);
    }

    /**
     * Move a submitted application into the admin review stage.
     */
    public function startApplicationReview($applicationId, TrademarkWorkflowService $workflow)
    {
        $application = Application::findOrFail($applicationId);

        if ($application->current_status !== TrademarkWorkflow::APPLICATION_SUBMITTED) {
            return redirect()->back()->with('error', 'Only a submitted application can be moved into admin review.');
        }

        $workflow->startAdminReview($application);

        return redirect()->back()->with('success', 'Admin review started. You can now review, approve, or request changes for this application.');
    }

    /**
     * Approve application
     */
    public function approveApplication(Request $request, $applicationId, TrademarkWorkflowService $workflow)
    {
        $application = Application::findOrFail($applicationId);
        $user = $application->user;

        $this->storePendingOnboardingUploads($application, $request);

        $validated = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
            'engagement_letter_file' => 'nullable|file|mimes:pdf|max:15360',
            'other_document_files' => 'nullable|array',
            'other_document_files.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:15360',
            'signature_fields' => 'nullable|array',
            'signature_fields.engagement_letter.signature.page' => 'required|integer|min:1',
            'signature_fields.engagement_letter.signature.x' => 'required|numeric|min:0',
            'signature_fields.engagement_letter.signature.y' => 'required|numeric|min:0',
            'signature_fields.engagement_letter.signature.width' => 'required|numeric|min:10',
            'signature_fields.engagement_letter.signature.height' => 'required|numeric|min:5',
            'signature_fields.engagement_letter.date.page' => 'nullable|integer|min:1',
            'signature_fields.engagement_letter.date.x' => 'nullable|numeric|min:0',
            'signature_fields.engagement_letter.date.y' => 'nullable|numeric|min:0',
            'signature_fields.engagement_letter.date.width' => 'nullable|numeric|min:10',
            'signature_fields.engagement_letter.date.height' => 'nullable|numeric|min:5',
        ])->validate();

        $this->ensureRequiredOnboardingDocumentsPresent($application, $request);

        $applicationUpdateData = [
            'rejection_reason' => null,
        ];

        if (Schema::hasColumn('applications', 'admin_review_note')) {
            $applicationUpdateData['admin_review_note'] = $validated['notes'] ?? null;
        }

        $application->update($applicationUpdateData);
        $this->storeAdminWorkflowDocumentFromPending($application, 'engagement_letter', 'Engagement Letter uploaded by admin.');
        $this->storeAdditionalOnboardingDocumentsFromPending($application, 'Document sent by admin.');
        $this->storeOnboardingFieldPlacements($application, $request);
        $workflow->approveReview($application->fresh(), $validated['notes'] ?? null);
        $this->clearPendingOnboardingUploads($application);

        try {
            $approvalNotification = new ApplicationReviewNotification(
                $user,
                $application->fresh(),
                'approved',
                $validated['notes'] ?? null
            );

            if (config('queue.default') !== 'sync') {
                Mail::to($user->email)->queue($approvalNotification);
            } else {
                Mail::to($user->email)->send($approvalNotification);
            }
        } catch (Throwable $exception) {
            Log::warning('Application approval email failed.', [
                'application_id' => $application->id,
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }

        \App\Models\Notification::create([
            'user_id' => $user->id,
            'type' => 'application_approved',
            'title' => 'Engagement Letter Ready',
            'message' => 'Your initial review is complete. Please review and electronically sign the Engagement Letter.',
            'data' => [
                'application_id' => $application->id,
                'note' => $validated['notes'] ?? null,
            ],
        ]);

        return redirect()->back()->with('success', 'Initial review completed and the Engagement Letter was sent to the client for signing.');
    }

    public function resendOnboardingPackage(Request $request, $applicationId, TrademarkWorkflowService $workflow)
    {
        $application = Application::findOrFail($applicationId);
        $user = $application->user;

        if ($application->current_status !== TrademarkWorkflow::ONBOARDING_PENDING) {
            return redirect()->back()->with('error', 'Onboarding package can only be resent while the onboarding package step is pending.');
        }

        $this->storePendingOnboardingUploads($application, $request);

        $validated = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
            'engagement_letter_file' => 'nullable|file|mimes:pdf|max:15360',
            'other_document_files' => 'nullable|array',
            'other_document_files.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:15360',
            'signature_fields' => 'nullable|array',
            'signature_fields.engagement_letter.signature.page' => 'required|integer|min:1',
            'signature_fields.engagement_letter.signature.x' => 'required|numeric|min:0',
            'signature_fields.engagement_letter.signature.y' => 'required|numeric|min:0',
            'signature_fields.engagement_letter.signature.width' => 'required|numeric|min:10',
            'signature_fields.engagement_letter.signature.height' => 'required|numeric|min:5',
            'signature_fields.*.date.page' => 'nullable|integer|min:1',
            'signature_fields.*.date.x' => 'nullable|numeric|min:0',
            'signature_fields.*.date.y' => 'nullable|numeric|min:0',
            'signature_fields.*.date.width' => 'nullable|numeric|min:10',
            'signature_fields.*.date.height' => 'nullable|numeric|min:5',
        ])->validate();

        $this->ensureRequiredOnboardingDocumentsPresent($application, $request);

        $this->deleteWorkflowDocuments($application, [
            'engagement_letter (Signed)',
        ]);

        $this->storeAdminWorkflowDocumentFromPending($application, 'engagement_letter', 'Engagement letter resent by admin.');
        $this->storeAdditionalOnboardingDocumentsFromPending($application, 'Document sent by admin.');
        $this->storeOnboardingFieldPlacements($application, $request);
        $this->resetOnboardingTasks($application);
        $this->clearPendingOnboardingUploads($application);

        if (Schema::hasColumn('applications', 'workflow_meta')) {
            $meta = $application->workflow_meta ?? [];
            unset(
                $meta['onboarding_package_submitted_at'],
                $meta['onboarding_package_notes'],
                $meta['onboarding_reupload_document'],
                $meta['onboarding_reupload_submitted_at']
            );
            $meta['onboarding_package_resent_at'] = now()->toDateTimeString();
            $meta['onboarding_package_resend_note'] = $validated['notes'] ?? null;
            $application->update(['workflow_meta' => $meta]);
        }

        if (Schema::hasTable('application_status_logs')) {
            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'from_status' => $application->current_status,
                'to_status' => $application->current_status,
                'actor_type' => 'admin',
                'actor_id' => Auth::guard('admin')->id(),
                'reason' => $validated['notes'] ?: 'Engagement Letter resent for the applicant to review and electronically sign.',
                'metadata' => [
                    'event' => 'onboarding_package_resent',
                    'title' => 'Onboarding Package Resent',
                ],
            ]);
        }

        \App\Models\Notification::create([
            'user_id' => $user->id,
            'type' => 'onboarding_package_resent',
            'title' => 'Onboarding Package Updated',
            'message' => 'Your Engagement Letter has been updated. Please review and electronically sign the latest copy.',
            'data' => [
                'application_id' => $application->id,
            ],
        ]);

        try {
            $message = 'Your Engagement Letter has been updated. Please log in, review the latest copy, and electronically sign it.';

            if (!empty($validated['notes'])) {
                $message .= "\n\nAdmin note: " . $validated['notes'];
            }

            $notification = new EventNotification(
                $user,
                'Onboarding package updated',
                $message,
                $this->mailAttachmentsForDocuments($application->documents()
                    ->whereIn('document_type', ['engagement_letter', 'other_document'])
                    ->latest('id')
                    ->get()
                    ->all()),
                $this->applicationActionCenterUrl($application)
            );

            if (config('queue.default') !== 'sync') {
                Mail::to($user->email)->queue($notification);
            } else {
                Mail::to($user->email)->send($notification);
            }
        } catch (\Throwable $e) {
            Log::warning('Onboarding resend email notification failed.', [
                'application_id' => $application->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        $workflow->ensureOnboardingPackage($application->fresh());

        return redirect()->back()->with('success', 'Engagement Letter resent successfully. The client has been notified.');
    }

    public function uploadManualOnboardingPackage(Request $request, $applicationId, TrademarkWorkflowService $workflow)
    {
        $application = Application::findOrFail($applicationId);

        if (!in_array($application->current_status, [TrademarkWorkflow::UNDER_REVIEW, TrademarkWorkflow::ONBOARDING_PENDING], true)) {
            return redirect()->back()->with('error', 'Manual onboarding upload is only available while review or onboarding is pending.');
        }

        $validated = $request->validate([
            'manual_engagement_letter_file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:15360',
            'manual_other_document_files' => 'nullable|array',
            'manual_other_document_files.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:15360',
            'manual_onboarding_note' => 'nullable|string|max:1000',
        ]);

        $note = $validated['manual_onboarding_note'] ?? 'Physically signed onboarding document uploaded by admin.';

        if ($application->current_status === TrademarkWorkflow::UNDER_REVIEW) {
            if (!$application->application_number) {
                $application->update(['application_number' => 'TM-' . now()->format('Y') . '-' . $application->id]);
            }

            $applicationUpdateData = ['rejection_reason' => null];
            if (Schema::hasColumn('applications', 'admin_review_note')) {
                $applicationUpdateData['admin_review_note'] = $note;
            }
            $application->update($applicationUpdateData);
            $workflow->approveReview($application->fresh(), $note);
            $application = $application->fresh(['documents', 'tasks']);
        }

        $signedEngagementLetter = $this->storeManualSignedOnboardingDocument(
            $application,
            'engagement_letter (Signed)',
            $request->file('manual_engagement_letter_file'),
            $note
        );
        $manualUploadedDocuments = collect([
            $signedEngagementLetter,
        ]);

        foreach ($request->file('manual_other_document_files', []) as $file) {
            if (!$file) {
                continue;
            }

            $manualUploadedDocuments->push(
                $this->storeAdminWorkflowDocumentCopy($application, 'other_document', $file, $note)
            );
        }

        $workflow->completeTask($application, 'engagement_letter_signed');
        $workflow->completeTask($application, 'signature_submitted');

        if (Schema::hasColumn('applications', 'workflow_meta')) {
            $meta = $application->workflow_meta ?? [];
            $meta['onboarding_package_submitted_at'] = now()->toDateTimeString();
            $meta['onboarding_package_notes'] = $note;
            $meta['onboarding_package_manual_upload_at'] = now()->toDateTimeString();
            $meta['onboarding_package_manual_upload_by_admin_id'] = Auth::guard('admin')->id();
            $application->update(['workflow_meta' => $meta]);
        }

        if (Schema::hasTable('application_status_logs')) {
            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'from_status' => $application->current_status,
                'to_status' => $application->current_status,
                'actor_type' => 'admin',
                'actor_id' => Auth::guard('admin')->id(),
                'reason' => $note,
                'metadata' => [
                    'event' => 'manual_onboarding_package_uploaded',
                    'title' => 'Manual Onboarding Package Uploaded',
                    'engagement_letter_document_id' => $signedEngagementLetter->id,
                    'other_document_ids' => $manualUploadedDocuments
                        ->filter(fn ($document) => $document->document_type === 'other_document')
                        ->pluck('id')
                        ->values()
                        ->all(),
                ],
            ]);
        }

        \App\Models\Notification::create([
            'user_id' => $application->user_id,
            'type' => 'manual_onboarding_uploaded',
            'title' => 'Signed Engagement Letter uploaded',
            'message' => 'Your signed Engagement Letter has been uploaded by the admin team.',
            'data' => [
                'application_id' => $application->id,
            ],
        ]);

        try {
            Mail::to($application->user->email)->send(new EventNotification(
                $application->user,
                'Signed Engagement Letter submitted and approved',
                'Your signed Engagement Letter has been submitted and approved by the admin team. The approved document is attached to this email for your records.',
                $this->mailAttachmentsForDocuments($manualUploadedDocuments->all()),
                $this->applicationActionCenterUrl($application)
            ));
        } catch (\Throwable $e) {
            report($e);
        }

        $workflow->refreshOnboardingStatus($application->fresh());

        return redirect()->back()->with('success', 'Signed Engagement Letter uploaded successfully.');
    }

    /**
     * Request applicant changes before onboarding
     */
    public function requestApplicationChanges(Request $request, $applicationId, TrademarkWorkflowService $workflow)
    {
        $application = Application::findOrFail($applicationId);
        $user = $application->user;

        $validated = $request->validate([
            'change_request' => 'required|string|min:5',
        ]);

        $workflow->requestReviewChanges($application->fresh(), $validated['change_request']);

        Mail::to($user->email)->send(new ApplicationReviewNotification(
            $user,
            $application->fresh(),
            'changes_requested',
            $validated['change_request']
        ));

        \App\Models\Notification::create([
            'user_id' => $user->id,
            'type' => 'application_changes_requested',
            'title' => 'Application Changes Requested',
            'message' => 'Please recheck your trademark application details and submit it again for review.',
            'data' => [
                'application_id' => $application->id,
                'note' => $validated['change_request'],
            ],
        ]);

        return redirect()->back()->with('success', 'Changes requested. The applicant can now recheck and edit the application.');
    }

    /**
     * Generate Affidavit document manually
     */
    public function generateAffidavit($applicationId)
    {
        $application = Application::findOrFail($applicationId);
        $user = $application->user;
        $existingAffidavit = $application->documents()
            ->whereIn('document_type', ['affidavit', 'affidavit (Signed)'])
            ->exists();

        if ($existingAffidavit || filled(data_get($application->workflow_meta, 'affidavit_generated_at'))) {
            return redirect()->back()->with('error', 'Affidavit has already been generated for this application and cannot be generated again.');
        }

        // Generate application number if not already generated
        if (!$application->application_number) {
            $applicationNumber = 'TM-' . now()->format('Y') . '-' . $application->id;
            $application->update(['application_number' => $applicationNumber]);
        }

        // Generate new affidavit
        $affidavit = DocumentGenerator::generateAffidavit($application);

        if (Schema::hasColumn('applications', 'workflow_meta')) {
            $meta = $application->workflow_meta ?? [];
            $meta['affidavit_generated_at'] = now()->toDateTimeString();
            $meta['affidavit_document_id'] = $affidavit->id;

            $application->update([
                'workflow_meta' => $meta,
            ]);
        }

        if (Schema::hasTable('application_status_logs')) {
            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'from_status' => $application->current_status,
                'to_status' => $application->current_status,
                'actor_type' => 'admin',
                'actor_id' => Auth::guard('admin')->id(),
                'reason' => 'Affidavit generated and shared with client for reference.',
                'metadata' => [
                    'event' => 'affidavit_generated',
                    'title' => 'Affidavit Generated',
                    'document_id' => $affidavit->id,
                ],
            ]);
        }

        \App\Models\Notification::create([
            'user_id' => $user->id,
            'type' => 'affidavit_generated',
            'title' => 'Affidavit Ready for Download',
            'message' => 'Your affidavit has been generated. Please review and download it from your dashboard.',
            'data' => [
                'application_id' => $application->id,
            ],
        ]);

        try {
            Mail::to($user->email)->send(new EventNotification(
                $user,
                'Affidavit ready for download',
                'Your affidavit has been generated. Please log in to review and download it from your dashboard.',
                $this->mailAttachmentsForDocuments([$affidavit]),
                $this->applicationActionCenterUrl($application)
            ));
        } catch (\Throwable $e) {
            // Keep affidavit generation resilient even if mail delivery is unavailable.
        }

        return redirect()->back()->with('success', '✅ Affidavit generated and shared with the user for download.');
    }

    /**
     * Generate POA document manually
     */
    public function generatePOA($applicationId)
    {
        $application = Application::findOrFail($applicationId);

        // Generate application number if not already generated
        if (!$application->application_number) {
            $applicationNumber = 'TM-' . now()->format('Y') . '-' . $application->id;
            $application->update(['application_number' => $applicationNumber]);
        }

        // Delete existing POA if any
        $application->documents()
            ->where('document_type', 'poa')
            ->each(function($doc) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($doc->file_path);
                $doc->delete();
            });

        // Generate new POA
        $poa = DocumentGenerator::generatePOA($application);

        return redirect()->back()->with('success', '✅ POA document generated successfully!');
    }

    /**
     * File application with trademark registry
     */
    public function fileApplication(Request $request, $applicationId, TrademarkWorkflowService $workflow)
    {
        $application = Application::findOrFail($applicationId);

        if ($application->current_status !== TrademarkWorkflow::PAYMENT_COMPLETED
            || ! $this->hasVerifiedFinalPayment($application)) {
            return redirect()->back()->with('error', 'Full payment must be completed before filing.');
        }

        $validated = $request->validate([
            'application_number' => 'required|string|max:255',
            'filing_date' => 'required|date|before_or_equal:today',
            'examination_deadline' => 'nullable|date|after_or_equal:filing_date',
            'publication_date' => 'nullable|date|after_or_equal:filing_date',
            'opposition_deadline' => 'nullable|date|after_or_equal:publication_date',
            'registration_number' => 'nullable|string|max:255',
            'renewal_date' => 'nullable|date|after:filing_date',
            'filing_receipt' => 'required|file|mimes:pdf|max:15360',
            'filing_note' => 'nullable|string|max:1000',
        ]);

        $receipt = $request->file('filing_receipt');
        $receiptName = 'ukipo-filing-receipt-' . $application->id . '-' . now()->timestamp . '.pdf';
        $receiptPath = 'documents/ukipo/' . $receiptName;
        Storage::disk('public')->put($receiptPath, file_get_contents($receipt));
        Document::create([
            'application_id' => $application->id,
            'user_id' => $application->user_id,
            'document_type' => 'ukipo_filing_receipt',
            'file_path' => $receiptPath,
            'file_name' => $receiptName,
            'file_type' => 'pdf',
            'file_size' => $receipt->getSize(),
            'status' => 'approved',
            'verification_notes' => 'UKIPO filing receipt uploaded by admin.',
        ]);

        $workflow->markFiled($application, [
            'application_number' => $validated['application_number'],
            'filing_date' => $validated['filing_date'],
            'examination_deadline' => $validated['examination_deadline'] ?? null,
            'publication_date' => $validated['publication_date'] ?? null,
            'opposition_deadline' => $validated['opposition_deadline'] ?? null,
            'registration_number' => $validated['registration_number'] ?? null,
            'renewal_date' => $validated['renewal_date'] ?? null,
            'filing_receipt_path' => $receiptPath,
            'filing_note' => $validated['filing_note'] ?? null,
        ]);

        return redirect()->back()->with('success', 'UKIPO filing recorded successfully.');
    }

    public function completeFiledStage(Request $request, $applicationId, TrademarkWorkflowService $workflow)
    {
        $application = Application::findOrFail($applicationId);

        if ($application->current_status !== TrademarkWorkflow::FILED) {
            return redirect()->back()->with('error', 'Only filed applications can be marked complete.');
        }

        $validated = $request->validate([
            'completion_note' => 'nullable|string|max:1000',
            'admin_stage_documents' => 'nullable|array|max:10',
            'admin_stage_documents.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:15360',
        ]);

        $adminStageDocuments = $this->storePostFilingAdminDocuments($application, 'filed', $request->file('admin_stage_documents', []), 'Filed stage document sent by admin.');

        $workflow->completeFiledStage(
            $application,
            $validated['completion_note'] ?? null,
            $this->mailAttachmentsForDocuments($adminStageDocuments)
        );

        return redirect()->back()->with('success', 'Filed stage marked complete successfully.');
    }

    public function updatePostFilingStage(Request $request, $applicationId, string $stage)
    {
        $application = Application::findOrFail($applicationId);
        $stageConfig = PostFilingJourney::stage($application, $stage);

        if (!$stageConfig || $application->current_status !== TrademarkWorkflow::POST_FILING) {
            abort(404);
        }

        $validated = $request->validate([
            'stage_status' => 'required|in:pending,processing,completed,opposed',
            'stage_note' => 'required_if:request_documents,1|required_if:stage_status,opposed|nullable|string|max:1000',
            'request_documents' => 'nullable|boolean',
            'opposition_received_on' => 'nullable|date',
            'counter_statement_due_on' => 'nullable|date|after_or_equal:opposition_received_on',
            'admin_stage_documents' => 'nullable|array|max:10',
            'admin_stage_documents.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:15360',
        ], [
            'stage_note.required_if' => 'Please add a client note explaining which supporting documents the applicant should upload.',
            'opposition_received_on.required_if' => 'Please add the opposition received date before marking the stage as opposed.',
            'counter_statement_due_on.required_if' => 'Please add the counter statement due date before marking the stage as opposed.',
        ]);

        $status = $validated['stage_status'];
        $isOpposedStage = $stage === 'accepted_advertised' && $status === PostFilingJourney::OPPOSED;

        if ($status === PostFilingJourney::OPPOSED && $stage !== 'accepted_advertised') {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Opposed status is only available for Stage 6: Accepted & Advertised.');
        }

        $allowsCompletedRegistrationDocuments = $stage === 'registered' && $status === PostFilingJourney::COMPLETED;
        if (!in_array($status, [PostFilingJourney::PROCESSING, PostFilingJourney::OPPOSED], true) && !$allowsCompletedRegistrationDocuments && $request->hasFile('admin_stage_documents')) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['admin_stage_documents' => 'Documents for client review can only be attached when the stage status is Processing, Opposed, or Registered is being completed.']);
        }

        if ($isOpposedStage && blank($validated['opposition_received_on'] ?? null)) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['opposition_received_on' => 'Please add the opposition received date before marking the stage as opposed.']);
        }

        if ($isOpposedStage && blank($validated['counter_statement_due_on'] ?? null)) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['counter_statement_due_on' => 'Please add the counter statement due date before marking the stage as opposed.']);
        }

        $previousIncompleteStage = PostFilingJourney::previousIncompleteStage($application, $stage);

        if ($previousIncompleteStage) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Complete Stage ' . $previousIncompleteStage['number'] . ': ' . $previousIncompleteStage['title'] . ' before updating this stage.');
        }

        $now = now();
        $meta = $application->workflow_meta ?? [];
        $stageMeta = data_get($meta, "post_filing_journey.stages.$stage", []);
        $requestDocuments = $status === PostFilingJourney::PROCESSING && $request->boolean('request_documents');
        $stageNote = $status === PostFilingJourney::PROCESSING ? ($validated['stage_note'] ?? null) : null;
        if ($isOpposedStage) {
            $stageNote = $validated['stage_note'] ?? null;
        }
        $stageMeta['status'] = $status;
        $stageMeta['updated_at'] = $now->toDateTimeString();
        $stageMeta['admin_note'] = $stageNote;

        if ($status === PostFilingJourney::PROCESSING) {
            $stageMeta['processing_at'] = $now->toDateTimeString();

            if ($requestDocuments) {
                $stageMeta['documents_requested'] = true;
                $stageMeta['documents_requested_at'] = $now->toDateTimeString();
                unset($stageMeta['documents_submitted_at']);
            } else {
                $stageMeta['documents_requested'] = false;
            }
        }

        if ($isOpposedStage) {
            $stageMeta['opposed_at'] = $now->toDateTimeString();
            $stageMeta['documents_requested'] = false;
            $stageMeta['opposition_received_on'] = $validated['opposition_received_on'];
            $stageMeta['counter_statement_due_on'] = $validated['counter_statement_due_on'];
            unset($stageMeta['processing_at'], $stageMeta['completed_at'], $stageMeta['documents_submitted_at']);
            $application->registry_status = TrademarkWorkflow::REGISTRY_OPPOSITION;
        }

        if ($isOpposedStage) {
            $this->syncOpposedTrademarkFilingApplication($application, $validated['opposition_received_on'], $validated['counter_statement_due_on']);
        }

        if ($status === PostFilingJourney::PENDING) {
            $stageMeta['documents_requested'] = false;
            unset($stageMeta['processing_at'], $stageMeta['completed_at'], $stageMeta['opposed_at'], $stageMeta['opposition_received_on'], $stageMeta['counter_statement_due_on']);
        }

        if ($status === PostFilingJourney::COMPLETED) {
            $stageMeta['completed_at'] = $now->toDateTimeString();
            $stageMeta['documents_requested'] = false;

            if ($stage === 'registered') {
                $application->registered_at = $now;
                $application->registry_status = TrademarkWorkflow::REGISTRY_REGISTERED;
            }
        }

        data_set($meta, "post_filing_journey.stages.$stage", $stageMeta);
        $application->workflow_meta = $meta;
        $application->save();

        $adminStageDocuments = $this->storePostFilingAdminDocuments(
            $application,
            $stage,
            $request->file('admin_stage_documents', []),
            ($stageConfig['title'] ?? 'Post filing') . ' document sent by admin.'
        );

        if (Schema::hasTable('application_status_logs')) {
            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'from_status' => $application->current_status,
                'to_status' => $application->current_status,
                'actor_type' => 'admin',
                'actor_id' => Auth::guard('admin')->id(),
                'reason' => ($stageConfig['title'] ?? 'Post filing') . ' marked ' . ($isOpposedStage ? 'opposed' : $status) . '.',
                'metadata' => [
                    'event' => 'post_filing_stage_updated',
                    'stage' => $stage,
                    'stage_status' => $status,
                ],
            ]);
        }

        \App\Models\Notification::create([
            'user_id' => $application->user_id,
            'type' => 'post_filing_stage_updated',
            'title' => $isOpposedStage ? 'Opposition Notice Received' : 'Trademark journey updated',
            'message' => $isOpposedStage
                ? 'An opposition notice has been received for your trademark application.'
                : (($stageConfig['title'] ?? 'Post filing') . ' is now ' . ucwords($status) . '.'),
            'data' => [
                'application_id' => $application->id,
                'stage' => $stage,
                'stage_status' => $status,
                'action_url' => $this->applicationActionCenterUrl($application),
            ],
        ]);

        try {
            $mailTitle = 'Trademark journey updated: ' . ($stageConfig['title'] ?? 'Post filing');
            $message = ($stageConfig['title'] ?? 'Post filing') . ' is now ' . ucwords($status) . '.';
            $mailAttachments = !empty($adminStageDocuments)
                ? $this->mailAttachmentsForDocuments($adminStageDocuments)
                : [];
            $actionText = 'Open Application Tracking';

            if ($stage === 'registered' && $status === PostFilingJourney::COMPLETED) {
                $trademarkName = $application->brand_name ?: 'your trademark';
                $mailTitle = 'Trademark registered successfully: ' . $trademarkName;
                $message = 'Your trademark for ' . $trademarkName . ' has been registered successfully.';
                $message .= "\n\nThank you for using LegalBruz for this purpose.";
                $registeredAdminDocuments = $application->documents()
                    ->where('document_type', PostFilingJourney::documentType('registered'))
                    ->where('file_path', 'like', 'workflow/admin/post-filing/%')
                    ->get()
                    ->all();
                $mailAttachments = $this->mailAttachmentsForDocuments($registeredAdminDocuments ?: $adminStageDocuments);
            }

            if ($isOpposedStage) {
                $mailTitle = 'Opposition Notice Received: ' . ($application->brand_name ?: 'Trademark');
                $message = implode("\n", array_filter([
                    'An opposition notice has been received for your trademark application.',
                    '',
                    'Trademark: ' . ($application->brand_name ?: 'N/A'),
                    'Application No.: ' . ($application->application_number ?: 'Awaiting assignment'),
                    'Opposition received on: ' . \Illuminate\Support\Carbon::parse($validated['opposition_received_on'])->format('d M Y'),
                    'Counter Statement Due: ' . \Illuminate\Support\Carbon::parse($validated['counter_statement_due_on'])->format('d M Y'),
                    '',
                    'No extra work is required right now.',
                ]));
            }

            if ($requestDocuments) {
                $message .= ' Please open the tracking page and upload any requested supporting documents for this stage.';
            }

            if (!empty($stageNote)) {
                $message .= "\n\nAdmin note: " . $stageNote;
            }

            Mail::to($application->user->email)->send(new EventNotification(
                $application->user,
                $mailTitle,
                $message,
                $mailAttachments,
                $this->applicationActionCenterUrl($application),
                $actionText
            ));
        } catch (\Throwable $e) {
            // Keep admin workflow resilient if mail transport is unavailable.
        }

        return redirect()->back()->with('success', ($stageConfig['title'] ?? 'Post filing') . ' updated successfully.');
    }

    private function syncOpposedTrademarkFilingApplication(Application $application, ?string $oppositionReceivedOn, ?string $counterStatementDueOn): void
    {
        if ($application->current_status !== TrademarkWorkflow::POST_FILING) {
            return;
        }

        $stage = 'accepted_advertised';
        $meta = $application->workflow_meta ?? [];
        $stageMeta = data_get($meta, "post_filing_journey.stages.$stage", []);
        $stageMeta['status'] = PostFilingJourney::OPPOSED;
        $stageMeta['updated_at'] = now()->toDateTimeString();
        $stageMeta['opposed_at'] = now()->toDateTimeString();
        $stageMeta['opposition_received_on'] = $oppositionReceivedOn;
        $stageMeta['counter_statement_due_on'] = $counterStatementDueOn;
        $stageMeta['documents_requested'] = false;
        unset($stageMeta['processing_at'], $stageMeta['completed_at'], $stageMeta['documents_submitted_at']);

        data_set($meta, "post_filing_journey.stages.$stage", $stageMeta);
        $application->workflow_meta = $meta;
        $application->registry_status = TrademarkWorkflow::REGISTRY_OPPOSITION;
        $application->save();
    }

    /**
     * Update application status based on API response
     */
    public function updateStatus(Request $request, $applicationId)
    {
        $application = Application::findOrFail($applicationId);

        $validated = $request->validate([
            'trademark_status' => 'required|string',
            'status' => 'required|string',
        ]);

        $statusUpdate = [
            'trademark_status' => $validated['trademark_status'],
            'registry_status' => $validated['status'],
        ];

        if ($validated['status'] === TrademarkWorkflow::REGISTRY_REGISTERED && Schema::hasColumn('applications', 'registered_at')) {
            $statusUpdate['registered_at'] = $application->registered_at ?: now();
        }

        $application->update($statusUpdate);

        $registryStatusLabel = ucwords(strtolower(str_replace('_', ' ', $validated['status'])));
        $title = 'Trade mark status updated: ' . $validated['trademark_status'];
        $message = implode("\n", [
            'The registry status for ' . ($application->brand_name ?: 'your trade mark') . ' has been updated.',
            '',
            'Trade mark status: ' . $validated['trademark_status'],
            'Registry status: ' . $registryStatusLabel,
        ]);

        if (Schema::hasTable('notifications')) {
            \App\Models\Notification::create([
                'user_id' => $application->user_id,
                'type' => 'registry_status_updated',
                'title' => $title,
                'message' => $message,
                'data' => [
                    'application_id' => $application->id,
                    'trademark_status' => $validated['trademark_status'],
                    'registry_status' => $validated['status'],
                    'action_url' => $this->applicationActionCenterUrl($application),
                ],
            ]);
        }

        $this->sendClientStageActionEmail($application, $title, $message);

        return response()->json(['success' => true]);
    }

    public function verifyKyc(Request $request, $applicationId, TrademarkWorkflowService $workflow)
    {
        $application = Application::findOrFail($applicationId);
        $signedEngagementLetter = $application->documents()
            ->where('document_type', 'engagement_letter (Signed)')
            ->latest('id')
            ->first();

        if (!$signedEngagementLetter || $signedEngagementLetter->status !== 'verified') {
            return redirect()->back()->with('error', 'The signed Engagement Letter must be verified before this step can be completed.');
        }

        $validated = $request->validate([
            'kyc_note' => 'nullable|string|max:1000',
        ]);

        $workflow->verifyKyc($application, $validated['kyc_note'] ?? null);

        return redirect()->back()->with('success', 'KYC verified and strategy stage started.');
    }

    public function completeStrategy(Request $request, $applicationId, TrademarkWorkflowService $workflow)
    {
        $application = Application::findOrFail($applicationId);
        $quickComplete = $request->boolean('quick_complete');

        $validated = $request->validate([
            'search_summary' => $quickComplete ? 'nullable|string|max:5000' : 'required|string|min:10',
            'risk_level' => $quickComplete ? 'nullable|string|max:100' : 'required|string|max:100',
            'recommended_classes' => 'nullable|string',
            'goods_services' => 'nullable|string',
            'warning_flags' => 'nullable|string',
        ]);

        $recommendedClasses = $validated['recommended_classes'] ?? null;
        $warningFlags = $validated['warning_flags'] ?? null;

        $classes = $recommendedClasses
            ? array_values(array_filter(array_map('trim', explode(',', $recommendedClasses))))
            : [];

        $warnings = $warningFlags
            ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $warningFlags))))
            : [];

        $workflow->completeStrategy($application, [
            'search_summary' => $quickComplete ? null : ($validated['search_summary'] ?? null),
            'risk_level' => $quickComplete ? null : ($validated['risk_level'] ?? null),
            'recommended_classes' => $classes,
            'goods_services' => $validated['goods_services'] ?? null,
            'warning_flags' => $warnings,
        ]);

        return redirect()->back()->with('success', $quickComplete
            ? 'Strategy completed. The matter has been moved to the next internal step.'
            : 'Strategy report completed.');
    }

    public function uploadSearchReport(Request $request, $applicationId, TrademarkWorkflowService $workflow)
    {
        $application = Application::findOrFail($applicationId);
        $user = $application->user;

        $validated = $request->validate([
            'search_report' => 'required|file|mimes:pdf|max:15360',
            'search_report_note' => 'nullable|string|max:1000',
        ]);

        $file = $request->file('search_report');
        $filename = 'search-report-' . $application->id . '-' . now()->timestamp . '.' . $file->getClientOriginalExtension();
        $path = 'documents/search-reports/' . $filename;

        Storage::disk('public')->put($path, file_get_contents($file));

        $document = Document::create([
            'application_id' => $application->id,
            'user_id' => $application->user_id,
            'document_type' => 'search_report',
            'file_path' => $path,
            'file_name' => $filename,
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
            'status' => 'approved',
            'verification_notes' => $validated['search_report_note'] ?: 'Manual search report shared by admin.',
        ]);

        if (Schema::hasColumn('applications', 'workflow_meta')) {
            $meta = $application->workflow_meta ?? [];
            $meta['strategy']['search_report_document_id'] = $document->id;
            $meta['strategy']['search_report_shared_at'] = now()->toDateTimeString();
            $application->update([
                'workflow_meta' => $meta,
            ]);
        }

        if (Schema::hasTable('application_status_logs')) {
            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'from_status' => $application->current_status,
                'to_status' => $application->current_status,
                'actor_type' => 'admin',
                'actor_id' => Auth::guard('admin')->id(),
                'reason' => $validated['search_report_note'] ?: 'Manual search report uploaded and shared with client.',
                'metadata' => [
                    'event' => 'search_report_shared',
                    'title' => 'Search Report Shared',
                    'document_id' => $document->id,
                ],
            ]);
        }

        \App\Models\Notification::create([
            'user_id' => $user->id,
            'type' => 'search_report_shared',
            'title' => 'Search Report Shared',
            'message' => 'A manual search report has been uploaded for your trademark matter.',
            'data' => [
                'application_id' => $application->id,
                'document_id' => $document->id,
            ],
        ]);

        try {
            $message = 'A manual search report has been uploaded to your application. Please log in to review it.';

            if (!empty($validated['search_report_note'])) {
                $message .= "\n\nAdmin note: " . $validated['search_report_note'];
            }

            Mail::to($user->email)->send(new EventNotification(
                $user,
                'Search report shared',
                $message,
                $this->mailAttachmentsForDocuments([$document]),
                $this->applicationActionCenterUrl($application)
            ));
        } catch (\Throwable $e) {
            // Keep admin workflow resilient if mail transport is unavailable.
        }

        $workflow->completeStrategy($application->fresh(), [
            'search_summary' => $validated['search_report_note'] ?: 'Search report uploaded and shared with the applicant.',
        ]);

        return redirect()->back()->with('success', 'Search report uploaded, shared with the user, and strategy completed.');
    }

    public function publishDraft(Request $request, $applicationId, TrademarkWorkflowService $workflow)
    {
        $application = Application::findOrFail($applicationId);
        $priorityClaimed = (bool) data_get($application->members_details, 'priority_details.priority_claim_required', false);

        if ($priorityClaimed && !$request->boolean('priority_approved')) {
            return redirect()->back()->withInput()->withErrors([
                'priority_approved' => 'Confirm the priority claim before sending the application for client approval.',
            ]);
        }

        $validated = $request->validate([
            'draft_file' => 'required|file|mimes:pdf|max:15360',
            'final_specification_file' => 'required|file|mimes:pdf|max:15360',
            'filing_authority_file' => 'required|file|mimes:pdf|max:15360',
            'final_classes' => 'required|string|max:500',
            'final_specification' => 'required|string|max:10000',
            'priority_deadline' => 'nullable|date',
            'draft_note' => 'nullable|string|max:2000',
        ]);

        $file = $request->file('draft_file');
        $filename = 'draft-' . $application->id . '-' . now()->timestamp . '.pdf';
        $path = 'documents/drafts/' . $filename;

        Storage::disk('public')->put($path, file_get_contents($file));

        $application->documents()
            ->where('document_type', 'draft_pdf')
            ->where('status', 'approved')
            ->update(['status' => 'archived']);

        Document::create([
            'application_id' => $application->id,
            'user_id' => $application->user_id,
            'document_type' => 'draft_pdf',
            'file_path' => $path,
            'file_name' => $filename,
            'file_type' => 'pdf',
            'file_size' => $file->getSize(),
            'status' => 'approved',
            'verification_notes' => $validated['draft_note'] ?: 'Draft PDF sent by admin for applicant approval.',
        ]);

        Document::create([
            'application_id' => $application->id,
            'user_id' => $application->user_id,
            'document_type' => 'application_summary',
            'file_path' => $path,
            'file_name' => $filename,
            'file_type' => 'pdf',
            'file_size' => $file->getSize(),
            'status' => 'approved',
            'verification_notes' => 'Application summary sent by admin for client approval.',
        ]);

        foreach ([
            'final_specification_file' => ['final_specification', 'final-specification'],
            'filing_authority_file' => ['filing_authority', 'filing-authority'],
        ] as $field => [$documentType, $slug]) {
            $approvalFile = $request->file($field);
            $approvalFilename = $slug . '-' . $application->id . '-' . now()->timestamp . '.pdf';
            $approvalPath = 'documents/client-approval/' . $approvalFilename;
            Storage::disk('public')->put($approvalPath, file_get_contents($approvalFile));
            Document::create([
                'application_id' => $application->id,
                'user_id' => $application->user_id,
                'document_type' => $documentType,
                'file_path' => $approvalPath,
                'file_name' => $approvalFilename,
                'file_type' => 'pdf',
                'file_size' => $approvalFile->getSize(),
                'status' => 'approved',
                'verification_notes' => 'Document sent by admin for client approval.',
            ]);
        }

        $details = $application->members_details ?? [];
        $tradeMarkDetails = $details['trademark_details'] ?? [];
        $finalClasses = array_values(array_filter(array_map('trim', explode(',', $validated['final_classes']))));
        $tradeMarkDetails['final_approved_classes'] = $finalClasses;
        $tradeMarkDetails['final_goods_and_services_specification'] = $validated['final_specification'];
        $details['trademark_details'] = $tradeMarkDetails;
        if ($priorityClaimed) {
            $details['priority_details']['priority_deadline'] = $validated['priority_deadline'] ?? null;
            $details['priority_details']['approved_by_admin'] = true;
        }
        $application->forceFill([
            'classes' => $finalClasses,
            'members_details' => $details,
        ])->save();

        $workflow->publishDraft($application, [
            'classes' => $finalClasses,
            'goods_services' => $validated['final_specification'],
            'tm_a_draft_path' => $path,
            'draft_note' => $validated['draft_note'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Application and approval documents sent to the client.');
    }

    /**
     * List all applications
     */
    public function listAllApplications(Request $request)
    {
        $statusColumn = $this->statusColumn();
        $applicationStats = [
            'pending' => Application::where($statusColumn, $this->pendingReviewStatus())->count(),
            'approved' => Application::where($statusColumn, $this->approvedStatus())->count(),
            'filed' => Application::where($statusColumn, $this->filedStatus())->count(),
            'registered' => Application::where(function ($query) {
                $query->where('registry_status', TrademarkWorkflow::REGISTRY_REGISTERED)
                    ->orWhereNotNull('registered_at');
            })->count(),
        ];

        $applications = Application::with('user')
            ->when($request->filled('status'), function ($query) use ($request, $statusColumn) {
                if ($request->status === TrademarkWorkflow::REGISTRY_REGISTERED) {
                    $query->where(function ($registeredQuery) {
                        $registeredQuery->where('registry_status', TrademarkWorkflow::REGISTRY_REGISTERED)
                            ->orWhereNotNull('registered_at');
                    });

                    return;
                }

                $query->where($statusColumn, $request->status);
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . $request->search . '%';
                $query->where(function ($inner) use ($search) {
                    $inner->where('applicant_name', 'like', $search)
                        ->orWhere('brand_name', 'like', $search)
                        ->orWhere('email', 'like', $search);
                });
            })
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.all-applications', [
            'applications' => $applications,
            'applicationStats' => $applicationStats,
            'applicationStatuses' => [
                ...TrademarkWorkflow::statusOptions(),
            ],
        ]);
    }

    /**
     * Approve individual document
     */
    public function approveDocument($documentId)
    {
        $document = Document::findOrFail($documentId);
        $application = $document->application;
        $user = $application->user;

        $document->update([
            'status' => 'approved'
        ]);

        // Send email notification
        Mail::to($user->email)->send(new DocumentApprovedNotification($user, $document, $application));

        // Create in-app notification
        \App\Models\Notification::create([
            'user_id' => $user->id,
            'type' => 'document_approved',
            'title' => '📋 Document Approved',
            'message' => ucfirst(str_replace('_', ' ', $document->document_type)) . ' has been approved!',
            'data' => [
                'document_id' => $document->id,
                'application_id' => $application->id,
                'document_type' => $document->document_type,
            ],
        ]);

        return redirect()->back()->with('success', '✅ ' . ucfirst(str_replace('_', ' ', $document->document_type)) . ' approved successfully! User notified via email.');
    }

    /**
     * Reject individual document
     */
    public function rejectDocument(Request $request, $documentId)
    {
        $document = Document::findOrFail($documentId);
        $application = $document->application;
        $user = $application->user;

        if (!$this->requiresAdminVerification($document)) {
            return redirect()->back()->with('error', ucfirst(str_replace('_', ' ', $document->document_type)) . ' does not require admin verification.');
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:10|max:1000',
        ]);

        $document->update([
            'status' => 'reupload_requested',
            'verified_at' => null,
            'verification_notes' => $validated['rejection_reason'],
        ]);

        // Send email notification
        Mail::to($user->email)->send(new DocumentRejectedNotification($user, $document, $application));

        // Create in-app notification
        \App\Models\Notification::create([
            'user_id' => $user->id,
            'type' => 'document_rejected',
            'title' => '❌ Document Rejected',
            'message' => ucfirst(str_replace('_', ' ', $document->document_type)) . ' needs to be reuploaded. Reason: ' . $validated['rejection_reason'],
            'data' => [
                'document_id' => $document->id,
                'application_id' => $application->id,
                'document_type' => $document->document_type,
                'rejection_reason' => $validated['rejection_reason'],
            ],
        ]);

        return redirect()->back()->with('success', 'Reupload requested for ' . ucfirst(str_replace('_', ' ', $document->document_type)) . '. The user has been notified.');
    }

    /**
     * Verify individual document (mark as verified)
     */
    public function verifyDocument($documentId, TrademarkWorkflowService $workflow)
    {
        $document = Document::findOrFail($documentId);
        $application = $document->application;
        $user = $application->user;

        if (!$this->requiresAdminVerification($document)) {
            return redirect()->back()->with('error', ucfirst(str_replace('_', ' ', $document->document_type)) . ' does not require admin verification.');
        }

        $document->update([
            'status' => 'verified',
            'verified_at' => now(),
            'verification_notes' => 'Accepted and verified by admin.',
        ]);

        // Send email notification
        Mail::to($user->email)->send(new DocumentVerifiedNotification($user, $document, $application));

        // Create in-app notification
        \App\Models\Notification::create([
            'user_id' => $user->id,
            'type' => 'document_verified',
            'title' => '✔️ Document Verified',
            'message' => ucfirst(str_replace('_', ' ', $document->document_type)) . ' has been verified!',
            'data' => [
                'document_id' => $document->id,
                'application_id' => $application->id,
                'document_type' => $document->document_type,
            ],
        ]);

        $workflow->refreshOnboardingStatus($application->fresh());

        return redirect()->back()->with('success', ucfirst(str_replace('_', ' ', $document->document_type)) . ' accepted and verified successfully.');
    }

    public function bulkReviewDocuments(Request $request, $applicationId, TrademarkWorkflowService $workflow)
    {
        $application = Application::with('user')->findOrFail($applicationId);

        $validated = $request->validate([
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['integer'],
            'action' => ['required', 'in:verified,reupload_requested'],
            'note' => ['required_if:action,reupload_requested', 'nullable', 'string', 'min:10', 'max:1000'],
        ]);

        $documents = $application->documents()
            ->whereIn('id', $validated['document_ids'])
            ->get()
            ->filter(fn (Document $document) => $this->requiresAdminVerification($document));

        if ($documents->isEmpty()) {
            return redirect()->back()->with('error', 'Please select at least one signed onboarding document.');
        }

        $labels = $documents
            ->map(fn (Document $document) => ucwords(str_replace(['_', '(signed)'], [' ', ' (Signed)'], $document->document_type)))
            ->values();

        if ($validated['action'] === 'verified') {
            foreach ($documents as $document) {
                $document->update([
                    'status' => 'verified',
                    'verified_at' => now(),
                    'verification_notes' => 'Accepted and verified by admin.',
                ]);
            }

            \App\Models\Notification::create([
                'user_id' => $application->user_id,
                'type' => 'documents_verified',
                'title' => '✔️ Documents Verified',
                'message' => 'The following document(s) have been verified: ' . $labels->join(', '),
                'data' => [
                    'application_id' => $application->id,
                    'document_ids' => $documents->pluck('id')->values()->all(),
                ],
            ]);

            $this->sendClientStageActionEmail(
                $application,
                'Documents verified: ' . ($application->brand_name ?: 'Trade mark application'),
                'The following document(s) have been verified by our team: ' . $labels->join(', ') . ".\n\nYour application will now continue to the next applicable stage."
            );

            $workflow->refreshOnboardingStatus($application->fresh());

            return redirect()->back()->with('success', 'Selected document(s) accepted and verified successfully.');
        }

        foreach ($documents as $document) {
            $document->update([
                'status' => 'reupload_requested',
                'verified_at' => null,
                'verification_notes' => $validated['note'],
            ]);
        }

        \App\Models\Notification::create([
            'user_id' => $application->user_id,
            'type' => 'documents_reupload_requested',
            'title' => '❌ Documents Need Reupload',
            'message' => 'The following document(s) need to be reuploaded: ' . $labels->join(', ') . '. Reason: ' . $validated['note'],
            'data' => [
                'application_id' => $application->id,
                'document_ids' => $documents->pluck('id')->values()->all(),
                'rejection_reason' => $validated['note'],
            ],
        ]);

        $this->sendClientStageActionEmail(
            $application,
            'Action required: reupload application documents',
            'Please reupload the following document(s): ' . $labels->join(', ') . ".\n\nAdmin note: " . $validated['note']
        );

        return redirect()->back()->with('success', 'Reupload requested for selected document(s).');
    }

    private function sendClientStageActionEmail(
        Application $application,
        string $title,
        string $message,
        array $attachments = [],
        string $actionText = 'Open Application Status'
    ): void {
        try {
            $notification = new EventNotification(
                $application->user,
                $title,
                $message,
                $attachments,
                $this->applicationActionCenterUrl($application),
                $actionText
            );

            if (config('queue.default') !== 'sync') {
                Mail::to($application->user->email)->queue($notification);
            } else {
                Mail::to($application->user->email)->send($notification);
            }
        } catch (\Throwable $exception) {
            Log::warning('Client stage-action email could not be dispatched.', [
                'application_id' => $application->id,
                'title' => $title,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function requiresAdminVerification(Document $document): bool
    {
        return in_array($document->document_type, [
            'engagement_letter (Signed)',
            'filing_authority (Signed)',
        ], true);
    }

    /**
     * Approve payment
     */
    public function approvePayment(Request $request, $paymentId)
    {
        $payment = \App\Models\Payment::findOrFail($paymentId);

        $payment->update([
            'status' => 'approved',
            'approved_at' => now()
        ]);

        // Send notification to user
        \App\Services\NotificationService::sendPaymentApprovedNotification($payment);

        return redirect()->back()->with('success', '✅ Payment approved! User has been notified via email and in-app notification.');
    }

    /**
     * Reject payment
     */
    public function rejectPayment(Request $request, $paymentId)
    {
        $payment = \App\Models\Payment::findOrFail($paymentId);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:10',
        ]);

        $payment->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => $validated['rejection_reason']
        ]);

        // Send notification to user
        \App\Services\NotificationService::sendPaymentRejectedNotification($payment, $validated['rejection_reason']);

        return redirect()->back()->with('success', '❌ Payment rejected! User has been notified with the reason.');
    }

    private function statusColumn(): string
    {
        return Schema::hasColumn('applications', 'service_status') ? 'service_status' : 'status';
    }

    private function storeAdminWorkflowDocument(Application $application, string $documentType, $file, string $notes): Document
    {
        $application->documents()
            ->where('document_type', $documentType)
            ->get()
            ->each(function (Document $doc) {
                if ($doc->file_path && Storage::disk('public')->exists($doc->file_path)) {
                    Storage::disk('public')->delete($doc->file_path);
                }

                $doc->delete();
            });

        $filename = $documentType . '-' . $application->id . '-' . now()->timestamp . '.' . $file->getClientOriginalExtension();
        $path = 'workflow/admin/' . $application->id . '/' . $filename;

        Storage::disk('public')->put($path, file_get_contents($file));

        return Document::create([
            'application_id' => $application->id,
            'user_id' => $application->user_id,
            'document_type' => $documentType,
            'file_path' => $path,
            'file_name' => $filename,
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
            'status' => 'approved',
            'verification_notes' => $notes,
        ]);
    }

    private function storePostFilingAdminDocuments(Application $application, string $stage, array $files, string $notes): array
    {
        $documents = [];

        foreach ($files as $file) {
            if (!$file) {
                continue;
            }

            $extension = strtolower((string) $file->getClientOriginalExtension());
            $filename = 'admin-post-filing-' . $stage . '-' . $application->id . '-' . now()->timestamp . '-' . uniqid() . '.' . $extension;
            $path = 'workflow/admin/post-filing/' . $application->id . '/' . $filename;

            Storage::disk('public')->put($path, file_get_contents($file));

            $documents[] = Document::create([
                'application_id' => $application->id,
                'user_id' => $application->user_id,
                'document_type' => PostFilingJourney::documentType($stage),
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName() ?: $filename,
                'file_type' => $extension,
                'file_size' => $file->getSize(),
                'status' => 'approved',
                'verification_notes' => $notes,
            ]);
        }

        return $documents;
    }

    private function storeManualSignedOnboardingDocument(Application $application, string $documentType, $file, string $notes): Document
    {
        $application->documents()
            ->where('document_type', $documentType)
            ->get()
            ->each(function (Document $doc) {
                if ($doc->file_path && Storage::disk('public')->exists($doc->file_path)) {
                    Storage::disk('public')->delete($doc->file_path);
                }

                $doc->delete();
            });

        $filename = strtolower(str_replace([' ', '(', ')'], ['-', '', ''], $documentType)) . '-' . $application->id . '-' . now()->timestamp . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = 'workflow/admin/' . $application->id . '/manual-signed/' . $filename;

        Storage::disk('public')->put($path, file_get_contents($file));

        return Document::create([
            'application_id' => $application->id,
            'user_id' => $application->user_id,
            'document_type' => $documentType,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName() ?: $filename,
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
            'status' => 'verified',
            'verified_at' => now(),
            'verification_notes' => $notes,
        ]);
    }

    private function mailAttachmentsForDocuments(array $documents): array
    {
        return collect($documents)
            ->filter(fn ($document) => $document instanceof Document)
            ->map(function (Document $document) {
                if (!$document->file_path || !Storage::disk('public')->exists($document->file_path)) {
                    return null;
                }

                return [
                    'path' => Storage::disk('public')->path($document->file_path),
                    'name' => $this->mailAttachmentNameForDocument($document),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function mailAttachmentNameForDocument(Document $document): string
    {
        $extension = pathinfo((string) ($document->file_name ?: $document->file_path), PATHINFO_EXTENSION);
        $baseName = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $document->document_type));
        $baseName = trim($baseName ?: 'document', '-');

        return $baseName . '-' . $document->id . ($extension ? '.' . $extension : '');
    }

    private function applicationActionCenterUrl(Application $application): string
    {
        return route('trademark.status', $application->id);
    }

    private function ensureRequiredOnboardingDocumentsPresent(Application $application, Request $request): void
    {
        $requiredDocuments = [
            'engagement_letter' => ['field' => 'engagement_letter_file', 'label' => 'Engagement Letter'],
        ];
        $errors = [];

        foreach ($requiredDocuments as $documentType => $config) {
            if (
                $request->hasFile($config['field'])
                || $this->hasPendingOnboardingUpload($application, $documentType)
                || $this->hasAdminWorkflowDocument($application, $documentType)
            ) {
                continue;
            }

            $errors[$config['field']] = $config['label'] . ' is required before issuing the onboarding package.';
        }

        if ($errors !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }

    private function hasAdminWorkflowDocument(Application $application, string $documentType): bool
    {
        return $application->documents()
            ->where('document_type', $documentType)
            ->where('status', 'approved')
            ->exists();
    }

    private function storePendingOnboardingUploads(Application $application, Request $request): void
    {
        Validator::make($request->all(), [
            'engagement_letter_file' => 'nullable|file|mimes:pdf|max:15360',
            'other_document_files' => 'nullable|array',
            'other_document_files.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:15360',
        ])->validate();

        $uploads = $this->pendingOnboardingUploads($application);

        foreach (['engagement_letter_file' => 'engagement_letter'] as $field => $documentType) {
            if (!$request->hasFile($field)) {
                continue;
            }

            if (isset($uploads[$documentType])) {
                $this->deletePendingUpload($uploads[$documentType]);
            }

            $uploads[$documentType] = $this->storePendingUpload($application, $request->file($field));
        }

        if ($request->hasFile('other_document_files')) {
            $uploads['other_document'] = $uploads['other_document'] ?? [];

            foreach ($request->file('other_document_files', []) as $file) {
                if ($file) {
                    $uploads['other_document'][] = $this->storePendingUpload($application, $file);
                }
            }
        }

        session([$this->pendingOnboardingUploadsKey($application) => $uploads]);
    }

    private function pendingOnboardingUploads(Application $application): array
    {
        return session($this->pendingOnboardingUploadsKey($application), []);
    }

    private function pendingOnboardingUploadsKey(Application $application): string
    {
        return 'admin_onboarding_uploads.' . $application->id;
    }

    private function hasPendingOnboardingUpload(Application $application, string $documentType): bool
    {
        $uploads = $this->pendingOnboardingUploads($application);

        return isset($uploads[$documentType]['path']) && Storage::exists($uploads[$documentType]['path']);
    }

    private function storePendingUpload(Application $application, $file): array
    {
        $directory = 'admin-onboarding-drafts/' . (Auth::guard('admin')->id() ?: 'admin') . '/' . $application->id;
        $filename = now()->timestamp . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = $directory . '/' . $filename;

        Storage::put($path, file_get_contents($file));

        return [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'extension' => $file->getClientOriginalExtension(),
            'size' => $file->getSize(),
        ];
    }

    private function storeAdminWorkflowDocumentFromPending(Application $application, string $documentType, string $notes): ?Document
    {
        $uploads = $this->pendingOnboardingUploads($application);
        $upload = $uploads[$documentType] ?? null;

        if (!is_array($upload) || empty($upload['path']) || !Storage::exists($upload['path'])) {
            return null;
        }

        $document = $this->storeAdminWorkflowDocumentFromStoredUpload($application, $documentType, $upload, $notes, true);
        $this->removePendingUpload($application, $documentType);

        return $document;
    }

    private function storeAdditionalOnboardingDocumentsFromPending(Application $application, string $notes): void
    {
        $uploads = $this->pendingOnboardingUploads($application);
        $otherUploads = $uploads['other_document'] ?? [];

        if (!is_array($otherUploads)) {
            return;
        }

        foreach ($otherUploads as $upload) {
            if (is_array($upload) && !empty($upload['path']) && Storage::exists($upload['path'])) {
                $this->storeAdminWorkflowDocumentFromStoredUpload($application, 'other_document', $upload, $notes, false);
            }
        }

        $this->removePendingUpload($application, 'other_document');
    }

    private function storeAdminWorkflowDocumentFromStoredUpload(Application $application, string $documentType, array $upload, string $notes, bool $replaceExisting): Document
    {
        if ($replaceExisting) {
            $application->documents()
                ->where('document_type', $documentType)
                ->get()
                ->each(function (Document $doc) {
                    if ($doc->file_path && Storage::disk('public')->exists($doc->file_path)) {
                        Storage::disk('public')->delete($doc->file_path);
                    }

                    $doc->delete();
                });
        }

        $extension = $upload['extension'] ?? pathinfo((string) ($upload['original_name'] ?? ''), PATHINFO_EXTENSION);
        $filename = $documentType . '-' . $application->id . '-' . now()->timestamp . '-' . uniqid() . '.' . $extension;
        $path = 'workflow/admin/' . $application->id . '/' . $filename;

        Storage::disk('public')->put($path, Storage::get($upload['path']));
        $this->deletePendingUpload($upload);

        return Document::create([
            'application_id' => $application->id,
            'user_id' => $application->user_id,
            'document_type' => $documentType,
            'file_path' => $path,
            'file_name' => $documentType === 'other_document' ? ($upload['original_name'] ?? $filename) : $filename,
            'file_type' => $extension,
            'file_size' => Storage::disk('public')->size($path),
            'status' => 'approved',
            'verification_notes' => $notes,
        ]);
    }

    private function removePendingUpload(Application $application, string $documentType): void
    {
        $uploads = $this->pendingOnboardingUploads($application);
        unset($uploads[$documentType]);
        session([$this->pendingOnboardingUploadsKey($application) => $uploads]);
    }

    private function clearPendingOnboardingUploads(Application $application): void
    {
        foreach ($this->pendingOnboardingUploads($application) as $upload) {
            if (isset($upload['path'])) {
                $this->deletePendingUpload($upload);
                continue;
            }

            if (is_array($upload)) {
                foreach ($upload as $item) {
                    if (is_array($item)) {
                        $this->deletePendingUpload($item);
                    }
                }
            }
        }

        session()->forget($this->pendingOnboardingUploadsKey($application));
    }

    private function deletePendingUpload(array $upload): void
    {
        if (!empty($upload['path']) && Storage::exists($upload['path'])) {
            Storage::delete($upload['path']);
        }
    }

    private function storeAdditionalOnboardingDocuments(Application $application, array $files, string $notes): void
    {
        foreach ($files as $file) {
            if (!$file) {
                continue;
            }

            $this->storeAdminWorkflowDocumentCopy($application, 'other_document', $file, $notes);
        }
    }

    private function storeAdminWorkflowDocumentCopy(Application $application, string $documentType, $file, string $notes): Document
    {
        $filename = $documentType . '-' . $application->id . '-' . now()->timestamp . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = 'workflow/admin/' . $application->id . '/' . $filename;

        Storage::disk('public')->put($path, file_get_contents($file));

        return Document::create([
            'application_id' => $application->id,
            'user_id' => $application->user_id,
            'document_type' => $documentType,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName() ?: $filename,
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
            'status' => 'approved',
            'verification_notes' => $notes,
        ]);
    }

    private function storeOnboardingFieldPlacements(Application $application, Request $request): void
    {
        if (!Schema::hasColumn('applications', 'workflow_meta')) {
            return;
        }

        $fields = [];
        $input = $request->input('signature_fields', []);

        foreach (['engagement_letter'] as $documentType) {
            foreach (['signature', 'date'] as $fieldType) {
                $field = data_get($input, $documentType . '.' . $fieldType, []);
                $fieldEnabled = (string) ($field['enabled'] ?? '0') === '1';

                if (!$fieldEnabled) {
                    continue;
                }

                if (!$this->hasCompletePlacementField($field)) {
                    continue;
                }

                $fields[$documentType][] = [
                    'type' => $fieldType,
                    'label' => $fieldType === 'signature' ? 'Applicant Signature' : 'Date',
                    'page' => max((int) ($field['page'] ?? 1), 1),
                    'x' => max((float) ($field['x'] ?? 0), 0),
                    'y' => max((float) ($field['y'] ?? 0), 0),
                    'width' => max((float) ($field['width'] ?? 0), 10),
                    'height' => max((float) ($field['height'] ?? 0), 5),
                ];
            }
        }

        $meta = $application->workflow_meta ?? [];
        $meta['signature_fields'] = $fields;
        $meta['signature_fields_updated_at'] = now()->toDateTimeString();

        $application->forceFill(['workflow_meta' => $meta])->save();
    }

    private function hasCompletePlacementField(array $field): bool
    {
        foreach (['page', 'x', 'y', 'width', 'height'] as $key) {
            if (!array_key_exists($key, $field) || $field[$key] === null || $field[$key] === '') {
                return false;
            }
        }

        return true;
    }

    private function deleteWorkflowDocuments(Application $application, array $documentTypes): void
    {
        $application->documents()
            ->whereIn('document_type', $documentTypes)
            ->get()
            ->each(function (Document $document) {
                if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                    Storage::disk('public')->delete($document->file_path);
                }

                $document->delete();
            });
    }

    private function resetOnboardingTasks(Application $application): void
    {
        if (!Schema::hasTable('application_tasks')) {
            return;
        }

        $application->tasks()
            ->whereIn('task_code', ['engagement_letter_signed', 'signature_submitted'])
            ->update([
                'status' => 'pending',
                'completed_at' => null,
            ]);
    }

    private function hasRegistryStatusColumn(): bool
    {
        return Schema::hasColumn('applications', 'registry_status');
    }

    private function pendingReviewStatus(): string
    {
        return $this->statusColumn() === 'service_status'
            ? TrademarkWorkflow::UNDER_REVIEW
            : 'pending_admin';
    }

    private function approvedStatus(): string
    {
        return $this->statusColumn() === 'service_status'
            ? TrademarkWorkflow::APPROVED_FOR_FILING
            : 'approved';
    }

    private function filedStatus(): string
    {
        return $this->statusColumn() === 'service_status'
            ? TrademarkWorkflow::FILED
            : 'filed';
    }

    private function pendingQueueStatuses(): array
    {
        if ($this->statusColumn() === 'service_status') {
            return [
                TrademarkWorkflow::APPLICATION_SUBMITTED,
                TrademarkWorkflow::UNDER_REVIEW,
                TrademarkWorkflow::ONBOARDING_PENDING,
                TrademarkWorkflow::STRATEGY_IN_PROGRESS,
                TrademarkWorkflow::STRATEGY_COMPLETED,
                TrademarkWorkflow::CHANGES_REQUESTED,
                TrademarkWorkflow::PAYMENT_COMPLETED,
            ];
        }

        return [
            'pending_admin',
            'approved',
        ];
    }

    private function hasVerifiedFinalPayment(Application $application): bool
    {
        if (! Schema::hasTable('payments')) {
            return false;
        }

        $payments = $application->payments()
            ->whereIn('status', ['completed', 'approved'])
            ->get();

        if ($payments->contains(fn (Payment $payment) => in_array(
            strtolower((string) ($payment->payment_type ?? '')),
            ['final', 'full'],
            true,
        ))) {
            return true;
        }

        return $payments->where('percentage', '50%')->count() >= 2
            || $payments->contains(fn (Payment $payment) => (string) $payment->percentage === '100%');
    }

    private function applicationRelations(): array
    {
        $relations = ['user', 'documents', 'payments'];

        if (Schema::hasTable('application_tasks')) {
            $relations[] = 'tasks';
        }

        if (Schema::hasTable('draft_versions')) {
            $relations[] = 'draftVersions';
        }

        if (Schema::hasTable('application_status_logs')) {
            $relations[] = 'statusLogs';
        }

        if (Schema::hasColumn('applications', 'opposition_application_id')) {
            $relations[] = 'oppositionApplication';
        }

        if (Schema::hasColumn('applications', 'opposition_defence_case_id')) {
            $relations[] = 'oppositionDefenceCase';
        }

        return $relations;
    }

    private function normalizeApplicationRelations(Application $application): Application
    {
        if (!Schema::hasTable('application_tasks')) {
            $application->setRelation('tasks', collect());
        }

        if (!Schema::hasTable('draft_versions')) {
            $application->setRelation('draftVersions', collect());
        }

        if (!Schema::hasTable('application_status_logs')) {
            $application->setRelation('statusLogs', collect());
        }

        return $application;
    }
}
