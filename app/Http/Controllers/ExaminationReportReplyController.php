<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentVerificationException;
use App\Mail\EventNotification;
use App\Models\Admin;
use App\Models\DiscountCoupon;
use App\Models\ExaminationReplyDocument;
use App\Models\ExaminationReplyDraft;
use App\Models\ExaminationReplyNotificationLog;
use App\Models\ExaminationReplyRequestedDocument;
use App\Models\ExaminationReplyStageRequest;
use App\Models\ExaminationReplyStageDraft;
use App\Models\ExaminationReplyStatusHistory;
use App\Models\ExaminationReportReplyCase;
use App\Models\Notification;
use App\Services\RazorpayPaymentVerifier;
use App\Support\ExaminationReportReplyWorkflow as Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ExaminationReportReplyController extends Controller
{
    private const ALLOWED_UPLOADS = 'pdf,jpg,jpeg,png,doc,docx';
    private const CLIENT_UPLOAD_MAX_KB = 10240;
    private const ADMIN_UPLOAD_MAX_KB = 15360;

    public function landing(): View
    {
        return view('examination-reply.landing');
    }

    public function create(): View
    {
        return view('examination-reply.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'applicant_name' => ['required', 'string', 'max:255'],
            'trademark_name' => ['required', 'string', 'max:255'],
            'application_number' => ['required', 'string', 'max:120'],
            'trademark_class' => ['required', 'string', 'max:120'],
            'application_filing_date' => ['required', 'date', 'before_or_equal:today'],
            'current_status' => ['required', 'in:Objected,Awaiting reply to examination report,Hearing required,Not sure'],
            'filed_through_legalbruz' => ['required', 'boolean'],
            'exam_report' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:' . self::CLIENT_UPLOAD_MAX_KB],
            'tma_acknowledgment' => ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:' . self::CLIENT_UPLOAD_MAX_KB],
            'logo_device_mark' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:' . self::CLIENT_UPLOAD_MAX_KB],
            'exam_report_receipt_date' => ['required', 'date', 'after_or_equal:application_filing_date', 'before_or_equal:today'],
        ]);

        $deadline = Carbon::parse($data['exam_report_receipt_date'])->addDays(30);
        $caseData = collect($data)->except([
            'exam_report',
            'tma_acknowledgment',
            'logo_device_mark',
        ])->all();

        $case = ExaminationReportReplyCase::create([
            ...$caseData,
            'user_id' => Auth::id(),
            'case_number' => 'ERR-' . now()->format('Ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'reply_deadline' => $deadline,
            'deadline_status' => Workflow::deadlineStatus($deadline),
            'current_admin_status' => Workflow::ADMIN_APPLICATION_RECEIVED,
            'current_client_stage' => Workflow::CLIENT_REQUEST_RECEIVED,
            'case_status' => 'active',
            'payment_status' => 'pending',
        ]);

        $this->storeDocument($case, $request->file('exam_report'), 'examination_report', 'client', 'client');
        if ($request->hasFile('tma_acknowledgment')) {
            $this->storeDocument($case, $request->file('tma_acknowledgment'), 'tm_a_acknowledgment', 'client', 'client');
        }
        if ($request->hasFile('logo_device_mark')) {
            $this->storeDocument($case, $request->file('logo_device_mark'), 'logo_device_mark', 'client', 'client');
        }

        $this->recordStatus($case, null, Workflow::ADMIN_APPLICATION_RECEIVED, 'Trademark Objection Reply request received.', 'client');
        $this->notifyClient($case, 'order_received');

        return redirect()->route('examination-reply.show', $case)
            ->with('success', 'Your Trademark Objection Reply request has been received.');
    }

    public function show(ExaminationReportReplyCase $case): View
    {
        $this->authorizeClient($case);
        $case->load(['documents' => fn ($query) => $query->where('is_draft', false), 'requestedDocuments.stageRequest', 'statusHistories', 'stageRequests', 'notificationLogs', 'drafts']);
        $this->completeEvidenceCollectionIfReviewed($case, 'system');
        $case->refresh()->load(['documents' => fn ($query) => $query->where('is_draft', false), 'requestedDocuments.stageRequest', 'statusHistories', 'stageRequests', 'notificationLogs', 'drafts']);

        return view('examination-reply.show', [
            'case' => $case,
            'timeline' => Workflow::clientTimeline($case),
            'evidenceTypes' => Workflow::evidenceDocumentTypes(),
            'razorpayKeyId' => config('razorpay.key_id'),
            'paymentCoupons' => DiscountCoupon::availableForPayment('objection_reply', Auth::id()),
            'autoApplyCoupon' => DiscountCoupon::autoApplyForPayment('objection_reply', Auth::id()),
        ]);
    }

    public function submitEvidence(Request $request, ExaminationReportReplyCase $case): RedirectResponse
    {
        $this->authorizeClient($case);

        $hasEvidenceIntake = ! empty($case->evidence_intake);
        $rules = [
            'usage_status' => [$hasEvidenceIntake ? 'nullable' : 'required', 'in:proposed,used'],
            'first_use_date' => ['nullable', 'required_if:usage_status,used', 'date'],
            'used_continuously' => [$hasEvidenceIntake ? 'nullable' : 'required', 'in:yes,no'],
            'annual_sales' => ['nullable', 'string', 'max:120'],
            'advertising_expenses' => ['nullable', 'string', 'max:120'],
            'previous_objection' => [$hasEvidenceIntake ? 'nullable' : 'required', 'in:yes,no'],
            'similar_brands_known' => [$hasEvidenceIntake ? 'nullable' : 'required', 'in:yes,no'],
            'message_to_admin' => ['nullable', 'string', 'max:2000'],
            'requested_documents' => ['nullable', 'array'],
            'requested_documents.*' => ['file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:' . self::CLIENT_UPLOAD_MAX_KB],
        ];

        $data = $request->validate($rules);
        $uploaded = 0;

        foreach ($request->file('requested_documents', []) as $requestedId => $file) {
            $requested = $case->requestedDocuments()->find($requestedId);
            if (!$requested) {
                continue;
            }

            $requested->loadMissing('stageRequest');
            $documentType = Str::slug($requested->document_name, '_');
            $isReupload = $case->documents()
                ->where('uploaded_by', 'client')
                ->where('document_type', $documentType)
                ->whereIn('review_status', ['reupload_requested', 'needs_better_copy'])
                ->exists();
            $document = $this->storeDocument(
                $case,
                $file,
                $documentType,
                'client',
                'client',
                $isReupload ? 'reuploaded' : 'uploaded',
                ['stage_key' => 'evidence_collection']
            );
            $requested->update([
                'is_uploaded_by_client' => true,
                'uploaded_file_path' => $document->file_path,
            ]);
            $uploaded++;
        }

        $existingEvidenceIntake = $case->evidence_intake ?? [];
        $case->update([
            'evidence_intake' => [
                'usage_status' => $data['usage_status'] ?? ($existingEvidenceIntake['usage_status'] ?? null),
                'first_use_date' => $data['first_use_date'] ?? ($existingEvidenceIntake['first_use_date'] ?? null),
                'used_continuously' => $data['used_continuously'] ?? ($existingEvidenceIntake['used_continuously'] ?? null),
                'annual_sales' => $data['annual_sales'] ?? ($existingEvidenceIntake['annual_sales'] ?? null),
                'advertising_expenses' => $data['advertising_expenses'] ?? ($existingEvidenceIntake['advertising_expenses'] ?? null),
                'previous_objection' => $data['previous_objection'] ?? ($existingEvidenceIntake['previous_objection'] ?? null),
                'similar_brands_known' => $data['similar_brands_known'] ?? ($existingEvidenceIntake['similar_brands_known'] ?? null),
            ],
        ]);

        if ($uploaded === 0 && $case->requestedDocuments()->where('is_required', true)->where('is_uploaded_by_client', false)->exists()) {
            return back()->with('error', 'Please upload the requested evidence documents before submitting.');
        }

        $clientAdminMessage = trim((string) ($data['message_to_admin'] ?? ''));
        $statusNote = 'Client submitted requested evidence.' . ($clientAdminMessage !== '' ? "\n\nMessage to admin: " . $clientAdminMessage : '');
        $this->transition($case, Workflow::ADMIN_EVIDENCE_SUBMITTED, $statusNote, 'client');
        $this->notifyAdmins($case, 'Evidence submitted for Trademark Objection Reply case ' . $case->case_number . ($clientAdminMessage !== '' ? "\n\nClient message:\n" . $clientAdminMessage : ''));

        return back()->with('success', 'Your documents have been submitted. Our legal team will review them.');
    }

    public function uploadRequestedDocuments(Request $request, ExaminationReportReplyCase $case): RedirectResponse
    {
        $this->authorizeClient($case);

        $request->validate([
            'requested_documents' => ['required', 'array', 'min:1'],
            'requested_documents.*' => ['file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:' . self::CLIENT_UPLOAD_MAX_KB],
            'message_to_admin' => ['nullable', 'string', 'max:2000'],
        ]);
        $clientAdminMessage = trim((string) $request->input('message_to_admin', ''));

        $uploaded = 0;
        $isRegistryClarificationUpload = false;
        foreach ($request->file('requested_documents', []) as $requestedId => $file) {
            $requested = $case->requestedDocuments()->find($requestedId);
            if (!$requested) {
                continue;
            }

            $requested->loadMissing('stageRequest');
            $isCurrentRegistryClarification = $requested->stageRequest?->to_stage === Workflow::CLIENT_AWAITING_REGISTRY_UPDATE;
            if ($isCurrentRegistryClarification) {
                $isRegistryClarificationUpload = true;
            }

            $documentType = Str::slug($requested->document_name, '_');
            $isReupload = $case->documents()
                ->where('uploaded_by', 'client')
                ->where('document_type', $documentType)
                ->whereIn('review_status', ['reupload_requested', 'needs_better_copy'])
                ->exists();
            $document = $this->storeDocument(
                $case,
                $file,
                $documentType,
                'client',
                'client',
                $isReupload ? 'reuploaded' : 'uploaded',
                ['stage_key' => $isCurrentRegistryClarification ? 'awaiting_registry_update' : 'evidence_collection']
            );
            $requested->update([
                'is_uploaded_by_client' => true,
                'uploaded_file_path' => $document->file_path,
            ]);
            $uploaded++;
        }

        if ($uploaded === 0) {
            return back()->withErrors(['requested_documents' => 'Please upload at least one requested document.'])->withInput();
        }

        $nextStatus = match (true) {
            $isRegistryClarificationUpload => Workflow::ADMIN_FURTHER_ACTION_REQUIRED,
            $case->current_admin_status === Workflow::ADMIN_DOCUMENTS_PENDING => Workflow::ADMIN_APPLICATION_RECEIVED,
            default => Workflow::ADMIN_EVIDENCE_SUBMITTED,
        };
        $statusNote = 'Client uploaded requested documents.' . ($clientAdminMessage !== '' ? "\n\nMessage to admin: " . $clientAdminMessage : '');
        $this->transition($case, $nextStatus, $statusNote, 'client');
        $this->notifyAdmins($case, 'Requested documents uploaded for Trademark Objection Reply case ' . $case->case_number . ($clientAdminMessage !== '' ? "\n\nClient message:\n" . $clientAdminMessage : ''));

        return back()->with('success', 'Requested documents uploaded successfully.');
    }

    public function approveDraft(Request $request, ExaminationReportReplyCase $case): RedirectResponse
    {
        $this->authorizeClient($case);

        if (!$case->drafts()->where('status', 'client_approval_pending')->exists()) {
            return back()->with('error', 'A draft is not currently pending approval.');
        }

        $case->drafts()->latest('id')->first()?->update(['status' => 'approved']);
        $case->update(['client_approval_status' => 'approved']);
        $this->transition($case, Workflow::ADMIN_READY_FOR_FILING, 'Client approved the objection reply draft.', 'client');
        $this->notifyAdmins($case, 'Client approved objection reply draft for case ' . $case->case_number . '.');

        return back()->with('success', 'Draft approved. Our team can now proceed with filing.');
    }

    public function requestDraftChanges(Request $request, ExaminationReportReplyCase $case): RedirectResponse
    {
        $this->authorizeClient($case);

        if (!$case->drafts()->where('status', 'client_approval_pending')->exists()) {
            return back()->with('error', 'A draft is not currently pending review.');
        }

        $data = $request->validate(['change_request' => ['required', 'string', 'min:10', 'max:1500']]);

        $case->drafts()->latest('id')->first()?->update([
            'status' => 'changes_requested',
            'client_comment' => $data['change_request'],
        ]);
        $case->update(['client_approval_status' => 'changes_requested']);
        $this->transition($case, Workflow::ADMIN_CHANGES_REQUESTED, $data['change_request'], 'client');
        $this->notifyAdmins($case, 'Client requested draft changes for objection reply case ' . $case->case_number . '.');

        return back()->with('success', 'Your change request has been sent to the drafting team.');
    }

    public function createPaymentOrder(Request $request, ExaminationReportReplyCase $case): JsonResponse
    {
        $this->authorizeClient($case);

        $data = $request->validate([
            'success_disclaimer' => ['accepted'],
            'discount_coupon_id' => ['nullable', 'integer'],
        ], [
            'success_disclaimer.accepted' => 'Please confirm that you understand Legal Bruz cannot guarantee acceptance or registration.',
        ]);

        if ($case->payment_status === 'paid') {
            return response()->json(['status' => 'error', 'message' => 'Payment is already complete.'], 422);
        }

        $pricing = $this->discountedPackagePricing(
            'objection_reply',
            (float) $case->package_price,
            isset($data['discount_coupon_id']) ? (int) $data['discount_coupon_id'] : null
        );
        $amount = $pricing['payable_amount'];
        if ($amount <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Package amount is not assigned yet.'], 422);
        }

        try {
            $razorpayKeyId = config('razorpay.key_id');
            $razorpaySecret = config('razorpay.key_secret');
            if (!$razorpayKeyId || !$razorpaySecret) {
                throw new \RuntimeException('Razorpay credentials are not configured.');
            }

            $amountInPaise = (int) round($amount * 100);
            $receipt = 'exam_reply_' . $case->id . '_' . time();
            $ch = curl_init('https://api.razorpay.com/v1/orders');
            curl_setopt($ch, CURLOPT_USERPWD, "$razorpayKeyId:$razorpaySecret");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'amount' => $amountInPaise,
                'currency' => config('razorpay.currency', 'INR'),
                'receipt' => $receipt,
                'description' => 'Examination Report Reply - ' . $case->case_number,
                'notes' => [
                    'case_id' => (string) $case->id,
                    'case_number' => (string) $case->case_number,
                    'payment_type' => 'objection_reply',
                    'original_amount' => (string) $pricing['original_amount'],
                    'discount_amount' => (string) $pricing['discount_amount'],
                    'coupon' => (string) ($pricing['coupon_label'] ?? ''),
                ],
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false || $curlError || !in_array($httpCode, [200, 201], true)) {
                throw new \RuntimeException($curlError ?: 'Failed to create Razorpay order.');
            }

            $order = json_decode($response, true);
            if (!is_array($order) || empty($order['id'])) {
                throw new \RuntimeException('Razorpay returned an invalid order response.');
            }
            $orders = $request->session()->get('examination_reply_payment_orders', []);
            $orders[$order['id']] = [
                'case_id' => $case->id,
                'amount' => $amountInPaise,
                'original_amount' => $pricing['original_amount'],
                'payable_amount' => $pricing['payable_amount'],
                'discount_amount' => $pricing['discount_amount'],
                'coupon_label' => $pricing['coupon_label'],
            ];
            $request->session()->put('examination_reply_payment_orders', $orders);

            return response()->json([
                'status' => 'success',
                'order_id' => $order['id'],
                'amount' => $amountInPaise,
                'currency' => config('razorpay.currency', 'INR'),
                'key' => $razorpayKeyId,
                'user_name' => Auth::user()->name,
                'user_email' => Auth::user()->email,
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 500);
        }
    }

    public function verifyPaymentSignature(Request $request, ExaminationReportReplyCase $case, RazorpayPaymentVerifier $verifier): JsonResponse
    {
        $this->authorizeClient($case);
        $data = $request->validate([
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        $orders = $request->session()->get('examination_reply_payment_orders', []);
        $order = $orders[$data['razorpay_order_id']] ?? null;
        if (!$order || (int) ($order['case_id'] ?? 0) !== (int) $case->id) {
            return response()->json(['status' => 'error', 'message' => 'This Razorpay order does not belong to the current objection reply case.'], 422);
        }

        try {
            $verifier->verifyCaptured(
                $data['razorpay_payment_id'],
                $data['razorpay_order_id'],
                $data['razorpay_signature'],
                $data['razorpay_order_id'],
                (int) ($order['amount'] ?? 0),
                (string) config('razorpay.currency', 'GBP'),
            );
        } catch (PaymentVerificationException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], $exception->httpStatus());
        }

        $case->update([
            'payment_status' => 'paid',
            'payment_reference' => $data['razorpay_order_id'],
            'transaction_id' => $data['razorpay_payment_id'],
            'paid_at' => now(),
            'original_package_price' => $order['original_amount'] ?? $case->package_price,
            'paid_amount' => $order['payable_amount'] ?? $case->package_price,
            'discount_amount' => $order['discount_amount'] ?? 0,
            'coupon_label' => $order['coupon_label'] ?? null,
        ]);
        $this->transition($case, Workflow::ADMIN_PAYMENT_COMPLETED, 'Client completed objection reply payment.', 'client');
        $this->notifyAdmins($case, 'Payment completed for Trademark Objection Reply case ' . $case->case_number . '.');
        $this->notifyClient($case, null, 'Your payment has been completed successfully. The payment invoice has been emailed to you and is also available in your case dashboard.');

        try {
            $case->refresh()->loadMissing('user');
            $issuedAt = $case->paid_at;
            $invoiceName = 'objection-reply-invoice-' . $case->case_number . '.pdf';
            $invoicePdf = \PDF::loadView('examination-reply.payment-invoice', [
                'case' => $case,
                'user' => $case->user,
                'invoiceNumber' => 'ERR-INV-' . $issuedAt->format('Y') . '-' . $case->id,
                'issuedAt' => $issuedAt,
                'firmName' => config('app.name', 'Legal Bruz'),
                'firmEmail' => config('mail.from.address'),
            ])->setPaper('a4');
            $this->emailClient(
                $case,
                'Payment Successful – Trademark Objection Reply',
                'Your payment for Trademark Objection Reply case ' . $case->case_number . ' was completed successfully. Your invoice is attached.',
                'Open Case',
                [[
                    'data' => $invoicePdf->output(),
                    'name' => $invoiceName,
                    'mime' => 'application/pdf',
                ]]
            );
        } catch (Throwable $exception) {
            Log::warning('Examination reply payment invoice email could not be prepared.', [
                'case_id' => $case->id,
                'error' => $exception->getMessage(),
            ]);
            ExaminationReplyNotificationLog::create([
                'case_id' => $case->id,
                'notification_type' => 'Payment Successful – Trademark Objection Reply',
                'channel' => 'email',
                'message' => 'Payment succeeded, but the invoice email could not be prepared.',
                'sent_at' => null,
                'status' => 'failed',
            ]);
        }

        unset($orders[$data['razorpay_order_id']]);
        $request->session()->put('examination_reply_payment_orders', $orders);

        return response()->json([
            'status' => 'success',
            'redirect_url' => route('examination-reply.show', $case),
        ]);
    }

    public function viewPaymentInvoice(ExaminationReportReplyCase $case)
    {
        $this->authorizeClient($case);

        abort_unless(
            $case->payment_status === 'paid'
            && filled($case->payment_reference)
            && filled($case->transaction_id)
            && filled($case->paid_at),
            404
        );

        $case->loadMissing('user');
        $issuedAt = $case->paid_at;

        $pdf = \PDF::loadView('examination-reply.payment-invoice', [
            'case' => $case,
            'user' => $case->user,
            'invoiceNumber' => 'ERR-INV-' . $issuedAt->format('Y') . '-' . $case->id,
            'issuedAt' => $issuedAt,
            'firmName' => config('app.name', 'Legal Bruz'),
            'firmEmail' => config('mail.from.address'),
        ])->setPaper('a4');

        return $pdf->stream('objection-reply-invoice-' . $case->case_number . '.pdf');
    }

    public function adminIndex(Request $request): View
    {
        $cases = ExaminationReportReplyCase::query()
            ->with('documents')
            ->when($request->filled('status'), fn ($query) => $query->where('current_admin_status', $request->string('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.trim((string) $request->string('search')).'%';
                $query->where(function ($query) use ($search) {
                    $query->where('case_number', 'like', $search)
                        ->orWhere('application_number', 'like', $search)
                        ->orWhere('trademark_name', 'like', $search)
                        ->orWhere('applicant_name', 'like', $search);
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.examination-reply.index', [
            'cases' => $cases,
            'statuses' => $this->adminStatuses(),
        ]);
    }

    public function adminShow(ExaminationReportReplyCase $case): View
    {
        $case->load(['user', 'documents', 'requestedDocuments.stageRequest', 'statusHistories', 'drafts', 'stageDrafts']);
        if ($case->current_admin_status === Workflow::ADMIN_ACKNOWLEDGMENT_UPLOADED) {
            $this->transition(
                $case,
                Workflow::ADMIN_AWAITING_REGISTRY_REVIEW,
                'Filing acknowledgment is available. The application is now awaiting review by the Trademark Registry.',
                'system'
            );
        }
        $this->completeEvidenceCollectionIfReviewed($case, 'system');
        $case->refresh()->load(['user', 'documents', 'requestedDocuments.stageRequest', 'statusHistories', 'drafts', 'stageDrafts']);

        return view('admin.examination-reply.show', [
            'case' => $case,
            'statuses' => $this->adminStatuses(),
            'timeline' => Workflow::clientTimeline($case),
            'evidenceTypes' => Workflow::evidenceDocumentTypes(),
        ]);
    }

    public function adminSaveStageDraft(Request $request, ExaminationReportReplyCase $case): RedirectResponse
    {
        $stageKey = $this->stageKey($request, $case);

        foreach (['draft_file', 'filing_acknowledgment'] as $replaceableInput) {
            if (!$request->hasFile($replaceableInput)) {
                continue;
            }

            $case->documents()
                ->where('stage_key', $stageKey)
                ->where('is_draft', true)
                ->where('metadata->input_name', $replaceableInput)
                ->get()
                ->each(function (ExaminationReplyDocument $document) {
                    Storage::disk('public')->delete($document->file_path);
                    $document->delete();
                });
        }

        ExaminationReplyStageDraft::updateOrCreate(
            [
                'case_id' => $case->id,
                'stage_key' => $stageKey,
            ],
            [
                'admin_status' => $case->current_admin_status,
                'payload' => $this->draftPayload($request),
                'created_by' => Auth::guard('admin')->id(),
                'saved_at' => now(),
            ]
        );

        $this->storeDraftFiles($request, $case, $stageKey);

        return back()->with('success', 'Draft saved. Nothing has been shared with the client yet.');
    }

    public function adminSaveInternalNote(Request $request, ExaminationReportReplyCase $case): RedirectResponse
    {
        $data = $request->validate([
            'internal_client_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $case->update($data);
        $this->recordStatus($case, $case->current_admin_status, $case->current_admin_status, 'Internal client note updated.', 'admin');

        return back()->with('success', 'Internal client note saved.');
    }

    public function adminStoreAdditionalDocuments(Request $request, ExaminationReportReplyCase $case): RedirectResponse
    {
        $data = $request->validate([
            'additional_documents' => ['nullable', 'array', 'max:10'],
            'additional_documents.*' => ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:' . self::ADMIN_UPLOAD_MAX_KB],
            'visibility' => ['nullable', 'in:client,admin'],
            'document_type' => ['nullable', 'string', 'max:120'],
            'optional_documents' => ['nullable', 'array', 'max:10'],
            'optional_documents.*' => ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:' . self::ADMIN_UPLOAD_MAX_KB],
            'optional_document_names' => ['nullable', 'array'],
            'optional_document_names.*' => ['nullable', 'string', 'max:120'],
            'optional_document_types' => ['nullable', 'array'],
            'optional_document_types.*' => ['nullable', 'string', 'max:120'],
            'optional_attachment_notes' => ['nullable', 'array'],
            'optional_attachment_notes.*' => ['nullable', 'string', 'max:3000'],
            'optional_document_remarks' => ['nullable', 'array'],
            'optional_document_remarks.*' => ['nullable', 'string', 'max:3000'],
            'optional_document_visibilities' => ['nullable', 'array'],
            'optional_document_visibilities.*' => ['nullable', 'in:client,admin'],
        ]);

        $stageKey = $this->stageKey($request, $case);
        $storedCount = $this->saveAdditionalDocumentsFromRequest($request, $case, $stageKey, true);

        if ($storedCount === 0) {
            return back()->withErrors(['optional_documents' => 'Please attach at least one document.'])->withInput();
        }

        $this->recordStatus($case, $case->current_admin_status, $case->current_admin_status, 'Additional documents attached.', 'admin');
        $this->clearStageDraft($case, $stageKey);

        return back()->with('success', 'Additional documents attached.');
    }

    public function adminReviewDocuments(Request $request, ExaminationReportReplyCase $case): RedirectResponse
    {
        $data = $request->validate([
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['integer'],
            'action' => ['required', 'in:reviewed,reupload_requested'],
            'note' => ['required_if:action,reupload_requested', 'nullable', 'string', 'max:1500'],
        ]);

        $documents = $case->documents()
            ->whereIn('id', $data['document_ids'])
            ->where('uploaded_by', 'client')
            ->get();

        if ($documents->isEmpty()) {
            return back()->withErrors(['documents' => 'Please select at least one client-uploaded document.']);
        }

        $case->loadMissing('requestedDocuments.stageRequest');
        $uploadedEvidencePaths = $this->currentEvidenceRequestedDocuments($case)
            ->where('is_uploaded_by_client', true)
            ->pluck('uploaded_file_path')
            ->filter()
            ->values();
        $isEvidenceReview = $documents->isNotEmpty()
            && $documents->every(fn ($document) => $uploadedEvidencePaths->contains($document->file_path));
        $reviewNote = trim((string) ($data['note'] ?? ''));
        $documentLabels = [];

        foreach ($documents as $document) {
            $documentLabel = Str::headline($document->document_type);
            $documentLabels[] = $documentLabel;

            $document->update([
                'review_status' => $data['action'],
                'review_note' => $reviewNote ?: null,
            ]);

            if ($data['action'] === 'reupload_requested') {
                if ($isEvidenceReview) {
                    $case->requestedDocuments()
                        ->where('uploaded_file_path', $document->file_path)
                        ->update([
                            'is_uploaded_by_client' => false,
                            'uploaded_file_path' => null,
                        ]);
                } else {
                    ExaminationReplyRequestedDocument::firstOrCreate(
                        [
                            'case_id' => $case->id,
                            'document_name' => $documentLabel,
                            'is_uploaded_by_client' => false,
                        ],
                        [
                            'is_required' => true,
                        ]
                    );
                }
            }
        }

        if ($data['action'] === 'reupload_requested') {
            $message = 'The following '
                . ($isEvidenceReview ? 'evidence document(s)' : 'Examination Report Reply document(s)')
                . " need to be reuploaded:\n\n- "
                . implode("\n- ", $documentLabels)
                . "\n\nIssues to fix:\n" . $reviewNote;

            $this->transition(
                $case,
                $isEvidenceReview ? Workflow::ADMIN_EVIDENCE_REQUESTED : Workflow::ADMIN_DOCUMENTS_PENDING,
                ($isEvidenceReview ? 'Evidence rejected for reupload: ' : 'Documents rejected for reupload: ') . implode(', ', $documentLabels),
                'admin'
            );
            $this->notifyClient($case, $isEvidenceReview ? 'evidence_requested' : 'documents_pending', $message);
            $this->emailClient(
                $case,
                $isEvidenceReview ? 'Evidence Needs Reupload' : 'Documents Need Reupload',
                $message,
                $isEvidenceReview ? 'Reupload Evidence' : 'Reupload Documents'
            );

            return back()->with('success', $isEvidenceReview
                ? 'Selected evidence was sent back to the client for reupload.'
                : 'Selected documents were sent back to the client for reupload.');
        }

        $message = "The following Examination Report Reply document(s) have been reviewed and accepted:\n\n- " . implode("\n- ", $documentLabels);
        if ($reviewNote !== '') {
            $message .= "\n\nReview note:\n" . $reviewNote;
        }

        $this->notifyClient($case, null, $message);
        $this->emailClient($case, 'Documents Reviewed', $message, 'View Case Status');

        if ($this->completeEvidenceCollectionIfReviewed($case)) {
            return back()->with('success', 'Selected evidence marked as reviewed. Evidence Collection is completed and Legal Review is now active.');
        }

        $allClientDocumentsReviewed = ! $case->documents()
            ->where('uploaded_by', 'client')
            ->where(function ($query) {
                $query->whereNull('review_status')
                    ->orWhereNotIn('review_status', ['reviewed', 'accepted', 'reupload_requested', 'needs_better_copy']);
            })
            ->exists();
        $shouldStartReview = in_array($case->current_admin_status, [
            Workflow::ADMIN_APPLICATION_RECEIVED,
            Workflow::ADMIN_DOCUMENTS_PENDING,
        ], true) && $allClientDocumentsReviewed;

        if ($shouldStartReview) {
            $this->transition(
                $case,
                Workflow::ADMIN_REPORT_UNDER_REVIEW,
                'Documents reviewed and accepted. Examination Report review started.',
                'admin'
            );

            return back()->with('success', 'Selected documents marked as reviewed. Examination Report Review is now active.');
        }

        $this->recordStatus(
            $case,
            $case->current_admin_status,
            $case->current_admin_status,
            'Documents reviewed and accepted: ' . implode(', ', $documentLabels),
            'admin'
        );

        return back()->with('success', 'Selected documents marked as reviewed.');
    }

    public function adminStartReview(Request $request, ExaminationReportReplyCase $case): RedirectResponse
    {
        $data = $request->validate([
            'document_action' => ['nullable', 'in:start_review,documents_pending'],
            'missing_document_name' => ['nullable', 'string', 'max:120'],
            'missing_documents' => ['nullable', 'array', 'max:10'],
            'missing_documents.*' => ['nullable', 'string', 'max:120'],
            'message_to_client' => ['nullable', 'string', 'max:5000'],
            'tracking_note' => ['nullable', 'string', 'max:5000'],
        ]);

        if (($data['document_action'] ?? null) === 'documents_pending') {
            $missingDocuments = collect($data['missing_documents'] ?? [])
                ->push($data['missing_document_name'] ?? null)
(fn ($documentName) => trim((string) $documentName))
                ->filter()
                ->unique()
                ->values();

            if ($missingDocuments->isEmpty()) {
                return back()->withErrors(['missing_documents' => 'Please add at least one missing document to request.'])->withInput();
            }

            $stageRequest = ExaminationReplyStageRequest::create([
                'case_id' => $case->id,
                'from_stage' => $case->current_client_stage,
                'to_stage' => Workflow::CLIENT_REQUEST_RECEIVED,
                'client_message' => $data['message_to_client'] ?: Workflow::notificationMessage('documents_pending'),
                'internal_note' => $data['tracking_note'] ?? null,
                'created_by' => Auth::guard('admin')->id(),
            ]);

            foreach ($missingDocuments as $documentName) {
                ExaminationReplyRequestedDocument::create([
                    'case_id' => $case->id,
                    'stage_request_id' => $stageRequest->id,
                    'document_name' => $documentName,
                    'is_required' => true,
                ]);
            }

            $this->transition($case, Workflow::ADMIN_DOCUMENTS_PENDING, $data['tracking_note'] ?: 'Required document requested from client.', 'admin');
            $this->notifyClient($case, 'documents_pending', $data['message_to_client'] ?: null);

            return back()->with('success', 'Missing document request sent to client.');
        }

        if (!$case->documents()->where('document_type', 'examination_report')->exists()) {
            $this->transition($case, Workflow::ADMIN_DOCUMENTS_PENDING, 'Examination Report is missing.', 'admin');
            $this->notifyClient($case, 'documents_pending');

            return back()->with('warning', 'Examination Report is required before review can start.');
        }

        $this->saveNotesAndDocuments($request, $case);
        $this->transition($case, Workflow::ADMIN_REPORT_UNDER_REVIEW, $data['tracking_note'] ?: 'Examination Report review started.', 'admin');

        return back()->with('success', 'Examination Report review started.');
    }

    public function adminSaveObjection(Request $request, ExaminationReportReplyCase $case): RedirectResponse
    {
        $data = $request->validate([
            'objection_types' => ['required', 'array', 'min:1'],
            'objection_types.*' => ['in:Section 9 Objection,Section 11 Objection,Formal Objection,Mixed Objection'],
            'portal_label' => ['required', 'in:Distinctiveness Objection,Similarity / Conflict Objection,Documentation / Formality Objection,Multiple Objections'],
            'section_9_reasons' => ['nullable', 'array'],
            'section_9_reasons.*' => ['string', 'max:120'],
            'similar_mark_name' => ['nullable', 'string', 'max:255'],
            'earlier_application_number' => ['nullable', 'string', 'max:120'],
            'similarity_note' => ['nullable', 'string', 'max:3000'],
            'formal_objection_reasons' => ['nullable', 'array'],
            'formal_objection_reasons.*' => ['string', 'max:120'],
            'admin_legal_note' => ['nullable', 'string', 'max:5000'],
            'client_visible_note' => ['nullable', 'string', 'max:5000'],
            'evidence_required' => ['required', 'boolean'],
            'requested_documents' => ['required_if:evidence_required,1', 'nullable', 'array'],
            'requested_documents.*' => ['string', 'max:120'],
            'tracking_note' => ['nullable', 'string', 'max:5000'],
            'edit_previous_stage' => ['nullable', 'boolean'],
        ]);

        $objectionTypes = $data['objection_types'];
        if (in_array('Section 9 Objection', $objectionTypes, true) && empty($data['section_9_reasons'] ?? [])) {
            return back()->withErrors(['section_9_reasons' => 'Please select at least one Section 9 reason.'])->withInput();
        }
        if (in_array('Formal Objection', $objectionTypes, true) && empty($data['formal_objection_reasons'] ?? [])) {
            return back()->withErrors(['formal_objection_reasons' => 'Please select at least one formal objection reason.'])->withInput();
        }
        if ((bool) $data['evidence_required'] && empty(array_filter($data['requested_documents'] ?? []))) {
            return back()->withErrors(['requested_documents' => 'Please select at least one evidence document to request.'])->withInput();
        }
        if ((bool) $data['evidence_required'] && blank($data['client_visible_note'] ?? null)) {
            return back()->withErrors(['client_visible_note' => 'Please add the message to client for the evidence request.'])->withInput();
        }

        $case->update([
            'objection_types' => $data['objection_types'],
            'portal_label' => $data['portal_label'],
            'section_9_reasons' => in_array('Section 9 Objection', $objectionTypes, true) ? ($data['section_9_reasons'] ?? []) : [],
            'section_11_details' => [
                'similar_mark_name' => in_array('Section 11 Objection', $objectionTypes, true) ? ($data['similar_mark_name'] ?? null) : null,
                'earlier_application_number' => in_array('Section 11 Objection', $objectionTypes, true) ? ($data['earlier_application_number'] ?? null) : null,
                'similarity_note' => in_array('Section 11 Objection', $objectionTypes, true) ? ($data['similarity_note'] ?? null) : null,
            ],
            'section_11_note' => in_array('Section 11 Objection', $objectionTypes, true) ? ($data['similarity_note'] ?? null) : null,
            'formal_objection_reasons' => in_array('Formal Objection', $objectionTypes, true) ? ($data['formal_objection_reasons'] ?? []) : [],
            'evidence_required' => (bool) $data['evidence_required'],
            'client_visible_note' => $data['client_visible_note'] ?? $case->client_visible_note,
            'internal_tracking_note' => $data['admin_legal_note'] ?? $data['tracking_note'] ?? $case->internal_tracking_note,
        ]);
        $this->saveNotesAndDocuments($request, $case);

        if ($request->boolean('edit_previous_stage')) {
            $this->syncRequestedEvidenceDocuments($case, $data);
            $case->refresh()->load(['requestedDocuments', 'documents']);
            $this->recordStatus(
                $case,
                $case->current_admin_status,
                $case->current_admin_status,
                'Objection details updated by admin.',
                'admin'
            );
            $message = $this->objectionDetailsClientMessage($case, 'Your Trademark Objection Reply details have been updated.');
            $this->notifyClient($case, null, $message);
            $this->emailClient(
                $case,
                'Trademark Objection Reply Details Updated',
                $message,
                'View Objection Details',
                $this->clientVisibleStageAttachments($case, 'objection')
            );

            return back()->with('success', 'Objection details updated.');
        }

        if ((bool) $data['evidence_required']) {
            $this->syncRequestedEvidenceDocuments($case, $data);

            $this->transition($case, Workflow::ADMIN_EVIDENCE_REQUESTED, 'Evidence requested from client.', 'admin');
            $this->notifyClient($case, 'evidence_requested');
            $case->refresh()->load(['requestedDocuments', 'documents']);
            $this->emailClient(
                $case,
                'Evidence Required for Trademark Objection Reply',
                $this->objectionDetailsClientMessage($case, Workflow::notificationMessage('evidence_requested')),
                'Upload Evidence',
                $this->clientVisibleStageAttachments($case, 'objection')
            );

            return back()->with('success', 'Objection type saved and evidence requested from client.');
        }

        $this->transition($case, Workflow::ADMIN_EVIDENCE_REVIEW_COMPLETED, 'No further evidence required.', 'admin');
        $case->refresh()->load(['requestedDocuments', 'documents']);
        $this->emailClient(
            $case,
            'Trademark Objection Details Saved',
            $this->objectionDetailsClientMessage($case, 'Our legal team has completed the objection details for your Trademark Objection Reply.'),
            'View Case Status',
            $this->clientVisibleStageAttachments($case, 'objection')
        );

        return back()->with('success', 'Objection type saved. You can proceed to risk assessment.');
    }

    public function adminReviewEvidence(Request $request, ExaminationReportReplyCase $case): RedirectResponse
    {
        $data = $request->validate([
            'evidence_status' => ['required', 'in:Evidence Complete,Evidence Pending,More Evidence Required,Evidence Not Required'],
            'requested_documents' => ['nullable', 'array'],
            'requested_documents.*' => ['string', 'max:120'],
            'message_to_client' => ['nullable', 'required_if:evidence_status,More Evidence Required', 'string', 'max:5000'],
            'tracking_note' => ['nullable', 'string', 'max:5000'],
            'document_reviews' => ['nullable', 'array'],
            'document_reviews.*' => ['in:accepted,needs_better_copy'],
        ]);

        if ($data['evidence_status'] === 'More Evidence Required' && empty(array_filter($data['requested_documents'] ?? []))) {
            return back()->withErrors(['requested_documents' => 'Please add at least one requested document when asking for more evidence.'])->withInput();
        }

        foreach (($data['document_reviews'] ?? []) as $documentId => $reviewStatus) {
            $case->documents()->whereKey($documentId)->update([
                'review_status' => $reviewStatus === 'accepted' ? 'accepted' : 'needs_better_copy',
            ]);
        }
        $this->saveNotesAndDocuments($request, $case);

        if ($data['evidence_status'] === 'More Evidence Required') {
            $stageRequest = ExaminationReplyStageRequest::create([
                'case_id' => $case->id,
                'from_stage' => $case->current_client_stage,
                'to_stage' => Workflow::CLIENT_EVIDENCE_COLLECTION,
                'client_message' => $data['message_to_client'],
                'internal_note' => $data['tracking_note'] ?? null,
                'created_by' => Auth::guard('admin')->id(),
            ]);

            foreach (($data['requested_documents'] ?? []) as $documentName) {
                if (filled($documentName)) {
                    ExaminationReplyRequestedDocument::create([
                        'case_id' => $case->id,
                        'stage_request_id' => $stageRequest->id,
                        'document_name' => $documentName,
                        'is_required' => true,
                    ]);
                }
            }

            $this->transition($case, Workflow::ADMIN_EVIDENCE_REQUESTED, $data['message_to_client'], 'admin');
            $this->notifyClient($case, 'evidence_requested');

            return back()->with('success', 'More evidence requested from client.');
        }

        $this->transition($case, Workflow::ADMIN_EVIDENCE_REVIEW_COMPLETED, $data['tracking_note'] ?: 'Evidence review completed.', 'admin');

        return back()->with('success', 'Evidence review completed.');
    }

    public function adminRisk(Request $request, ExaminationReportReplyCase $case): RedirectResponse
    {
        $data = $request->validate([
            'risk_level' => ['required', 'in:Low Risk,Medium Risk,High Risk'],
            'risk_reason' => ['required', 'string', 'max:5000'],
            'client_visible_risk_note' => ['nullable', 'string', 'max:5000'],
            'recommendation_note' => ['nullable', 'string', 'max:5000'],
            'package_type' => ['required', 'string', 'max:150'],
            'package_price' => ['required', 'numeric', 'min:1', 'max:100000'],
            'package_description' => ['required', 'string', 'max:5000'],
            'included_services' => ['required', 'array', 'min:1'],
            'included_services.*' => ['string', 'max:150'],
            'add_ons' => ['nullable', 'array'],
            'add_ons.*' => ['string', 'max:150'],
            'tracking_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $case->update([
            'risk_level' => $data['risk_level'],
            'risk_reason' => $data['risk_reason'],
            'client_visible_risk_note' => $data['client_visible_risk_note'] ?? null,
            'recommendation_note' => $data['recommendation_note'] ?? null,
            'package_type' => $data['package_type'],
            'package_price' => $data['package_price'],
            'package_description' => $data['package_description'],
            'included_services' => $data['included_services'],
            'add_ons' => $data['add_ons'] ?? [],
            'internal_tracking_note' => $data['tracking_note'] ?? $case->internal_tracking_note,
            'payment_status' => 'pending',
        ]);
        $this->saveNotesAndDocuments($request, $case);
        $this->transition($case, Workflow::ADMIN_PAYMENT_PENDING, 'Legal review completed. Package assigned and payment requested.', 'admin');
        $this->notifyClient($case, null, 'Your Trademark Objection Reply legal review and package details are ready. Please review and complete payment.');

        return back()->with('success', 'Legal review, package details, and payment request saved.');
    }

    public function adminPricing(Request $request, ExaminationReportReplyCase $case): RedirectResponse
    {
        $data = $request->validate([
            'package_type' => ['required', 'in:Basic Objection Reply,Standard Objection Reply,Advanced Objection Reply,Custom Package'],
            'package_price' => ['required', 'numeric', 'min:1', 'max:100000'],
            'package_description' => ['required', 'string', 'max:5000'],
            'included_services' => ['required', 'array', 'min:1'],
            'included_services.*' => ['string', 'max:150'],
            'add_ons' => ['nullable', 'array'],
            'add_ons.*' => ['string', 'max:150'],
            'tracking_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $case->update([
            'package_type' => $data['package_type'],
            'package_price' => $data['package_price'],
            'package_description' => $data['package_description'],
            'included_services' => $data['included_services'],
            'add_ons' => $data['add_ons'] ?? [],
            'payment_status' => 'pending',
        ]);
        $this->saveNotesAndDocuments($request, $case);
        $this->transition($case, Workflow::ADMIN_PAYMENT_PENDING, $data['tracking_note'] ?: 'Package assigned and payment requested.', 'admin');
        $this->notifyClient($case, null, 'Your Trademark Objection Reply package is ready. Please review the package and complete payment.');

        return back()->with('success', 'Package assigned and client notified.');
    }

    public function adminDraft(Request $request, ExaminationReportReplyCase $case): RedirectResponse
    {
        if ($case->payment_status !== 'paid') {
            return back()->with('error', 'Payment must be completed before reply drafting can start.');
        }

        $savedDraftReplyDocument = $case->documents()
            ->where('stage_key', 'draft')
            ->where('is_draft', true)
            ->where('metadata->input_name', 'draft_file')
            ->latest('id')
            ->first();
        $lastDocumentIdBeforeDraftUpload = (int) $case->documents()->max('id');
        $draftEmailDocumentIds = $case->documents()
            ->where('stage_key', 'draft')
            ->where('is_draft', true)
            ->pluck('id')
            ->merge($request->input('optional_existing_document_ids', []));

        $data = $request->validate([
            'draft_file' => [$savedDraftReplyDocument ? 'nullable' : 'required', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:' . self::ADMIN_UPLOAD_MAX_KB],
            'message_to_client' => ['nullable', 'string', 'max:5000'],
            'tracking_note' => ['nullable', 'string', 'max:5000'],
            'optional_documents' => ['nullable', 'array', 'max:10'],
            'optional_documents.*' => ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:' . self::ADMIN_UPLOAD_MAX_KB],
            'optional_existing_document_ids' => ['nullable', 'array', 'max:10'],
            'optional_existing_document_ids.*' => ['nullable', 'integer'],
            'optional_document_names' => ['nullable', 'array', 'max:10'],
            'optional_document_names.*' => ['nullable', 'string', 'max:255'],
            'optional_document_types' => ['nullable', 'array', 'max:10'],
            'optional_document_types.*' => ['nullable', 'string', 'max:120'],
            'optional_attachment_notes' => ['nullable', 'array', 'max:10'],
            'optional_attachment_notes.*' => ['nullable', 'string', 'max:3000'],
            'optional_document_remarks' => ['nullable', 'array', 'max:10'],
            'optional_document_remarks.*' => ['nullable', 'string', 'max:3000'],
            'optional_document_visibilities' => ['nullable', 'array', 'max:10'],
            'optional_document_visibilities.*' => ['nullable', 'in:client,admin'],
        ], [
            'optional_documents.*.uploaded' => 'An additional document could not be uploaded. Ensure every file is below 15 MB.',
            'optional_documents.*.max' => 'Each additional document must be 15 MB or smaller.',
            'optional_documents.max' => 'You can attach a maximum of 10 additional documents at a time.',
        ]);

        if ($request->hasFile('draft_file') && $savedDraftReplyDocument) {
            Storage::disk('public')->delete($savedDraftReplyDocument->file_path);
            $savedDraftReplyDocument->delete();
            $savedDraftReplyDocument = null;
        }

        $this->saveNotesAndDocuments($request, $case);
        if ($request->hasFile('draft_file')) {
            $file = $request->file('draft_file');
            $path = $file->store('examination-replies/' . $case->id . '/drafts', 'public');
            $originalName = $file->getClientOriginalName();
            $fileType = strtolower((string) $file->getClientOriginalExtension());
            $fileSize = (int) $file->getSize();
        } else {
            $savedDraftReplyDocument->refresh();
            $path = $savedDraftReplyDocument->file_path;
            $originalName = $savedDraftReplyDocument->original_name;
            $fileType = $savedDraftReplyDocument->file_type;
            $fileSize = (int) $savedDraftReplyDocument->file_size;
            $savedDraftReplyDocument->update([
                'document_type' => 'reply_draft',
                'document_title' => $savedDraftReplyDocument->document_title ?: 'Objection Reply Draft',
                'visibility' => 'client',
                'is_draft' => false,
            ]);
            $replyDraftDocument = $savedDraftReplyDocument;
        }

        ExaminationReplyDraft::create([
            'case_id' => $case->id,
            'draft_file_path' => $path,
            'original_name' => $originalName,
            'version' => ((int) $case->drafts()->max('version')) + 1,
            'status' => 'client_approval_pending',
            'uploaded_by' => 'admin',
        ]);
        if ($request->hasFile('draft_file')) {
            $replyDraftDocument = ExaminationReplyDocument::create([
                'case_id' => $case->id,
                'document_type' => 'reply_draft',
                'document_title' => 'Objection Reply Draft',
                'file_path' => $path,
                'original_name' => $originalName,
                'file_type' => $fileType,
                'file_size' => $fileSize,
                'visibility' => 'client',
                'uploaded_by' => 'admin',
                'stage_key' => 'draft',
            ]);
        }

        $case->update([
            'draft_status' => 'Ready for Client Approval',
            'client_approval_status' => 'pending',
        ]);
        $this->transition(
            $case,
            Workflow::ADMIN_CLIENT_APPROVAL_PENDING,
            $data['tracking_note'] ?: 'Draft reply uploaded for client approval.',
            'admin'
        );
        $this->notifyClient($case, 'draft_ready', $data['message_to_client'] ?: null);

        $case->refresh()->loadMissing('user');
        $draftEmailDocumentIds = $draftEmailDocumentIds
            ->push($replyDraftDocument->id)
            ->merge($case->documents()->where('id', '>', $lastDocumentIdBeforeDraftUpload)->pluck('id'))
            ->filter()
            ->unique()
            ->values();
        $draftMailAttachments = $case->documents()
            ->whereIn('id', $draftEmailDocumentIds)
            ->where('visibility', 'client')
            ->where('is_draft', false)
            ->latest('id')
            ->get()
            ->unique(fn (ExaminationReplyDocument $document) => $document->file_path)
            ->filter(fn (ExaminationReplyDocument $document) => Storage::disk('public')->exists($document->file_path))
            ->map(fn (ExaminationReplyDocument $document) => [
                'path' => Storage::disk('public')->path($document->file_path),
                'name' => $document->document_title
                    ? $document->document_title . ($document->file_type ? '.' . $document->file_type : '')
                    : $document->original_name,
            ])
            ->values()
            ->all();
        $this->emailClient(
            $case,
            'Objection Reply Draft Ready for Approval',
            $data['message_to_client'] ?: Workflow::notificationMessage('draft_ready'),
            'Review Draft',
            $draftMailAttachments
        );

        return back()->with('success', 'Draft uploaded and sent for client approval.');
    }

    public function adminFiling(Request $request, ExaminationReportReplyCase $case): RedirectResponse
    {
        if ($case->client_approval_status !== 'approved') {
            return back()->with('error', 'Client approval is required before filing the reply.');
        }

        $savedFilingAcknowledgmentDocument = $case->documents()
            ->where('stage_key', 'filing')
            ->where('is_draft', true)
            ->where('metadata->input_name', 'filing_acknowledgment')
            ->latest('id')
            ->first();

        $data = $request->validate([
            'reply_filing_date' => ['required', 'date'],
            'filing_acknowledgment' => [$savedFilingAcknowledgmentDocument ? 'nullable' : 'required', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:' . self::ADMIN_UPLOAD_MAX_KB],
            'registry_filing_note' => ['nullable', 'string', 'max:5000'],
            'tracking_note' => ['nullable', 'string', 'max:5000'],
            'optional_documents' => ['nullable', 'array', 'max:10'],
            'optional_documents.*' => ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:' . self::ADMIN_UPLOAD_MAX_KB],
            'optional_existing_document_ids' => ['nullable', 'array', 'max:10'],
            'optional_existing_document_ids.*' => ['nullable', 'integer'],
            'optional_document_names' => ['nullable', 'array', 'max:10'],
            'optional_document_names.*' => ['nullable', 'string', 'max:255'],
            'optional_document_visibilities' => ['nullable', 'array', 'max:10'],
            'optional_document_visibilities.*' => ['nullable', 'in:client,admin'],
        ], [
            'optional_documents.*.uploaded' => 'An additional document could not be uploaded. Ensure every file is below 15 MB.',
            'optional_documents.*.max' => 'Each additional document must be 15 MB or smaller.',
            'optional_documents.max' => 'You can attach a maximum of 10 additional documents at a time.',
        ]);

        if ($request->hasFile('filing_acknowledgment') && $savedFilingAcknowledgmentDocument) {
            Storage::disk('public')->delete($savedFilingAcknowledgmentDocument->file_path);
            $savedFilingAcknowledgmentDocument->delete();
            $savedFilingAcknowledgmentDocument = null;
        }

        $this->saveNotesAndDocuments($request, $case);

        if ($request->hasFile('filing_acknowledgment')) {
            $document = $this->storeDocument(
                $case,
                $request->file('filing_acknowledgment'),
                'filing_acknowledgment',
                'admin',
                'client',
                null,
                [
                    'document_title' => 'Filing Acknowledgment',
                    'stage_key' => 'filing',
                ]
            );
        } else {
            $savedFilingAcknowledgmentDocument->refresh();
            $savedFilingAcknowledgmentDocument->update([
                'document_type' => 'filing_acknowledgment',
                'document_title' => $savedFilingAcknowledgmentDocument->document_title ?: 'Filing Acknowledgment',
                'visibility' => 'client',
                'is_draft' => false,
            ]);
            $document = $savedFilingAcknowledgmentDocument;
        }

        $case->update([
            'reply_filing_date' => $data['reply_filing_date'],
            'acknowledgment_file' => $document->file_path,
        ]);
        $this->transition(
            $case,
            Workflow::ADMIN_AWAITING_REGISTRY_REVIEW,
            $data['registry_filing_note']
                ?: 'Reply filed and filing acknowledgment uploaded. The application is now awaiting review by the Trademark Registry.',
            'admin'
        );
        $this->notifyClient(
            $case,
            'awaiting_registry',
            'Your objection reply has been filed and the filing acknowledgment is available in your dashboard. The application is now awaiting review by the Trademark Registry.'
        );

        return back()->with('success', 'Reply marked filed. Awaiting Registry Update is now active and the client has been notified.');
    }

    public function adminRegistryUpdate(Request $request, ExaminationReportReplyCase $case): RedirectResponse
    {
        $data = $request->validate([
            'registry_update_type' => ['required', 'in:Accepted,Accepted & Advertised,Hearing Issued,Further Clarification Required,Application Abandoned,Still Waiting'],
            'registry_document' => ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:' . self::ADMIN_UPLOAD_MAX_KB],
            'hearing_notice' => [
                \Illuminate\Validation\Rule::requiredIf(fn () => $request->input('registry_update_type') === 'Hearing Issued'),
                'nullable',
                'file',
                'mimes:' . self::ALLOWED_UPLOADS,
                'max:' . self::ADMIN_UPLOAD_MAX_KB,
            ],
            'hearing_datetime' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'message_to_client' => [
                \Illuminate\Validation\Rule::requiredIf(fn () => in_array($request->input('registry_update_type'), [
                    'Further Clarification Required',
                    'Application Abandoned',
                ], true)),
                'nullable',
                'string',
                'max:5000',
            ],
            'tracking_note' => ['nullable', 'string', 'max:5000'],
            'clarification_documents' => ['nullable', 'array', 'max:10'],
            'clarification_documents.*.name' => ['nullable', 'string', 'max:120'],
            'clarification_documents.*.required' => ['nullable', 'boolean'],
            'optional_documents' => ['nullable', 'array', 'max:10'],
            'optional_documents.*' => ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:' . self::ADMIN_UPLOAD_MAX_KB],
            'optional_existing_document_ids' => ['nullable', 'array', 'max:10'],
            'optional_existing_document_ids.*' => ['nullable', 'integer'],
            'optional_document_names' => ['nullable', 'array', 'max:10'],
            'optional_document_names.*' => ['nullable', 'string', 'max:255'],
            'optional_document_visibilities' => ['nullable', 'array', 'max:10'],
            'optional_document_visibilities.*' => ['nullable', 'in:client,admin'],
        ], [
            'registry_update_type.required' => 'Select a Registry update to continue.',
            'hearing_notice.required' => 'Upload the hearing notice before moving the case to Hearing Required.',
            'message_to_client.required' => 'Add a message explaining this Registry update to the client.',
            'optional_documents.*.uploaded' => 'An additional document could not be uploaded. Ensure every file is below 15 MB.',
            'optional_documents.*.max' => 'Each additional document must be 15 MB or smaller.',
            'optional_documents.max' => 'You can attach a maximum of 10 additional documents at a time.',
        ]);

        if ($request->hasFile('registry_document')) {
            $this->storeDocument(
                $case,
                $request->file('registry_document'),
                'registry_update',
                'admin',
                'client',
                null,
                [
                    'document_title' => 'Registry Document / Screenshot',
                    'stage_key' => 'registry_update',
                    'metadata' => [
                        'source' => 'registry_update_primary',
                        'input_name' => 'registry_document',
                    ],
                ]
            );
        }

        if ($request->hasFile('hearing_notice')) {
            $this->storeDocument(
                $case,
                $request->file('hearing_notice'),
                'hearing_notice',
                'admin',
                'client',
                null,
                [
                    'document_title' => 'Hearing Notice',
                    'stage_key' => 'hearing_required',
                    'metadata' => [
                        'source' => 'registry_update_hearing',
                        'input_name' => 'hearing_notice',
                    ],
                ]
            );
        }

        $this->saveNotesAndDocuments($request, $case);

        $nextStatus = match ($data['registry_update_type']) {
            'Accepted' => Workflow::ADMIN_ACCEPTED,
            'Accepted & Advertised' => Workflow::ADMIN_ACCEPTED_ADVERTISED,
            'Hearing Issued' => Workflow::ADMIN_HEARING_ISSUED,
            'Further Clarification Required' => Workflow::ADMIN_FURTHER_ACTION_REQUIRED,
            'Application Abandoned' => Workflow::ADMIN_APPLICATION_ABANDONED,
            default => Workflow::ADMIN_AWAITING_REGISTRY_REVIEW,
        };
        $isTerminalOutcome = in_array($nextStatus, [
            Workflow::ADMIN_ACCEPTED,
            Workflow::ADMIN_ACCEPTED_ADVERTISED,
            Workflow::ADMIN_APPLICATION_ABANDONED,
        ], true);
        $defaultClientMessage = match ($data['registry_update_type']) {
            'Accepted' => 'Your objection reply has been accepted by the Trademark Registry.',
            'Accepted & Advertised' => 'Your trademark has been accepted and advertised in the Trademark Journal.',
            'Hearing Issued' => Workflow::notificationMessage('hearing_issued'),
            'Application Abandoned' => 'The application has been marked abandoned due to delay or previous non-compliance. Please review the update shared by our team.',
            default => '',
        };
        $clientMessage = trim((string) ($data['message_to_client'] ?? ''));
        $shouldPublishClientUpdate = $data['registry_update_type'] !== 'Still Waiting' || $clientMessage !== '';
        $caseUpdates = [
            'registry_update_type' => $data['registry_update_type'],
            'registry_update_date' => now()->toDateString(),
            'case_status' => 'active',
            'closed_at' => null,
        ];
        if (! $shouldPublishClientUpdate) {
            unset($caseUpdates['registry_update_type'], $caseUpdates['registry_update_date']);
        }
        if ($shouldPublishClientUpdate) {
            $caseUpdates['client_visible_note'] = $clientMessage !== '' ? $clientMessage : $defaultClientMessage;
        }
        if ($nextStatus === Workflow::ADMIN_HEARING_ISSUED) {
            $hearingAt = filled($data['hearing_datetime'] ?? null)
                ? Carbon::createFromFormat('Y-m-d\TH:i', $data['hearing_datetime'])
                : null;
            $caseUpdates = array_merge($caseUpdates, [
                'hearing_date' => $hearingAt?->toDateString(),
                'hearing_time' => $hearingAt?->format('H:i'),
            ]);
        }
        if ($isTerminalOutcome) {
            $caseUpdates['final_outcome'] = $data['registry_update_type'];
            $caseUpdates['final_note_to_client'] = $clientMessage !== '' ? $clientMessage : $defaultClientMessage;
        }
        $case->update($caseUpdates);

        if ($nextStatus === Workflow::ADMIN_FURTHER_ACTION_REQUIRED) {
            $clarificationDocuments = collect($data['clarification_documents'] ?? [])
                ->map(fn (array $document) => [
                    'name' => trim((string) ($document['name'] ?? '')),
                    'required' => (bool) ($document['required'] ?? true),
                ])
                ->filter(fn (array $document) => $document['name'] !== '')
                ->unique('name')
                ->values();

            if ($clarificationDocuments->isNotEmpty()) {
                $stageRequest = ExaminationReplyStageRequest::create([
                    'case_id' => $case->id,
                    'from_stage' => $case->current_client_stage,
                    'to_stage' => Workflow::CLIENT_AWAITING_REGISTRY_UPDATE,
                    'client_message' => $clientMessage,
                    'internal_note' => $data['tracking_note'] ?? null,
                    'created_by' => Auth::guard('admin')->id(),
                ]);

                foreach ($clarificationDocuments as $clarificationDocument) {
                    ExaminationReplyRequestedDocument::create([
                        'case_id' => $case->id,
                        'stage_request_id' => $stageRequest->id,
                        'document_name' => $clarificationDocument['name'],
                        'is_required' => $clarificationDocument['required'],
                    ]);
                }
            }
        }

        $transitionNote = trim((string) ($data['tracking_note'] ?? ''))
            ?: 'Registry update recorded: ' . $data['registry_update_type'] . '.';
        $isSilentStillWaiting = $data['registry_update_type'] === 'Still Waiting'
            && $clientMessage === ''
            && trim((string) ($data['tracking_note'] ?? '')) === '';
        if (!$isSilentStillWaiting) {
            $this->transition($case, $nextStatus, $transitionNote, 'admin');
        }

        $shouldNotifyClient = $shouldPublishClientUpdate;
        if ($shouldNotifyClient) {
            $notificationType = $nextStatus === Workflow::ADMIN_HEARING_ISSUED
                ? 'hearing_issued'
                : ($nextStatus === Workflow::ADMIN_AWAITING_REGISTRY_REVIEW ? 'awaiting_registry' : null);
            $this->notifyClient($case, $notificationType, $clientMessage !== '' ? $clientMessage : $defaultClientMessage);
        }

        return back()->with('success', $shouldNotifyClient
            ? 'Registry update saved and the client has been notified.'
            : 'Still Waiting saved without notifying the client.');
    }

    public function adminAwaitingRegistry(Request $request, ExaminationReportReplyCase $case): RedirectResponse
    {
        $data = $request->validate([
            'message_to_client' => ['nullable', 'string', 'max:5000'],
            'tracking_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->saveNotesAndDocuments($request, $case);
        $this->transition($case, Workflow::ADMIN_AWAITING_REGISTRY_REVIEW, $data['tracking_note'] ?: 'Awaiting Registry review after reply filing.', 'admin');
        $this->notifyClient($case, 'awaiting_registry', $data['message_to_client'] ?: null);

        return back()->with('success', 'Case moved to Awaiting Registry Review.');
    }

    public function adminHearing(Request $request, ExaminationReportReplyCase $case): RedirectResponse
    {
        $data = $request->validate([
            'hearing_notice' => ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:' . self::ADMIN_UPLOAD_MAX_KB],
            'hearing_datetime' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'hearing_package_price' => ['nullable', 'numeric', 'min:1', 'max:100000'],
            'message_to_client' => ['nullable', 'string', 'max:5000'],
            'tracking_note' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($request->hasFile('hearing_notice')) {
            $this->storeDocument($case, $request->file('hearing_notice'), 'hearing_notice', 'admin', 'client');
        }
        $hearingAt = filled($data['hearing_datetime'] ?? null)
            ? Carbon::createFromFormat('Y-m-d\TH:i', $data['hearing_datetime'])
            : null;
        $case->update(collect($data)
            ->except(['hearing_notice', 'hearing_datetime', 'message_to_client', 'tracking_note'])
            ->merge([
                'hearing_date' => $hearingAt?->toDateString(),
                'hearing_time' => $hearingAt?->format('H:i'),
            ])
            ->all());
        $this->saveNotesAndDocuments($request, $case);
        $this->transition($case, Workflow::ADMIN_HEARING_ISSUED, $data['message_to_client'] ?: 'Hearing notice updated.', 'admin');
        $this->notifyClient($case, 'hearing_issued');

        return back()->with('success', 'Hearing details saved.');
    }

    public function adminClose(Request $request, ExaminationReportReplyCase $case): RedirectResponse
    {
        $data = $request->validate([
            'final_outcome' => ['required', 'in:Accepted,Accepted & Advertised,Hearing Required,Further Clarification Required,Application Abandoned,Other'],
            'final_registry_document' => ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:' . self::ADMIN_UPLOAD_MAX_KB],
            'final_note_to_client' => ['required', 'string', 'max:5000'],
            'internal_closing_note' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($request->hasFile('final_registry_document')) {
            $this->storeDocument($case, $request->file('final_registry_document'), 'final_registry_document', 'admin', 'client');
        }
        $case->update([
            'final_outcome' => $data['final_outcome'],
            'final_note_to_client' => $data['final_note_to_client'],
            'internal_tracking_note' => $data['internal_closing_note'] ?? $case->internal_tracking_note,
            'case_status' => 'closed',
            'closed_at' => now(),
        ]);
        $this->saveNotesAndDocuments($request, $case);
        $this->transition($case, Workflow::ADMIN_MATTER_CLOSED, $data['final_note_to_client'], 'admin');
        $this->notifyClient($case, 'matter_closed');

        return back()->with('success', 'Matter closed and client notified.');
    }

    public function viewDocument(ExaminationReplyDocument $document)
    {
        $this->authorizeDocument($document);
        abort_unless(Storage::disk('public')->exists($document->file_path), 404);

        return response()->file(Storage::disk('public')->path($document->file_path));
    }

    public function downloadDocument(ExaminationReplyDocument $document)
    {
        $this->authorizeDocument($document);
        abort_unless(Storage::disk('public')->exists($document->file_path), 404);

        return Storage::disk('public')->download($document->file_path, $document->original_name);
    }

    public function removeDocument(ExaminationReplyDocument $document): RedirectResponse
    {
        abort_unless(Auth::guard('admin')->check(), 403);
        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Document removed.');
    }

    private function discountedPackagePricing(string $service, float $amount, ?int $couponId = null): array
    {
        $originalAmount = round(max($amount, 0), 2);
        $availableCoupons = DiscountCoupon::availableForPayment($service, Auth::id());
        $coupon = $couponId ? $availableCoupons->firstWhere('id', $couponId) : null;

        if (!$coupon) {
            $coupon = DiscountCoupon::autoApplyForPayment($service, Auth::id());
        }

        $discountAmount = $coupon ? round($coupon->discountAmountFor($originalAmount), 2) : 0.0;

        return [
            'original_amount' => $originalAmount,
            'payable_amount' => round(max($originalAmount - $discountAmount, 0), 2),
            'discount_amount' => $discountAmount,
            'coupon_label' => $coupon?->code,
        ];
    }

    private function transition(ExaminationReportReplyCase $case, string $adminStatus, ?string $note = null, string $changedBy = 'system'): void
    {
        $paidServiceStatuses = [
            Workflow::ADMIN_PAYMENT_COMPLETED,
            Workflow::ADMIN_REPLY_DRAFTING,
            Workflow::ADMIN_DRAFT_UNDER_REVIEW,
            Workflow::ADMIN_CLIENT_APPROVAL_PENDING,
            Workflow::ADMIN_CHANGES_REQUESTED,
            Workflow::ADMIN_READY_FOR_FILING,
            Workflow::ADMIN_FILED_WITH_REGISTRY,
            Workflow::ADMIN_ACKNOWLEDGMENT_UPLOADED,
            Workflow::ADMIN_AWAITING_REGISTRY_REVIEW,
            Workflow::ADMIN_ACCEPTED,
            Workflow::ADMIN_ACCEPTED_ADVERTISED,
            Workflow::ADMIN_HEARING_ISSUED,
            Workflow::ADMIN_FURTHER_ACTION_REQUIRED,
        ];

        abort_unless(
            ! in_array($adminStatus, $paidServiceStatuses, true) || $case->payment_status === 'paid',
            409,
            'Verified payment is required before this objection-reply stage can begin.',
        );

        $oldAdmin = $case->current_admin_status;
        $oldClient = $case->current_client_stage;
        $clientStage = Workflow::clientStageForAdminStatus($adminStatus);

        $case->update([
            'current_admin_status' => $adminStatus,
            'current_client_stage' => $clientStage,
        ]);
        $this->recordStatus($case->fresh(), $oldAdmin, $adminStatus, $note, $changedBy, $oldClient, $clientStage);
    }

    private function completeEvidenceCollectionIfReviewed(ExaminationReportReplyCase $case, string $changedBy = 'admin'): bool
    {
        $case->refresh()->load('documents', 'requestedDocuments.stageRequest');

        if (! in_array($case->current_admin_status, [
            Workflow::ADMIN_EVIDENCE_REQUESTED,
            Workflow::ADMIN_EVIDENCE_SUBMITTED,
        ], true)) {
            return false;
        }

        $currentEvidenceRequests = $this->currentEvidenceRequestedDocuments($case);

        $hasPendingRequiredEvidence = $currentEvidenceRequests
            ->where('is_required', true)
            ->where('is_uploaded_by_client', false)
            ->isNotEmpty();

        if ($hasPendingRequiredEvidence) {
            return false;
        }

        $uploadedEvidencePaths = $currentEvidenceRequests
            ->where('is_uploaded_by_client', true)
            ->pluck('uploaded_file_path')
            ->filter()
            ->values();

        if ($uploadedEvidencePaths->isEmpty()) {
            return false;
        }

        $uploadedEvidenceDocuments = $case->documents
            ->where('uploaded_by', 'client')
            ->filter(fn ($document) => $uploadedEvidencePaths->contains($document->file_path))
            ->values();

        if ($uploadedEvidenceDocuments->isEmpty()) {
            return false;
        }

        $allEvidenceReviewed = $uploadedEvidenceDocuments
            ->every(fn ($document) => in_array($document->review_status, ['reviewed', 'accepted'], true));

        if (! $allEvidenceReviewed) {
            return false;
        }

        $this->transition(
            $case,
            Workflow::ADMIN_EVIDENCE_REVIEW_COMPLETED,
            'Evidence Collection completed. All client-submitted evidence reviewed. Legal Review is now active.',
            $changedBy
        );

        return true;
    }

    private function currentEvidenceRequestedDocuments(ExaminationReportReplyCase $case)
    {
        $case->loadMissing('requestedDocuments.stageRequest');

        return $case->requestedDocuments
            ->filter(fn ($requested) => $requested->stageRequest?->to_stage === Workflow::CLIENT_EVIDENCE_COLLECTION)
            ->groupBy(fn ($requested) => Str::lower(trim((string) $requested->document_name)))
            ->map(fn ($requests) => $requests->sortByDesc('id')->first())
            ->filter()
            ->values();
    }

    private function recordStatus(
        ExaminationReportReplyCase $case,
        ?string $oldAdminStatus,
        string $newAdminStatus,
        ?string $note,
        string $changedBy,
        ?string $oldClientStage = null,
        ?string $newClientStage = null
    ): void {
        ExaminationReplyStatusHistory::create([
            'case_id' => $case->id,
            'old_admin_status' => $oldAdminStatus,
            'new_admin_status' => $newAdminStatus,
            'old_client_stage' => $oldClientStage ?? $case->current_client_stage,
            'new_client_stage' => $newClientStage ?? Workflow::clientStageForAdminStatus($newAdminStatus),
            'note' => $note,
            'changed_by' => $changedBy,
            'actor_id' => $changedBy === 'admin' ? Auth::guard('admin')->id() : Auth::id(),
        ]);
    }

    private function storeDocument(ExaminationReportReplyCase $case, $file, string $documentType, string $uploadedBy, string $visibility, ?string $reviewStatus = null, array $attributes = []): ExaminationReplyDocument
    {
        $path = $file->store('examination-replies/' . $case->id . '/documents', 'public');

        return ExaminationReplyDocument::create([
            'case_id' => $case->id,
            'document_type' => $documentType,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'file_type' => strtolower((string) $file->getClientOriginalExtension()),
            'file_size' => (int) $file->getSize(),
            'visibility' => $visibility,
            'uploaded_by' => $uploadedBy,
            'review_status' => $uploadedBy === 'client' ? ($reviewStatus ?: 'uploaded') : $reviewStatus,
        ] + $attributes);
    }

    private function saveNotesAndDocuments(Request $request, ExaminationReportReplyCase $case): void
    {
        $stageKey = $this->stageKey($request, $case);
        $updates = [];
        if ($request->filled('internal_client_note')) {
            $updates['internal_client_note'] = $request->input('internal_client_note');
        }
        if ($request->filled('client_visible_note') || $request->filled('message_to_client')) {
            $updates['client_visible_note'] = $request->input('client_visible_note', $request->input('message_to_client'));
        }
        if ($request->filled('tracking_note')) {
            $updates['internal_tracking_note'] = $request->input('tracking_note');
        }
        if ($updates !== []) {
            $case->update($updates);
        }

        $this->saveAdditionalDocumentsFromRequest($request, $case, $stageKey, true);
        $this->clearStageDraft($case, $stageKey);
    }

    private function syncRequestedEvidenceDocuments(ExaminationReportReplyCase $case, array $data): void
    {
        $selectedDocuments = collect($data['requested_documents'] ?? [])
            ->map(fn ($documentName) => trim((string) $documentName))
            ->filter()
            ->unique()
            ->values();

        if (! (bool) ($data['evidence_required'] ?? false)) {
            $case->requestedDocuments()
                ->where('is_uploaded_by_client', false)
                ->delete();

            return;
        }

        $stageRequest = $case->stageRequests()
            ->where('to_stage', Workflow::CLIENT_EVIDENCE_COLLECTION)
            ->latest('id')
            ->first();

        if ($stageRequest) {
            $stageRequest->update([
                'client_message' => $data['client_visible_note'] ?? $stageRequest->client_message ?? Workflow::notificationMessage('evidence_requested'),
                'internal_note' => $data['tracking_note'] ?? $stageRequest->internal_note,
            ]);
        } else {
            $stageRequest = ExaminationReplyStageRequest::create([
                'case_id' => $case->id,
                'from_stage' => $case->current_client_stage,
                'to_stage' => Workflow::CLIENT_EVIDENCE_COLLECTION,
                'client_message' => $data['client_visible_note'] ?? Workflow::notificationMessage('evidence_requested'),
                'internal_note' => $data['tracking_note'] ?? null,
                'created_by' => Auth::guard('admin')->id(),
            ]);
        }

        $case->requestedDocuments()
            ->where('is_uploaded_by_client', false)
            ->whereNotIn('document_name', $selectedDocuments->all())
            ->delete();

        foreach ($selectedDocuments as $documentName) {
            $case->requestedDocuments()->updateOrCreate(
                [
                    'document_name' => $documentName,
                    'is_uploaded_by_client' => false,
                ],
                [
                    'stage_request_id' => $stageRequest->id,
                    'is_required' => true,
                ]
            );
        }
    }

    private function objectionDetailsClientMessage(ExaminationReportReplyCase $case, string $intro): string
    {
        $lines = [$intro];

        if (filled($case->portal_label)) {
            $lines[] = 'Portal label: ' . $case->portal_label;
        }

        if (!empty($case->objection_types)) {
            $lines[] = 'Objection type(s): ' . implode(', ', $case->objection_types);
        }

        if (!empty($case->section_9_reasons)) {
            $lines[] = 'Section 9 reason(s): ' . implode(', ', $case->section_9_reasons);
        }

        $section11Details = $case->section_11_details ?? [];
        $section11Lines = collect([
            'Similar earlier mark' => $section11Details['similar_mark_name'] ?? null,
            'Earlier application number' => $section11Details['earlier_application_number'] ?? null,
            'Similarity note' => $section11Details['similarity_note'] ?? $case->section_11_note,
        ])->filter();

        if ($section11Lines->isNotEmpty()) {
            $lines[] = 'Section 11 details:';
            foreach ($section11Lines as $label => $value) {
                $lines[] = '- ' . $label . ': ' . $value;
            }
        }

        if (!empty($case->formal_objection_reasons)) {
            $lines[] = 'Formal objection reason(s): ' . implode(', ', $case->formal_objection_reasons);
        }

        $requestedDocuments = $case->requestedDocuments
            ->pluck('document_name')
            ->filter()
            ->unique()
            ->values();

        if ($requestedDocuments->isNotEmpty()) {
            $lines[] = 'Evidence/document(s) requested: ' . $requestedDocuments->implode(', ');
        }

        if (filled($case->client_visible_note)) {
            $lines[] = "\nAdmin note: " . $case->client_visible_note;
        }

        return implode("\n", $lines);
    }

    private function clientVisibleStageAttachments(ExaminationReportReplyCase $case, string $stageKey): array
    {
        return $case->documents
            ->where('uploaded_by', 'admin')
            ->where('visibility', 'client')
            ->where('stage_key', $stageKey)
            ->where('is_draft', false)
            ->filter(fn ($document) => Storage::disk('public')->exists($document->file_path))
            ->map(fn ($document) => [
                'path' => Storage::disk('public')->path($document->file_path),
                'name' => $document->original_name,
                'mime' => $document->file_type ? Storage::disk('public')->mimeType($document->file_path) : null,
            ])
            ->values()
            ->all();
    }

    private function saveAdditionalDocumentsFromRequest(Request $request, ExaminationReportReplyCase $case, string $stageKey, bool $publishDraftFiles = false): int
    {
        $storedCount = 0;

        if ($publishDraftFiles) {
            $storedCount += $this->publishDraftFiles($case, $stageKey);
        }

        $storedCount += $this->updateExistingAdditionalDocuments($request, $case, $stageKey);

        foreach ($request->file('additional_documents', []) as $file) {
            if (!$file) {
                continue;
            }

            $this->storeDocument(
                $case,
                $file,
                $this->documentTypeFromInput($request->input('additional_document_type', 'additional_document')),
                'admin',
                $request->input('additional_visibility', 'client'),
                null,
                [
                    'stage_key' => $stageKey,
                    'document_title' => $request->input('additional_document_type'),
                    'metadata' => ['source' => 'legacy_additional_documents'],
                ]
            );
            $storedCount++;
        }

        $documentNames = $request->input('optional_document_names', []);
        $documentTypes = $request->input('optional_document_types', []);
        $attachmentNotes = $request->input('optional_attachment_notes', []);
        $remarks = $request->input('optional_document_remarks', []);
        $visibilities = $request->input('optional_document_visibilities', []);
        $newDocumentOffset = count($request->input('optional_existing_document_ids', []));

        foreach ($request->file('optional_documents', []) as $index => $file) {
            if (!$file) {
                continue;
            }

            $fieldIndex = $newDocumentOffset + $index;
            $documentTitle = trim((string) ($documentNames[$fieldIndex] ?? ''));
            $documentTypeLabel = trim((string) ($documentTypes[$fieldIndex] ?? ''));

            $this->storeDocument(
                $case,
                $file,
                $this->documentTypeFromInput($documentTypeLabel !== '' ? $documentTypeLabel : $documentTitle),
                'admin',
                $visibilities[$fieldIndex] ?? 'client',
                null,
                [
                    'document_title' => $documentTitle !== '' ? $documentTitle : null,
                    'document_note' => $attachmentNotes[$fieldIndex] ?? null,
                    'remarks' => $remarks[$fieldIndex] ?? null,
                    'stage_key' => $stageKey,
                    'metadata' => [
                        'document_type_label' => $documentTypeLabel,
                        'source' => 'additional_documents',
                    ],
                ]
            );
            $storedCount++;
        }

        return $storedCount;
    }

    private function updateExistingAdditionalDocuments(Request $request, ExaminationReportReplyCase $case, string $stageKey): int
    {
        $existingIds = $request->input('optional_existing_document_ids', []);
        $documentNames = $request->input('optional_document_names', []);
        $documentTypes = $request->input('optional_document_types', []);
        $attachmentNotes = $request->input('optional_attachment_notes', []);
        $remarks = $request->input('optional_document_remarks', []);
        $visibilities = $request->input('optional_document_visibilities', []);
        $updatedCount = 0;

        foreach ($existingIds as $index => $documentId) {
            $document = $case->documents()->whereKey($documentId)->first();
            if (!$document) {
                continue;
            }

            $documentTitle = trim((string) ($documentNames[$index] ?? ''));
            $documentTypeLabel = trim((string) ($documentTypes[$index] ?? ''));

            $document->update([
                'document_title' => $documentTitle !== '' ? $documentTitle : $document->document_title,
                'document_type' => $this->documentTypeFromInput($documentTypeLabel !== '' ? $documentTypeLabel : ($documentTitle ?: $document->document_type)),
                'document_note' => $attachmentNotes[$index] ?? null,
                'remarks' => $remarks[$index] ?? null,
                'visibility' => $visibilities[$index] ?? $document->visibility,
                'stage_key' => $stageKey,
                'metadata' => array_merge($document->metadata ?? [], [
                    'document_type_label' => $documentTypeLabel,
                    'source' => 'additional_documents',
                ]),
            ]);
            $updatedCount++;
        }

        return $updatedCount;
    }

    private function publishDraftFiles(ExaminationReportReplyCase $case, string $stageKey)
    {
        $draftDocuments = $case->documents()
            ->where('stage_key', $stageKey)
            ->where('is_draft', true)
            ->get();

        foreach ($draftDocuments as $document) {
            $document->update(['is_draft' => false]);
        }

        return $draftDocuments->count();
    }

    private function clearStageDraft(ExaminationReportReplyCase $case, string $stageKey): void
    {
        $case->stageDrafts()->where('stage_key', $stageKey)->delete();
    }

    private function storeDraftFiles(Request $request, ExaminationReportReplyCase $case, string $stageKey): int
    {
        $storedCount = 0;

        foreach ($request->allFiles() as $inputName => $files) {
            foreach ($this->flattenFiles($files) as $index => $file) {
                if (!$file) {
                    continue;
                }

                $fieldIndex = $inputName === 'optional_documents'
                    ? count($request->input('optional_existing_document_ids', [])) + $index
                    : $index;
                $documentTitle = $this->draftFileTitle($request, $inputName, $fieldIndex);
                $documentTypeLabel = $this->draftFileType($request, $inputName, $fieldIndex);

                $this->storeDocument(
                    $case,
                    $file,
                    $this->documentTypeFromInput($documentTypeLabel ?: $documentTitle ?: $inputName),
                    'admin',
                    $this->draftFileVisibility($request, $inputName, $fieldIndex),
                    null,
                    [
                        'document_title' => $documentTitle,
                        'document_note' => $request->input("optional_attachment_notes.$fieldIndex"),
                        'remarks' => $request->input("optional_document_remarks.$fieldIndex"),
                        'stage_key' => $stageKey,
                        'is_draft' => true,
                        'metadata' => [
                            'source' => 'stage_draft',
                            'input_name' => $inputName,
                            'input_index' => $fieldIndex,
                            'document_type_label' => $documentTypeLabel,
                        ],
                    ]
                );
                $storedCount++;
            }
        }

        return $storedCount;
    }

    private function flattenFiles($files): array
    {
        if (is_array($files)) {
            return collect($files)->flatten()->all();
        }

        return [$files];
    }

    private function draftFileTitle(Request $request, string $inputName, int $index): ?string
    {
        if ($inputName === 'optional_documents') {
            return trim((string) $request->input("optional_document_names.$index")) ?: null;
        }

        return Str::headline($inputName);
    }

    private function draftFileType(Request $request, string $inputName, int $index): ?string
    {
        if ($inputName === 'optional_documents') {
            return trim((string) $request->input("optional_document_types.$index")) ?: null;
        }

        return Str::headline($inputName);
    }

    private function draftFileVisibility(Request $request, string $inputName, int $index): string
    {
        if ($inputName !== 'optional_documents') {
            return 'client';
        }

        return $request->input("optional_document_visibilities.$index", $request->input('additional_visibility', 'client'));
    }

    private function draftPayload(Request $request): array
    {
        return [
            'fields' => collect($request->except(['_token', '_method']))
                ->reject(fn ($value, $key) => $key === 'stage_key')
                ->all(),
            'file_inputs' => collect($request->allFiles())
                ->map(fn ($files) => collect($this->flattenFiles($files))
                    ->map(fn ($file) => $file?->getClientOriginalName())
                    ->filter()
                    ->values()
                    ->all())
                ->all(),
            'saved_at' => now()->toIso8601String(),
        ];
    }

    private function stageKey(Request $request, ExaminationReportReplyCase $case): string
    {
        $requestedKey = trim((string) $request->input('stage_key', ''));

        if ($requestedKey !== '') {
            return Str::slug($requestedKey, '_');
        }

        $routeName = (string) $request->route()?->getName();
        if (Str::startsWith($routeName, 'admin.examination-reply.')) {
            return Str::slug(Str::afterLast($routeName, '.'), '_');
        }

        return Str::slug($case->current_admin_status, '_');
    }

    private function documentTypeFromInput(?string $value): string
    {
        $documentType = Str::slug(trim((string) $value), '_');

        return $documentType !== '' ? $documentType : 'additional_document';
    }

    private function notifyClient(ExaminationReportReplyCase $case, ?string $type = null, ?string $message = null): void
    {
        $message = $message ?: Workflow::notificationMessage((string) $type);
        Notification::create([
            'user_id' => $case->user_id,
            'type' => 'examination_reply_' . ($type ?: 'updated'),
            'title' => 'Trademark Objection Reply Update',
            'message' => $message,
            'data' => ['case_id' => $case->id, 'case_number' => $case->case_number],
        ]);
        ExaminationReplyNotificationLog::create([
            'case_id' => $case->id,
            'notification_type' => $type ?: 'case_updated',
            'channel' => 'in_app',
            'message' => $message,
            'sent_at' => now(),
            'status' => 'sent',
        ]);
    }

    private function notifyAdmins(ExaminationReportReplyCase $case, string $message): void
    {
        foreach (Admin::query()->pluck('id') as $adminId) {
            // Existing notifications are user-scoped, so keep admin audit in the case log.
        }
        ExaminationReplyNotificationLog::create([
            'case_id' => $case->id,
            'notification_type' => 'admin_update',
            'channel' => 'admin_log',
            'message' => $message,
            'sent_at' => now(),
            'status' => 'logged',
        ]);
    }

    private function emailClient(ExaminationReportReplyCase $case, string $title, string $message, string $actionText, array $attachments = []): bool
    {
        $user = $case->user;

        if (!$user?->email) {
            ExaminationReplyNotificationLog::create([
                'case_id' => $case->id,
                'notification_type' => $title,
                'channel' => 'email',
                'message' => $message,
                'sent_at' => null,
                'status' => 'failed',
            ]);

            return false;
        }

        try {
            Mail::to($user->email)->send(new EventNotification(
                $user,
                $title,
                $message,
                $attachments,
                route('examination-reply.show', $case),
                $actionText
            ));

            ExaminationReplyNotificationLog::create([
                'case_id' => $case->id,
                'notification_type' => $title,
                'channel' => 'email',
                'message' => $message,
                'sent_at' => now(),
                'status' => 'sent',
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Examination reply notification email failed.', [
                'case_id' => $case->id,
                'title' => $title,
                'error' => $exception->getMessage(),
            ]);

            ExaminationReplyNotificationLog::create([
                'case_id' => $case->id,
                'notification_type' => $title,
                'channel' => 'email',
                'message' => $message,
                'sent_at' => null,
                'status' => 'failed',
            ]);

            return false;
        }
    }

    private function authorizeClient(ExaminationReportReplyCase $case): void
    {
        abort_unless(Auth::check() && $case->user_id === Auth::id(), 403);
    }

    private function authorizeDocument(ExaminationReplyDocument $document): void
    {
        if (Auth::guard('admin')->check()) {
            return;
        }

        abort_unless(
            Auth::check()
            && $document->case
            && $document->case->user_id === Auth::id()
            && $document->visibility === 'client'
            && ! $document->is_draft,
            403
        );
    }

    private function adminStatuses(): array
    {
        return [
            Workflow::ADMIN_APPLICATION_RECEIVED,
            Workflow::ADMIN_DOCUMENTS_PENDING,
            Workflow::ADMIN_REPORT_UNDER_REVIEW,
            Workflow::ADMIN_OBJECTION_TYPE_IDENTIFIED,
            Workflow::ADMIN_EVIDENCE_REQUESTED,
            Workflow::ADMIN_EVIDENCE_SUBMITTED,
            Workflow::ADMIN_EVIDENCE_REVIEW_COMPLETED,
            Workflow::ADMIN_RISK_ASSESSMENT_COMPLETED,
            Workflow::ADMIN_PRICING_ASSIGNED,
            Workflow::ADMIN_PAYMENT_PENDING,
            Workflow::ADMIN_PAYMENT_COMPLETED,
            Workflow::ADMIN_REPLY_DRAFTING,
            Workflow::ADMIN_DRAFT_UNDER_REVIEW,
            Workflow::ADMIN_CLIENT_APPROVAL_PENDING,
            Workflow::ADMIN_CHANGES_REQUESTED,
            Workflow::ADMIN_READY_FOR_FILING,
            Workflow::ADMIN_FILED_WITH_REGISTRY,
            Workflow::ADMIN_ACKNOWLEDGMENT_UPLOADED,
            Workflow::ADMIN_AWAITING_REGISTRY_REVIEW,
            Workflow::ADMIN_ACCEPTED,
            Workflow::ADMIN_ACCEPTED_ADVERTISED,
            Workflow::ADMIN_HEARING_ISSUED,
            Workflow::ADMIN_FURTHER_ACTION_REQUIRED,
            Workflow::ADMIN_APPLICATION_ABANDONED,
            Workflow::ADMIN_MATTER_CLOSED,
        ];
    }
}
