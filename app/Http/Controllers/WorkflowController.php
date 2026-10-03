<?php

namespace App\Http\Controllers;

use App\Mail\AdminWorkflowNotification;
use App\Models\Application;
use App\Models\ApplicationStatusLog;
use App\Models\Admin;
use App\Models\Document;
use App\Services\DocumentGenerator;
use App\Services\PdfSignatureStampService;
use App\Services\TrademarkWorkflowService;
use App\Support\PostFilingJourney;
use App\Support\TrademarkWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class WorkflowController extends Controller
{
    public function completeTask(Request $request, $applicationId, $taskCode, TrademarkWorkflowService $workflow)
    {
        $application = Auth::user()->applications()->findOrFail($applicationId);

        $workflow->completeTask($application, $taskCode);
        $workflow->refreshOnboardingStatus($application->fresh());
        $this->notifyAdminsOfApplicantAction(
            $application,
            'Applicant completed a workflow task',
            'Task completed: ' . str_replace('_', ' ', $taskCode)
        );

        return redirect()->route('trademark.status', $application->id)
            ->with('success', 'Task updated successfully.');
    }

    public function requestDraftChanges(Request $request, $applicationId, TrademarkWorkflowService $workflow)
    {
        $application = Auth::user()->applications()->findOrFail($applicationId);

        $validated = $request->validate([
            'change_request' => 'required|string|min:10|max:1000',
        ]);

        $workflow->requestDraftChanges($application, $validated['change_request']);

        return redirect()->route('trademark.status', $application->id)
            ->with('success', 'Your change request has been sent to the drafting team.');
    }

    public function approveDraft(
        Request $request,
        $applicationId,
        TrademarkWorkflowService $workflow
    )
    {
        $application = Auth::user()->applications()->findOrFail($applicationId);

        $validated = $request->validate([
            'approval_notes' => 'nullable|string|max:1000',
            'approve_applicant' => 'accepted',
            'approve_mark' => 'accepted',
            'approve_classes_specification' => 'accepted',
            'confirm_genuine_use' => 'accepted',
            'authorise_filing' => 'accepted',
            'signed_filing_authority' => 'required|file|mimes:pdf,jpg,jpeg,png|max:15360',
        ]);

        $draftDocument = $application->documents()
            ->where('document_type', 'draft_pdf')
            ->where('status', 'approved')
            ->latest('id')
            ->first();

        if (!$draftDocument) {
            return redirect()->route('trademark.status', $application->id)
                ->with('error', 'The draft PDF is not available yet. Please ask the admin to send the draft again.');
        }

        if (Schema::hasColumn('applications', 'workflow_meta')) {
            $meta = $application->workflow_meta ?? [];
            $meta['client_approval'] = [
                'applicant_approved' => true,
                'mark_approved' => true,
                'classes_and_specification_approved' => true,
                'genuine_use_or_intention_confirmed' => true,
                'filing_authority_confirmed' => true,
                'approved_at' => now()->toDateTimeString(),
            ];
            $application->forceFill(['workflow_meta' => $meta])->save();
        }

        foreach ([
            'signed_filing_authority' => 'filing_authority (Signed)',
        ] as $field => $documentType) {
            $file = $request->file($field);
            $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');
            $filename = str_replace([' ', '(', ')'], ['-', '', ''], strtolower($documentType)) . '-' . $application->id . '-' . now()->timestamp . '.' . $extension;
            $path = $file->storeAs('documents/client-approval/signed', $filename, 'public');
            Document::create([
                'application_id' => $application->id,
                'user_id' => $application->user_id,
                'document_type' => $documentType,
                'file_path' => $path,
                'file_name' => $filename,
                'file_type' => $extension,
                'file_size' => $file->getSize(),
                'status' => 'uploaded',
                'verification_notes' => 'Signed client-approval document submitted for admin verification.',
            ]);
        }

        $workflow->approveDraft($application, [
            'approval_notes' => $validated['approval_notes'] ?? null,
        ]);

        $application->refresh();

        if (in_array($application->current_status, [TrademarkWorkflow::PAYMENT_COMPLETED, TrademarkWorkflow::FILED], true)) {
            return redirect()->route('trademark.status', $application->id)
                ->with('success', 'Application approved successfully. Your full payment is already complete, so the application is ready for filing.');
        }

        return redirect()->route('payment.show', $application->id)
            ->with('success', 'Application approved successfully. Please complete the final payment to proceed with filing.');
    }

    public function submitPostFilingDocuments(Request $request, $applicationId, string $stage)
    {
        $application = Auth::user()->applications()->findOrFail($applicationId);
        $stageConfig = PostFilingJourney::stage($application, $stage);

        if (!$stageConfig || $application->current_status !== TrademarkWorkflow::POST_FILING) {
            abort(404);
        }

        $validated = $request->validate([
            'post_filing_documents' => 'required|array|min:1|max:10',
            'post_filing_documents.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:15360',
            'post_filing_note' => 'nullable|string|max:1000',
        ], [
            'post_filing_documents.required' => 'Please upload at least one supporting document for this stage.',
            'post_filing_documents.min' => 'Please upload at least one supporting document for this stage.',
        ]);

        $documents = [];

        foreach ($request->file('post_filing_documents', []) as $file) {
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $filename = 'post-filing-' . $stage . '-' . $application->id . '-' . now()->timestamp . '-' . uniqid() . '.' . $extension;
            $path = 'documents/post-filing/' . $application->id . '/' . $filename;

            Storage::disk('public')->put($path, file_get_contents($file));

            $documents[] = Document::create([
                'application_id' => $application->id,
                'user_id' => Auth::id(),
                'document_type' => PostFilingJourney::documentType($stage),
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName() ?: $filename,
                'file_type' => $extension,
                'file_size' => $file->getSize(),
                'status' => 'uploaded',
                'verification_notes' => trim(($stageConfig['title'] ?? 'Post filing') . ' applicant upload. ' . ($validated['post_filing_note'] ?? '')),
            ]);
        }

        if (Schema::hasColumn('applications', 'workflow_meta')) {
            $meta = $application->workflow_meta ?? [];
            $stageMeta = data_get($meta, "post_filing_journey.stages.$stage", []);
            $stageMeta['documents_submitted_at'] = now()->toDateTimeString();

            if (!empty($validated['post_filing_note'])) {
                $stageMeta['applicant_note'] = $validated['post_filing_note'];
            }

            data_set($meta, "post_filing_journey.stages.$stage", $stageMeta);
            $application->update(['workflow_meta' => $meta]);
        }

        if (Schema::hasTable('application_status_logs')) {
            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'from_status' => $application->current_status,
                'to_status' => $application->current_status,
                'actor_type' => 'user',
                'actor_id' => Auth::id(),
                'reason' => ($stageConfig['title'] ?? 'Post filing') . ' documents submitted by applicant.',
                'metadata' => [
                    'event' => 'post_filing_documents_submitted',
                    'stage' => $stage,
                    'document_ids' => collect($documents)->pluck('id')->all(),
                ],
            ]);
        }

        $this->notifyAdminsOfApplicantAction(
            $application,
            'Applicant submitted post-filing documents',
            ($stageConfig['title'] ?? 'Post filing') . ' documents were submitted for review.'
        );

        return redirect()->route('trademark.status', $application->id)
            ->with('success', 'Post-filing documents submitted successfully.');
    }

    public function applyOnboardingSignature(
        Request $request,
        $applicationId,
        $documentType,
        PdfSignatureStampService $pdfSignatureStampService
    )
    {
        $application = Auth::user()->applications()->findOrFail($applicationId);

        if ($application->current_status !== TrademarkWorkflow::ONBOARDING_PENDING) {
            abort(422, 'Onboarding signature application is only available during onboarding.');
        }

        if ($documentType !== 'engagement_letter') {
            abort(404);
        }

        if (!$this->documentRequiresSignature($application, $documentType)) {
            abort(404);
        }

        $validated = $request->validate([
            'signature_mode' => 'required|in:draw,type,upload',
            'digital_signature' => 'required|string|max:255',
            'signature_image_data' => 'nullable|string',
            'signature_image' => 'nullable|file|mimetypes:image/png,image/jpeg,image/pjpeg,image/jpg|max:2048',
        ]);

        $sourceDocument = $application->documents()
            ->where('document_type', $documentType)
            ->latest('id')
            ->first();

        if (!$sourceDocument) {
            return response()->json([
                'message' => ucfirst(str_replace('_', ' ', $documentType)) . ' is not available yet. Please ask the admin to upload it again.',
            ], 422);
        }

        $signatureVisual = $this->prepareSignatureVisual($request, $validated['signature_mode'], '', $application, $documentType);

        try {
            $signedDocument = $pdfSignatureStampService->generateSignedWorkflowPdf(
                $application,
                $sourceDocument,
                $validated['digital_signature'],
                null,
                'prepared',
                $signatureVisual['image_path']
            );
        } catch (\RuntimeException $exception) {
            $this->cleanupSignatureVisual($signatureVisual);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            $this->cleanupSignatureVisual($signatureVisual);
            report($exception);

            return response()->json([
                'message' => 'The signature could not be applied right now. Please ask the admin to re-upload the document and try again.',
            ], 422);
        }

        $this->cleanupSignatureVisual($signatureVisual);
        $this->notifyAdminsOfApplicantAction(
            $application,
            'Applicant applied onboarding signature',
            'Signature applied to ' . str_replace('_', ' ', $documentType) . '.'
        );

        return response()->json([
            'message' => 'Signature applied successfully.',
            'document_id' => $signedDocument->id,
            'view_url' => route('user.document.view', $signedDocument->id),
            'download_url' => route('user.document.download', $signedDocument->id),
            'status' => 'Signature Applied',
        ]);
    }

    public function submitOnboardingPackage(
        Request $request,
        $applicationId,
        TrademarkWorkflowService $workflow,
        PdfSignatureStampService $pdfSignatureStampService
    )
    {
        $application = Auth::user()->applications()->findOrFail($applicationId);

        if ($application->current_status !== TrademarkWorkflow::ONBOARDING_PENDING) {
            abort(422, 'Onboarding signature submission is only available during onboarding.');
        }

        $validated = $request->validate([
            'engagement_signature_mode' => 'nullable|in:draw,type,upload',
            'engagement_digital_signature' => 'nullable|string|max:255',
            'engagement_signature_image_data' => 'nullable|string',
            'engagement_signature_image' => 'nullable|file|mimetypes:image/png,image/jpeg,image/pjpeg,image/jpg|max:2048',
            'onboarding_notes' => 'nullable|string|max:1000',
        ]);

        $engagementLetter = $application->documents()
            ->where('document_type', 'engagement_letter')
            ->latest('id')
            ->first();

        if (!$engagementLetter) {
            return redirect()->route('trademark.status', $application->id)
                ->with('error', 'The Engagement Letter is not available yet. Please wait for the admin to upload it.');
        }

        $signedEngagementLetter = $application->documents()
            ->where('document_type', 'engagement_letter (Signed)')
            ->where('status', '!=', 'reupload_requested')
            ->latest('id')
            ->first();

        $engagementSignatureVisual = ['image_path' => null];

        try {
            if (!$signedEngagementLetter) {
                $request->validate([
                    'engagement_signature_mode' => 'required|in:draw,type,upload',
                    'engagement_digital_signature' => 'required|string|max:255',
                ]);

                $engagementSignatureVisual = $this->prepareSignatureVisual($request, (string) $request->input('engagement_signature_mode'), 'engagement', $application, 'engagement_letter');
                $signedEngagementLetter = $pdfSignatureStampService->generateSignedWorkflowPdf(
                    $application,
                    $engagementLetter,
                    (string) $request->input('engagement_digital_signature'),
                    $validated['onboarding_notes'] ?? null,
                    'uploaded',
                    $engagementSignatureVisual['image_path']
                );
            }
        } catch (\RuntimeException $exception) {
            $this->cleanupSignatureVisual($engagementSignatureVisual);
            return redirect()->route('trademark.status', $application->id)
                ->with('error', $exception->getMessage());
        }

        $this->markSignedDocumentSubmitted($signedEngagementLetter, $validated['onboarding_notes'] ?? null);

        $workflow->completeTask($application, 'engagement_letter_signed');
        $workflow->completeTask($application, 'signature_submitted');
        $this->storeOnboardingMeta($application, $validated['onboarding_notes'] ?? null);
        $this->logOnboardingPackageSentForReview($application, $signedEngagementLetter);
        $workflow->refreshOnboardingStatus($application->fresh());
        $this->cleanupSignatureVisual($engagementSignatureVisual);
        $this->notifyAdminsOfApplicantAction(
            $application,
            'Applicant submitted signed Engagement Letter',
            $validated['onboarding_notes'] ?? 'The signed Engagement Letter was submitted for admin verification.'
        );

        return redirect()->route('trademark.status', $application->id)
            ->with('success', 'Signed Engagement Letter submitted successfully.');
    }

    public function saveOnboardingPhysicalDraft(Request $request, $applicationId, string $documentType)
    {
        $application = Auth::user()->applications()->findOrFail($applicationId);

        if ($application->current_status !== TrademarkWorkflow::ONBOARDING_PENDING) {
            abort(422, 'Onboarding document uploads are only available during onboarding.');
        }

        $fileFields = [];

        if (!array_key_exists($documentType, $fileFields)) {
            abort(404);
        }

        $field = $fileFields[$documentType];

        $request->validate([
            $field => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:15360',
        ]);

        $sourceDocument = $application->documents()
            ->where('document_type', $documentType)
            ->latest('id')
            ->first();

        if (!$sourceDocument) {
            abort(422, ucfirst($documentType) . ' is not available yet. Please wait for the admin to upload it.');
        }

        $signedDocument = $this->storePhysicalSignedOnboardingDocument(
            $application,
            $sourceDocument,
            $request->file($field),
            null,
            'draft',
            'Physically signed document attached by user as a draft.'
        );

        return response()->json([
            'success' => true,
            'document_id' => $signedDocument->id,
            'file_name' => $request->file($field)->getClientOriginalName(),
            'view_url' => route('user.document.view', $signedDocument->id),
            'download_url' => route('user.document.download', $signedDocument->id),
            'message' => 'Signed document saved. It will persist until you submit the onboarding package.',
        ]);
    }

    public function resubmitOnboardingDocument(
        Request $request,
        $applicationId,
        $documentType,
        TrademarkWorkflowService $workflow,
        PdfSignatureStampService $pdfSignatureStampService
    )
    {
        $application = Auth::user()->applications()->findOrFail($applicationId);

        if ($application->current_status !== TrademarkWorkflow::ONBOARDING_PENDING) {
            abort(422, 'Onboarding document reupload is only available during onboarding.');
        }

        $allowedDocumentTypes = [
            'engagement_letter',
        ];

        if (!in_array($documentType, $allowedDocumentTypes, true)) {
            abort(404);
        }

        $validationRules = [
            'reupload_notes' => 'nullable|string|max:1000',
            'signature_mode' => 'required|in:draw,type,upload',
            'digital_signature' => 'required|string|max:255',
            'signature_image_data' => 'nullable|string',
            'signature_image' => 'nullable|file|mimetypes:image/png,image/jpeg,image/pjpeg,image/jpg|max:2048',
        ];

        $validated = $request->validate($validationRules);
        $sourceDocument = $application->documents()
            ->where('document_type', $documentType)
            ->latest('id')
            ->first();

        if (!$sourceDocument) {
            return redirect()->route('trademark.status', $application->id)
                ->with('error', ucfirst(str_replace('_', ' ', $documentType)) . ' is not available yet. Please ask the admin to upload it again.');
        }

        $signatureVisual = $this->prepareSignatureVisual($request, $validated['signature_mode'], '', $application, $documentType);

        try {
            $pdfSignatureStampService->generateSignedWorkflowPdf(
                $application,
                $sourceDocument,
                $validated['digital_signature'],
                $validated['reupload_notes'] ?? null,
                'reuploaded',
                $signatureVisual['image_path']
            );
        } catch (\RuntimeException $exception) {
            $this->cleanupSignatureVisual($signatureVisual);
            return redirect()->route('trademark.status', $application->id)
                ->with('error', $exception->getMessage());
        }

        if ($documentType === 'engagement_letter') {
            $workflow->completeTask($application, 'engagement_letter_signed');
        }

        if (Schema::hasColumn('applications', 'workflow_meta')) {
            $meta = $application->workflow_meta ?? [];
            $meta['onboarding_reupload_document'] = $documentType;
            $meta['onboarding_reupload_submitted_at'] = now()->toDateTimeString();
            $application->update(['workflow_meta' => $meta]);
        }

        $workflow->refreshOnboardingStatus($application->fresh());
        $this->cleanupSignatureVisual($signatureVisual);
        $this->notifyAdminsOfApplicantAction(
            $application,
            'Applicant resubmitted onboarding document',
            ucfirst(str_replace('_', ' ', $documentType)) . ' signature was reapplied and submitted for review.'
        );

        return redirect()->route('trademark.status', $application->id)
            ->with('success', ucfirst(str_replace('_', ' ', $documentType)) . ' signature reapplied successfully.');
    }

    private function storeOnboardingMeta(Application $application, ?string $notes = null): void
    {
        if (!Schema::hasColumn('applications', 'workflow_meta')) {
            return;
        }

        $meta = $application->workflow_meta ?? [];
        $meta['onboarding_package_submitted_at'] = now()->toDateTimeString();

        if ($notes) {
            $meta['onboarding_package_notes'] = $notes;
        }

        $application->update([
            'workflow_meta' => $meta,
        ]);
    }

    private function logOnboardingPackageSentForReview(Application $application, Document $signedEngagementLetter): void
    {
        if (!Schema::hasTable('application_status_logs')) {
            return;
        }

        ApplicationStatusLog::create([
            'application_id' => $application->id,
            'from_status' => $application->current_status,
            'to_status' => $application->current_status,
            'actor_type' => 'user',
            'actor_id' => Auth::id(),
            'reason' => 'Signed Engagement Letter submitted to admin for verification.',
            'metadata' => [
                'event' => 'onboarding_package_sent_for_review',
                'title' => 'Onboarding Package Sent for Review',
                'engagement_letter_document_id' => $signedEngagementLetter->id,
            ],
        ]);
    }

    private function markSignedDocumentSubmitted(Document $document, ?string $notes = null): void
    {
        if ($document->status === 'verified' || $document->verified_at) {
            return;
        }

        $isReuploaded = $document->status === 'reuploaded';
        $note = $isReuploaded
            ? 'Signed document reuploaded to admin for approval.'
            : 'Signed document submitted to admin for approval.';

        if ($notes) {
            $note .= ' Notes: ' . $notes;
        }

        $document->update([
            'status' => $isReuploaded ? 'reuploaded' : 'uploaded',
            'verification_notes' => $note,
        ]);
    }

    private function storePhysicalSignedOnboardingDocument(
        Application $application,
        Document $sourceDocument,
        $file,
        ?string $notes = null,
        string $status = 'uploaded',
        ?string $baseNote = null
    ): Document
    {
        $signedDocumentType = $sourceDocument->document_type . ' (Signed)';
        $previousSignedDocuments = $application->documents()
            ->where('document_type', $signedDocumentType)
            ->get();
        $isReuploadSubmission = $previousSignedDocuments->contains(fn (Document $document) => $document->status === 'reupload_requested');

        $previousSignedDocuments
            ->each(function (Document $document) {
                if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                    Storage::disk('public')->delete($document->file_path);
                }

                $document->delete();
            });

        if ($status === 'uploaded' && $isReuploadSubmission) {
            $status = 'reuploaded';
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $filename = 'signed-' . $sourceDocument->document_type . '-' . $application->id . '-' . now()->timestamp . '-' . uniqid() . '.' . $extension;
        $path = 'documents/signed/' . $filename;

        Storage::disk('public')->put($path, file_get_contents($file));

        return Document::create([
            'application_id' => $application->id,
            'user_id' => Auth::id() ?: $application->user_id,
            'document_type' => $signedDocumentType,
            'file_path' => $path,
            'file_name' => $filename,
            'file_type' => $extension,
            'file_size' => $file->getSize(),
            'status' => $status,
            'verification_notes' => ($baseNote ?: 'Physically signed document uploaded by user.') . ($notes ? ' Notes: ' . $notes : ''),
        ]);
    }

    private function notifyAdminsOfApplicantAction(Application $application, string $title, string $message): void
    {
        try {
            $user = $application->user;
            $applicationLabel = $application->application_number ?: ('Application #' . $application->id);
            $brandName = $application->brand_name ?: 'Trademark application';
            $submittedAt = now()->timezone(config('app.timezone', 'Europe/London'))->format('d M Y, h:i A T');

            $body = implode(PHP_EOL, array_filter([
                $title,
                '',
                'Applicant: ' . ($user?->name ?? 'Unknown applicant'),
                'Applicant email: ' . ($user?->email ?? 'Not available'),
                'Application: ' . $applicationLabel,
                'Brand: ' . $brandName,
                'Submitted at: ' . $submittedAt,
                '',
                'Details:',
                $message,
            ]));

            Admin::query()
                ->pluck('email')
                ->filter()
                ->unique()
                ->each(function (string $email) use ($title, $body, $applicationLabel, $application) {
                    Mail::to($email)->send(new AdminWorkflowNotification(
                        $title . ' - ' . $applicationLabel,
                        $body,
                        route('admin.view-application', $application->id)
                    ));
                });
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function prepareSignatureVisual(
        Request $request,
        string $mode,
        string $prefix = '',
        ?Application $application = null,
        ?string $documentType = null
    ): array
    {
        $fieldPrefix = $prefix ? $prefix . '_' : '';

        if ($mode === 'type') {
            return ['image_path' => null];
        }

        $directory = storage_path('app/temp-signatures');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if ($mode === 'draw') {
            $imageData = (string) $request->input($fieldPrefix . 'signature_image_data', '');
            if (!preg_match('/^data:image\/png;base64,/', $imageData)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $fieldPrefix . 'signature_image_data' => 'Please draw and apply your signature before submitting.',
                ]);
            }

            $path = $directory . '/signature-' . Auth::id() . '-' . now()->timestamp . '-' . uniqid() . '.png';
            file_put_contents($path, base64_decode(substr($imageData, strpos($imageData, ',') + 1)));

            return ['image_path' => $path];
        }

        if ($mode === 'upload') {
            $imageData = (string) $request->input($fieldPrefix . 'signature_image_data', '');
            if (preg_match('/^data:image\/png;base64,/', $imageData)) {
                $path = $directory . '/signature-' . Auth::id() . '-' . now()->timestamp . '-' . uniqid() . '.png';
                file_put_contents($path, base64_decode(substr($imageData, strpos($imageData, ',') + 1)));
                $this->validateUploadedSignatureDimensions($path, $fieldPrefix . 'signature_image_data', $application, $documentType);

                return ['image_path' => $path];
            }

            if (!$request->hasFile($fieldPrefix . 'signature_image')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $fieldPrefix . 'signature_image' => 'Please upload, crop, and apply your signature before submitting.',
                ]);
            }

            $file = $request->file($fieldPrefix . 'signature_image');
            $this->validateUploadedSignatureDimensions($file->getPathname(), $fieldPrefix . 'signature_image', $application, $documentType);
            $mimeType = strtolower((string) $file->getMimeType());
            $extension = str_contains($mimeType, 'png') ? 'png' : 'jpg';
            $path = $directory . '/signature-' . Auth::id() . '-' . now()->timestamp . '-' . uniqid() . '.' . $extension;
            $file->move($directory, basename($path));

            return ['image_path' => $path];
        }

        return ['image_path' => null];
    }

    private function validateUploadedSignatureDimensions(
        string $path,
        string $field,
        ?Application $application,
        ?string $documentType
    ): void {
        $expected = $this->expectedSignatureImageDimensions($application, $documentType);

        if (!$expected) {
            return;
        }

        $actual = @getimagesize($path);
        $actualWidth = (int) ($actual[0] ?? 0);
        $actualHeight = (int) ($actual[1] ?? 0);

        if ($actualWidth !== $expected['width'] || $actualHeight !== $expected['height']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $field => 'Please upload a signature image sized exactly ' . $expected['width'] . ' x ' . $expected['height'] . 'px.',
            ]);
        }
    }

    private function expectedSignatureImageDimensions(?Application $application, ?string $documentType): ?array
    {
        if (!$application || !$documentType) {
            return null;
        }

        $signatureField = collect(data_get($application->workflow_meta ?? [], 'signature_fields.' . $documentType, []))
            ->firstWhere('type', 'signature');

        if (!is_array($signatureField)) {
            return null;
        }

        $width = (float) ($signatureField['width'] ?? 0);
        $height = (float) ($signatureField['height'] ?? 0);

        if ($width <= 0 || $height <= 0) {
            return null;
        }

        return [
            'width' => max((int) round(($width / 210) * 760), 1),
            'height' => max((int) round(($height / 297) * ((760 * 297) / 210)), 1),
        ];
    }

    private function cleanupSignatureVisual(array $signatureVisual): void
    {
        $path = $signatureVisual['image_path'] ?? null;

        if ($path && is_file($path)) {
            @unlink($path);
        }
    }

    private function documentRequiresSignature(Application $application, string $documentType): bool
    {
        $fields = data_get($application->workflow_meta ?? [], 'signature_fields.' . $documentType, []);

        if (!is_array($fields) || $fields === []) {
            return $documentType === 'engagement_letter';
        }

        return collect($fields)->contains(
            fn ($field) => is_array($field) && ($field['type'] ?? null) === 'signature'
        );
    }
}
