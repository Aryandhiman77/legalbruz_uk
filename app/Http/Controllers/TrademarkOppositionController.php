<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentVerificationException;
use App\Mail\EventNotification;
use App\Models\Admin;
use App\Models\Application;
use App\Models\ApplicationStatusLog;
use App\Models\CaseStatusHistory;
use App\Models\DiscountCoupon;
use App\Models\DraftDocument;
use App\Models\LegalReviewPoint;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\OppositionDocument;
use App\Models\OppositionEvidence;
use App\Models\OppositionGround;
use App\Models\OppositionRegistryUpdate;
use App\Models\TrademarkOppositionCase;
use App\Services\RazorpayPaymentVerifier;
use App\Support\PostFilingJourney;
use App\Support\TrademarkOppositionWorkflow;
use App\Support\TrademarkWorkflow;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class TrademarkOppositionController extends Controller
{
    private const ALLOWED_UPLOADS = 'pdf,jpg,jpeg,png,doc,docx';
    private const CLIENT_DOCUMENT_UPLOAD_MAX_KB = 7680;
    private const ADMIN_ADDITIONAL_DOCUMENT_TYPES = [
        'legal_review_optional_document',
        'counter_statement_additional_document',
        'counter_statement_filing_additional_document',
        'oppose_admin_additional_document',
        'notice_draft_additional_document',
        'notice_filing_additional_document',
        'notice_tracking_additional_document',
        'counter_statement_tracking_additional_document',
        'counter_statement_tracking_internal_document',
        'hearing_notice_document',
        'final_order_document',
        'final_order_internal_document',
        'decision_additional_document',
    ];

    public function create(): View
    {
        return view('trademark-opposition.create');
    }

    public function createOppose(): View
    {
        return view('trademark-opposition.oppose-create');
    }

    public function storeOpposeBasic(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'trademark_you_own' => ['required', 'string', 'max:255'],
            'trademark_to_oppose' => ['required', 'string', 'max:255'],
            'opposed_application_number' => ['required', 'string', 'max:120'],
            'trademark_class' => ['required', 'string', 'max:120'],
            'conflict_reason' => ['required', 'string', 'max:3000'],
            'opposed_applicant_name' => ['nullable', 'string', 'max:255'],
            'user_business_name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $case = TrademarkOppositionCase::create([
            ...$data,
            'user_id' => Auth::id(),
            'case_number' => 'OPP-B-' . now()->format('Ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'flow_type' => TrademarkOppositionWorkflow::FLOW_OPPOSE,
            'applicant_name' => $data['user_business_name'],
            'trademark_name' => $data['trademark_you_own'],
            'application_number' => $data['opposed_application_number'],
            'notice_receipt_date' => null,
            'counter_statement_deadline' => null,
            'deadline_status' => null,
            'current_admin_status' => TrademarkOppositionWorkflow::ADMIN_APPLICATION_RECEIVED,
            'current_client_stage' => TrademarkOppositionWorkflow::CLIENT_EVIDENCE_COLLECTION,
        ]);

        $this->recordStatus($case, null, TrademarkOppositionWorkflow::ADMIN_APPLICATION_RECEIVED, 'Client opened trademark opposition filing case.');
        $this->notifyClient($case, 'Case Opened', 'Your trademark opposition matter has been initiated successfully.');

        $linkedApplication = Application::query()
            ->where('application_number', $case->application_number)
            ->first();

        if ($linkedApplication) {
            $linkedApplication->update([
                'opposition_application_id' => $case->id,
                'opposition_status' => PostFilingJourney::OPPOSED,
            ]);
        }

        return redirect()->route('trademark-opposition.oppose.show', $case)
            ->with('success', 'Your trademark opposition filing case has been created.');
    }

    public function showOppose(TrademarkOppositionCase $case): View
    {
        $this->authorizeClient($case);
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE, 404);

        return view('trademark-opposition.oppose-show', [
            'case' => $case->load(['evidence', 'legalReviewPoints', 'draftDocuments', 'statusHistories']),
            'evidenceTypes' => TrademarkOppositionWorkflow::opposeEvidenceTypes(),
            'requiredEvidenceGroups' => TrademarkOppositionWorkflow::opposeRequiredEvidenceGroups(),
            'recommendations' => TrademarkOppositionWorkflow::recommendations(),
        ]);
    }

    public function uploadOpposeEvidence(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        $this->authorizeClient($case);
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE, 404);
        abort_unless(
            $case->statusHistories()
                ->where('new_status', TrademarkOppositionWorkflow::ADMIN_EVIDENCE_COLLECTION)
                ->where('changed_by', 'admin')
                ->exists(),
            403
        );
        abort_if(
            $case->payment_status === 'paid'
            || filled($case->draft_path)
            || in_array($case->current_admin_status, [
                'Payment Completed',
                TrademarkOppositionWorkflow::ADMIN_NOTICE_DRAFTING,
                TrademarkOppositionWorkflow::ADMIN_DRAFT_UNDER_LEGAL_REVIEW,
                TrademarkOppositionWorkflow::ADMIN_CLIENT_APPROVAL_PENDING,
                TrademarkOppositionWorkflow::ADMIN_READY_FOR_FILING,
                TrademarkOppositionWorkflow::ADMIN_NOTICE_FILED,
            ], true),
            403
        );

        $rules = [];
        $messages = [];
        foreach (TrademarkOppositionWorkflow::opposeEvidenceTypes() as $evidenceType => $evidenceLabel) {
            $rules[$evidenceType] = ['nullable', 'array'];
            $rules[$evidenceType . '.*'] = ['file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:10240'];
            $messages[$evidenceType . '.*.uploaded'] = $evidenceLabel . ' could not be uploaded. Please keep each file at 10 MB or smaller and try again.';
            $messages[$evidenceType . '.*.max'] = $evidenceLabel . ' must be 10 MB or smaller.';
            $messages[$evidenceType . '.*.mimes'] = $evidenceLabel . ' must be a PDF, JPG, PNG, DOC, or DOCX file.';
        }

        $request->validate($rules, $messages);

        $uploadedCount = 0;
        foreach (array_keys(TrademarkOppositionWorkflow::opposeEvidenceTypes()) as $evidenceType) {
            foreach ($request->file($evidenceType, []) as $file) {
                $this->storeEvidence($case, $evidenceType, $file, 'client');
                $uploadedCount++;
            }
        }

        if ($uploadedCount === 0) {
            return redirect()
                ->to(route('trademark-opposition.oppose.show', $case) . '#action-center')
                ->with('error', 'Please select at least one evidence document before submitting.');
        }

        $freshCase = $case->fresh('evidence');
        $requiredEvidenceComplete = $this->hasOpposeRequiredEvidence($freshCase);
        $nextStatus = $requiredEvidenceComplete
            ? TrademarkOppositionWorkflow::ADMIN_EVIDENCE_COLLECTION
            : TrademarkOppositionWorkflow::ADMIN_DOCUMENTS_PENDING;

        $this->transition($case, $nextStatus, $nextStatus === TrademarkOppositionWorkflow::ADMIN_LEGAL_ANALYSIS
            ? 'Mandatory opposition filing evidence uploaded.'
            : ($nextStatus === TrademarkOppositionWorkflow::ADMIN_EVIDENCE_COLLECTION
                ? 'Mandatory opposition filing evidence uploaded and awaiting admin review.'
                : 'Mandatory opposition filing evidence is still pending.'));

        $uploadedTypes = $freshCase->evidence
            ->where('file_path', '!=', 'metadata')
            ->where('uploaded_by', 'client')
            ->sortByDesc('id')
            ->unique('evidence_type')
            ->reject(fn (OppositionEvidence $evidence) => $evidence->review_status === 'rejected')
            ->pluck('evidence_type')
            ->all();

        $evidenceLabels = TrademarkOppositionWorkflow::opposeEvidenceTypes();
        $missingEvidence = collect(TrademarkOppositionWorkflow::opposeRequiredEvidenceGroups())
            ->filter(fn (array $types) => count(array_intersect($types, $uploadedTypes)) === 0)
            ->map(fn (array $types) => collect($types)->map(fn (string $type) => $evidenceLabels[$type] ?? $type)->join(' or '))
            ->values();

        $message = $requiredEvidenceComplete
            ? $uploadedCount . ' evidence document' . ($uploadedCount === 1 ? '' : 's') . ' uploaded successfully. Your evidence is now waiting for admin review.'
            : $uploadedCount . ' evidence document' . ($uploadedCount === 1 ? '' : 's') . ' uploaded successfully. Still required: ' . $missingEvidence->join(', ') . '.';

        return redirect()
            ->to(route('trademark-opposition.oppose.show', $case) . '#action-center')
            ->with('success', $message);
    }

    public function completeOpposePayment(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        $this->authorizeClient($case);
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE, 404);

        return back()->with('error', 'Please complete the Razorpay checkout to continue.');
    }

    public function createOpposePaymentOrder(Request $request, TrademarkOppositionCase $case): JsonResponse
    {
        $this->authorizeClient($case);
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE, 404);

        $data = $request->validate([
            'success_disclaimer' => ['accepted'],
            'discount_coupon_id' => ['nullable', 'integer'],
        ], [
            'success_disclaimer.accepted' => 'Please confirm that you understand Legal Bruz cannot guarantee success.',
        ]);

        if ($case->payment_status === 'paid') {
            return response()->json(['status' => 'error', 'message' => 'Payment is already complete.'], 422);
        }

        $pricing = $this->discountedPackagePricing(
            'opposition_filing',
            (float) ($case->total_amount ?: $case->package_price),
            isset($data['discount_coupon_id']) ? (int) $data['discount_coupon_id'] : null
        );
        $amount = $pricing['payable_amount'];
        if ($amount <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Package amount is not configured for this case.'], 422);
        }

        try {
            $razorpayKeyId = config('razorpay.key_id');
            $razorpaySecret = config('razorpay.key_secret');

            if (!$razorpayKeyId || !$razorpaySecret) {
                throw new \Exception('Razorpay credentials are not configured.');
            }

            $amountInPaise = (int) round($amount * 100);
            $receipt = 'opp_flow_b_' . $case->id . '_' . time();

            $ch = curl_init('https://api.razorpay.com/v1/orders');
            curl_setopt($ch, CURLOPT_USERPWD, "$razorpayKeyId:$razorpaySecret");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'amount' => $amountInPaise,
                'currency' => config('razorpay.currency', 'INR'),
                'receipt' => $receipt,
                'description' => ($case->package_name ?: 'Notice of Opposition Filing Package') . ' - ' . $case->case_number,
                'notes' => [
                    'case_id' => (string) $case->id,
                    'case_number' => (string) $case->case_number,
                    'payment_type' => 'opposition_notice_filing_package',
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

            if ($response === false || $curlError) {
                throw new \Exception($curlError ?: 'Unable to contact Razorpay.');
            }
            if (!in_array($httpCode, [200, 201], true)) {
                throw new \Exception('Failed to create Razorpay order.');
            }

            $order = json_decode($response, true);
            if (!is_array($order) || empty($order['id'])) {
                throw new \Exception('Razorpay returned an invalid order response.');
            }

            $orders = $request->session()->get('trademark_opposition_oppose_payment_orders', []);
            $orders[$order['id']] = [
                'case_id' => $case->id,
                'amount' => $amountInPaise,
                'original_amount' => $pricing['original_amount'],
                'payable_amount' => $pricing['payable_amount'],
                'discount_amount' => $pricing['discount_amount'],
                'coupon_label' => $pricing['coupon_label'],
            ];
            $request->session()->put('trademark_opposition_oppose_payment_orders', $orders);

            return response()->json([
                'status' => 'success',
                'order_id' => $order['id'],
                'amount' => $amountInPaise,
                'currency' => $order['currency'] ?? config('razorpay.currency', 'INR'),
                'key' => $razorpayKeyId,
                'user_email' => Auth::user()->email,
                'user_phone' => Auth::user()->phone ?? $case->mobile_number,
                'user_name' => Auth::user()->name ?? $case->applicant_name,
                'description' => $case->package_name ?: 'Notice of Opposition Filing Package',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create payment order: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function verifyOpposePaymentSignature(Request $request, TrademarkOppositionCase $case, RazorpayPaymentVerifier $verifier): JsonResponse
    {
        $this->authorizeClient($case);
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE, 404);

        if ($case->payment_status === 'paid') {
            return response()->json([
                'status' => 'success',
                'message' => 'Payment is already complete.',
                'redirect_url' => route('trademark-opposition.oppose.show', $case),
            ]);
        }

        $validated = $request->validate([
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        $orders = $request->session()->get('trademark_opposition_oppose_payment_orders', []);
        $order = $orders[$validated['razorpay_order_id']] ?? null;

        if (!$order || (int) ($order['case_id'] ?? 0) !== (int) $case->id) {
            return response()->json(['status' => 'error', 'message' => 'This Razorpay order does not belong to the current opposition case.'], 422);
        }

        try {
            $verifier->verifyCaptured(
                $validated['razorpay_payment_id'],
                $validated['razorpay_order_id'],
                $validated['razorpay_signature'],
                $validated['razorpay_order_id'],
                (int) ($order['amount'] ?? 0),
                (string) config('razorpay.currency', 'GBP'),
            );
        } catch (PaymentVerificationException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], $exception->httpStatus());
        }

        $case->update([
            'payment_status' => 'paid',
            'payment_reference' => $validated['razorpay_order_id'],
            'transaction_id' => $validated['razorpay_payment_id'],
            'paid_at' => now(),
            'original_package_price' => $order['original_amount'] ?? $case->package_price,
            'paid_amount' => $order['payable_amount'] ?? $case->package_price,
            'discount_amount' => $order['discount_amount'] ?? 0,
            'coupon_label' => $order['coupon_label'] ?? null,
        ]);

        $this->transition($case, 'Payment Completed', 'Client completed Notice of Opposition filing package payment.', 'client');

        unset($orders[$validated['razorpay_order_id']]);
        $request->session()->put('trademark_opposition_oppose_payment_orders', $orders);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment verified successfully.',
            'redirect_url' => route('trademark-opposition.oppose.show', $case),
        ]);
    }

    public function viewOpposePaymentInvoice(TrademarkOppositionCase $case)
    {
        $this->authorizeClient($case);
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE, 404);

        return $this->viewPaymentInvoice($case);
    }

    public function approveOpposeDraft(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        $this->authorizeClient($case);
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE, 404);

        $data = $request->validate(['client_approval_note' => ['nullable', 'string', 'max:3000']]);
        $case->draftDocuments()->latest()->first()?->update([
            'client_status' => 'approved',
            'client_comment' => $data['client_approval_note'] ?? null,
        ]);
        $case->update([
            'client_approval_status' => 'approved',
            'client_approval_note' => $data['client_approval_note'] ?? null,
            'client_change_request' => null,
        ]);
        $this->transition($case, TrademarkOppositionWorkflow::ADMIN_READY_FOR_FILING, 'Client approved Notice of Opposition draft.', 'client');

        return back()->with('success', 'Draft approved. The matter is ready for filing.');
    }

    public function requestOpposeDraftChanges(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        $this->authorizeClient($case);
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE, 404);

        $data = $request->validate([
            'client_change_request' => ['required', 'string', 'max:3000'],
        ]);

        $case->draftDocuments()->latest()->first()?->update([
            'client_status' => 'changes_requested',
            'client_comment' => $data['client_change_request'],
        ]);
        $case->update([
            'client_approval_status' => 'changes_requested',
            'client_approval_note' => null,
            'client_change_request' => $data['client_change_request'],
        ]);
        $this->transition($case, TrademarkOppositionWorkflow::ADMIN_NOTICE_DRAFTING, 'Client requested draft reupload: ' . $data['client_change_request'], 'client');

        return back()->with('success', 'Your change request has been sent to the team.');
    }

    public function storeBasic(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'applicant_name' => ['required', 'string', 'max:255'],
            'trademark_name' => ['required', 'string', 'max:255'],
            'application_number' => ['required', 'string', 'max:120'],
            'trademark_class' => ['required', 'string', 'max:120'],
            'mobile_number' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'notice_receipt_date' => ['required', 'date'],
        ]);

        $receiptDate = Carbon::parse($data['notice_receipt_date'])->startOfDay();
        $deadline = $receiptDate->copy()->addMonthsNoOverflow(2);

        $case = TrademarkOppositionCase::create([
            ...$data,
            'user_id' => Auth::id(),
            'case_number' => 'OPP-A-' . now()->format('Ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'flow_type' => TrademarkOppositionWorkflow::FLOW_DEFEND,
            'notice_receipt_date' => $receiptDate,
            'counter_statement_deadline' => $deadline,
            'deadline_status' => TrademarkOppositionWorkflow::deadlineStatus($deadline),
            'current_admin_status' => TrademarkOppositionWorkflow::ADMIN_APPLICATION_RECEIVED,
            'current_client_stage' => TrademarkOppositionWorkflow::CLIENT_CASE_OPENED,
        ]);

        $this->recordStatus($case, null, TrademarkOppositionWorkflow::ADMIN_APPLICATION_RECEIVED, 'Client opened opposition defence case.');
        $this->notifyClient($case, 'Case Opened', 'Your opposition matter has been initiated successfully.');

        $linkedApplication = Application::query()
            ->where('application_number', $case->application_number)
            ->first();

        if ($linkedApplication) {
            $linkedApplication->update([
                'opposition_defence_case_id' => $case->id,
                'opposition_defence_status' => 'started',
            ]);
        }

        return redirect()->route('trademark-opposition.show', $case)
            ->with('success', 'Your opposition defence case has been created.');
    }

    public function show(TrademarkOppositionCase $case): View|RedirectResponse
    {
        $this->authorizeClient($case);

        if ($case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE) {
            return redirect()->route('trademark-opposition.oppose.show', $case);
        }

        $this->normalizeFiledDefenceStage($case);

        return view('trademark-opposition.show', [
            'case' => $case->load(['documents', 'evidence', 'grounds', 'statusHistories']),
            'requiredDocuments' => TrademarkOppositionWorkflow::requiredDocuments(),
            'optionalDocuments' => TrademarkOppositionWorkflow::optionalDocuments(),
            'evidenceTypes' => TrademarkOppositionWorkflow::evidenceTypes(),
            'riskNotes' => TrademarkOppositionWorkflow::riskNotes(),
            'actionOnly' => false,
        ]);
    }

    public function actionCenter(TrademarkOppositionCase $case): View
    {
        $this->authorizeClient($case);
        $this->normalizeFiledDefenceStage($case);

        return view('trademark-opposition.show', [
            'case' => $case->load(['documents', 'evidence', 'grounds', 'statusHistories']),
            'requiredDocuments' => TrademarkOppositionWorkflow::requiredDocuments(),
            'optionalDocuments' => TrademarkOppositionWorkflow::optionalDocuments(),
            'evidenceTypes' => TrademarkOppositionWorkflow::evidenceTypes(),
            'riskNotes' => TrademarkOppositionWorkflow::riskNotes(),
            'actionOnly' => true,
        ]);
    }

    public function uploadDocuments(Request $request, TrademarkOppositionCase $case): RedirectResponse|JsonResponse
    {
        $this->authorizeClient($case);

        $documentKeys = array_keys(TrademarkOppositionWorkflow::requiredDocuments() + TrademarkOppositionWorkflow::optionalDocuments());
        $rules = [];

        foreach ($documentKeys as $documentKey) {
            $rules[$documentKey] = ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:' . self::CLIENT_DOCUMENT_UPLOAD_MAX_KB];
        }

        $request->validate($rules);

        foreach ($documentKeys as $documentKey) {
            if (!$request->hasFile($documentKey)) {
                continue;
            }

            $previousDocument = $case->documents()
                ->where('document_type', $documentKey)
                ->latest('id')
                ->first();
            $reviewStatus = $previousDocument?->review_status === 'rejected' ? 'reuploaded' : 'pending';

            $this->storeDocument(
                $case,
                $documentKey,
                $request->file($documentKey),
                'client',
                array_key_exists($documentKey, TrademarkOppositionWorkflow::requiredDocuments()),
                $reviewStatus
            );
        }

        $nextStatus = $case->fresh()->hasRequiredDocuments()
            ? TrademarkOppositionWorkflow::ADMIN_OPPOSITION_REVIEW
            : TrademarkOppositionWorkflow::ADMIN_DOCUMENTS_PENDING;

        $this->transition($case, $nextStatus, $nextStatus === TrademarkOppositionWorkflow::ADMIN_OPPOSITION_REVIEW
            ? 'Mandatory opposition documents uploaded.'
            : 'Mandatory opposition documents are still pending.');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Documents uploaded successfully.',
                'next_status' => $nextStatus,
            ]);
        }

        return back()->with('success', 'Documents uploaded successfully.');
    }

    public function uploadEvidence(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        $this->authorizeClient($case);

        $isDraft = $request->boolean('save_draft');
        $rules = [
            'first_use_date' => ['nullable', 'date'],
            'currently_in_use' => ['nullable', 'in:yes,no'],
            'used_continuously' => ['nullable', 'in:yes,no'],
            'annual_sales' => ['nullable', 'string', 'max:120'],
            'marketing_spend' => ['nullable', 'string', 'max:120'],
            'other_relevant_details' => ['nullable', 'string', 'max:3000'],
            'save_draft' => ['nullable', 'boolean'],
            'requested_evidence' => ['nullable', 'array'],
        ];

        foreach (array_keys(TrademarkOppositionWorkflow::evidenceTypes()) as $evidenceType) {
            $rules[$evidenceType] = ['nullable', 'array'];
            $rules[$evidenceType . '.*'] = ['file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:10240'];
        }

        foreach (($case->requested_evidence_types ?? []) as $evidenceLabel) {
            $evidenceKey = $this->evidenceRequestKey($evidenceLabel);
            $existingEvidenceQuery = $case->evidence()
                ->where('file_path', '!=', 'metadata')
                ->where('evidence_type', $evidenceKey);
            if (Schema::hasColumn('opposition_evidence', 'review_status')) {
                $existingEvidenceQuery->where('review_status', '!=', 'rejected');
            }
            $hasExistingEvidence = $existingEvidenceQuery->exists();
            $rules['requested_evidence.' . $evidenceKey] = [($isDraft || $hasExistingEvidence) ? 'nullable' : 'required', 'array', 'min:1'];
            $rules['requested_evidence.' . $evidenceKey . '.*'] = ['file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:10240'];
        }

        $data = $request->validate($rules);

        if (!empty($data['first_use_date'])) {
            $case->update(['first_use_date' => $data['first_use_date']]);
        }

        $meta = array_filter([
            'First Use Date' => $data['first_use_date'] ?? null,
            'Currently In Use' => $data['currently_in_use'] ?? null,
            'Used Continuously' => $data['used_continuously'] ?? null,
            'Annual Sales' => $data['annual_sales'] ?? null,
            'Marketing Spend' => $data['marketing_spend'] ?? null,
            'Other Relevant Details' => $data['other_relevant_details'] ?? null,
        ]);

        if ($meta !== []) {
            $case->evidence()->create([
                'evidence_type' => 'evidence_intake_details',
                'file_path' => 'metadata',
                'file_name' => json_encode($meta),
                'file_type' => 'json',
                'file_size' => strlen(json_encode($meta)),
                'uploaded_by' => 'client',
            ]);
        }

        foreach (array_keys(TrademarkOppositionWorkflow::evidenceTypes()) as $evidenceType) {
            foreach ($request->file($evidenceType, []) as $file) {
                $path = $file->store('opposition/evidence/' . $case->id, 'public');
                OppositionEvidence::create([
                    'case_id' => $case->id,
                    'evidence_type' => $evidenceType,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getClientOriginalExtension(),
                    'file_size' => $file->getSize(),
                    'uploaded_by' => 'client',
                ]);
            }
        }

        foreach (($case->requested_evidence_types ?? []) as $evidenceLabel) {
            $evidenceKey = $this->evidenceRequestKey($evidenceLabel);
            foreach ($request->file('requested_evidence.' . $evidenceKey, []) as $file) {
                $path = $file->store('opposition/evidence/' . $case->id, 'public');
                OppositionEvidence::create([
                    'case_id' => $case->id,
                    'evidence_type' => $evidenceKey,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getClientOriginalExtension(),
                    'file_size' => $file->getSize(),
                    'uploaded_by' => 'client',
                ]);
            }
        }

        if ($isDraft) {
            return back()->with('success', 'Evidence draft saved successfully.');
        }

        $this->transition($case, TrademarkOppositionWorkflow::ADMIN_LEGAL_ANALYSIS, 'Client submitted requested evidence.', 'client');

        return back()->with('success', 'Evidence submitted successfully.');
    }

    public function uploadOpposeThirdPartyEvidence(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        $this->authorizeClient($case);
        abort_unless(in_array($case->flow_type, [
            TrademarkOppositionWorkflow::FLOW_OPPOSE,
            TrademarkOppositionWorkflow::FLOW_DEFEND,
        ], true), 404);

        $latestThirdPartyEvidence = $case->evidence()
            ->where('file_path', '!=', 'metadata')
            ->where('uploaded_by', 'client')
            ->where('evidence_type', 'like', 'third_party_requested_evidence_%')
            ->get()
            ->sortByDesc('id')
            ->unique('evidence_type')
            ->values();
        $rejectedThirdPartyEvidence = $latestThirdPartyEvidence
            ->filter(fn (OppositionEvidence $evidence) => $evidence->review_status === 'rejected')
            ->values();
        abort_unless($case->third_party_evidence_pending || $rejectedThirdPartyEvidence->isNotEmpty(), 403);

        $requests = collect($case->third_party_evidence_requests ?? [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values();
        if ($rejectedThirdPartyEvidence->isNotEmpty()) {
            $requests = $rejectedThirdPartyEvidence
                ->map(fn (OppositionEvidence $evidence) => Str::headline(Str::after($evidence->evidence_type, 'third_party_requested_evidence_')))
                ->values();
        }

        abort_if($requests->isEmpty(), 422, 'No additional evidence is currently requested.');

        $rules = [];
        foreach ($requests as $index => $label) {
            $rules['requested_documents.' . $index] = ['required', 'array', 'min:1'];
            $rules['requested_documents.' . $index . '.*'] = ['file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:10240'];
        }
        $rules['note_to_admin'] = ['nullable', 'string', 'max:3000'];

        $data = $request->validate($rules);
        $noteToAdmin = trim((string) ($data['note_to_admin'] ?? ''));

        foreach ($requests as $index => $label) {
            foreach ($request->file('requested_documents.' . $index, []) as $file) {
                $document = $this->storeEvidence(
                    $case,
                    'third_party_requested_evidence_' . Str::slug($label, '_'),
                    $file,
                    'client'
                );
                $document->update(['review_note' => $label]);
            }
        }

        $case->update(['third_party_evidence_pending' => false]);
        $statusNote = $case->flow_type === TrademarkOppositionWorkflow::FLOW_DEFEND
            ? 'Client submitted the registry-stage documents requested by admin.'
            : 'Client submitted the additional evidence requested during Evidence Stage.';
        if ($noteToAdmin !== '') {
            $statusNote .= ' Note to admin: ' . $noteToAdmin;
        }

        if ($case->flow_type === TrademarkOppositionWorkflow::FLOW_DEFEND) {
            $this->recordStatus(
                $case,
                $case->current_admin_status,
                $case->current_admin_status,
                $statusNote,
                'client'
            );

            return back()->with('success', 'Requested documents submitted successfully.');
        }

        $hasEnteredEvidenceStage = $case->statusHistories()
            ->whereIn('new_status', [
                TrademarkOppositionWorkflow::ADMIN_EVIDENCE_BY_OPPONENT,
                TrademarkOppositionWorkflow::ADMIN_EVIDENCE_BY_APPLICANT,
                TrademarkOppositionWorkflow::ADMIN_EVIDENCE_IN_REPLY,
            ])
            ->exists();

        if ($hasEnteredEvidenceStage && in_array($case->current_admin_status, [
            TrademarkOppositionWorkflow::ADMIN_EVIDENCE_COLLECTION,
            TrademarkOppositionWorkflow::ADMIN_DOCUMENTS_PENDING,
        ], true)) {
            $this->transition($case, TrademarkOppositionWorkflow::ADMIN_EVIDENCE_BY_OPPONENT, $statusNote, 'client');
        } else {
            $this->recordStatus(
                $case,
                $case->current_admin_status,
                $case->current_admin_status,
                $statusNote,
                'client'
            );
        }

        return back()->with('success', 'The requested evidence was submitted successfully.');
    }

    public function completePayment(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        $this->authorizeClient($case);

        return back()->with('error', 'Please complete the Razorpay checkout to continue.');
    }

    public function createPaymentOrder(Request $request, TrademarkOppositionCase $case): JsonResponse
    {
        $this->authorizeClient($case);

        $data = $request->validate([
            'success_disclaimer' => ['accepted'],
            'discount_coupon_id' => ['nullable', 'integer'],
        ], [
            'success_disclaimer.accepted' => 'Please confirm that you understand Legal Bruz cannot guarantee success.',
        ]);

        if ($case->payment_status === 'paid') {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment is already complete.',
            ], 422);
        }

        $pricing = $this->discountedPackagePricing(
            'opposition_defence_package',
            (float) $case->package_price,
            isset($data['discount_coupon_id']) ? (int) $data['discount_coupon_id'] : null
        );
        $amount = $pricing['payable_amount'];

        if ($amount <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Package amount is not configured for this case.',
            ], 422);
        }

        try {
            $razorpayKeyId = config('razorpay.key_id');
            $razorpaySecret = config('razorpay.key_secret');

            if (!$razorpayKeyId || !$razorpaySecret) {
                throw new \Exception('Razorpay credentials are not configured.');
            }

            $amountInPaise = (int) round($amount * 100);
            $receipt = 'opp_pkg_' . $case->id . '_' . time();

            $ch = curl_init('https://api.razorpay.com/v1/orders');
            curl_setopt($ch, CURLOPT_USERPWD, "$razorpayKeyId:$razorpaySecret");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'amount' => $amountInPaise,
                'currency' => config('razorpay.currency', 'INR'),
                'receipt' => $receipt,
                'description' => ($case->package_name ?: 'Opposition Defence Package') . ' - ' . $case->case_number,
                'notes' => [
                    'case_id' => (string) $case->id,
                    'case_number' => (string) $case->case_number,
                    'payment_type' => 'opposition_defence_package',
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

            if ($response === false || $curlError) {
                throw new \Exception($curlError ?: 'Unable to contact Razorpay.');
            }

            if (!in_array($httpCode, [200, 201], true)) {
                throw new \Exception('Failed to create Razorpay order.');
            }

            $order = json_decode($response, true);

            if (!is_array($order) || empty($order['id'])) {
                throw new \Exception('Razorpay returned an invalid order response.');
            }

            $orders = $request->session()->get('trademark_opposition_payment_orders', []);
            $orders[$order['id']] = [
                'case_id' => $case->id,
                'amount' => $amountInPaise,
                'original_amount' => $pricing['original_amount'],
                'payable_amount' => $pricing['payable_amount'],
                'discount_amount' => $pricing['discount_amount'],
                'coupon_label' => $pricing['coupon_label'],
            ];
            $request->session()->put('trademark_opposition_payment_orders', $orders);

            return response()->json([
                'status' => 'success',
                'order_id' => $order['id'],
                'amount' => $amountInPaise,
                'currency' => $order['currency'] ?? config('razorpay.currency', 'INR'),
                'key' => $razorpayKeyId,
                'user_email' => Auth::user()->email,
                'user_phone' => Auth::user()->phone ?? $case->mobile_number,
                'user_name' => Auth::user()->name ?? $case->applicant_name,
                'description' => $case->package_name ?: 'Opposition Defence Package',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create payment order: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function verifyPaymentSignature(Request $request, TrademarkOppositionCase $case, RazorpayPaymentVerifier $verifier): JsonResponse
    {
        $this->authorizeClient($case);

        if ($case->payment_status === 'paid') {
            return response()->json([
                'status' => 'success',
                'message' => 'Payment is already complete.',
                'redirect_url' => route('trademark-opposition.show', $case),
            ]);
        }

        $validated = $request->validate([
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        $orders = $request->session()->get('trademark_opposition_payment_orders', []);
        $order = $orders[$validated['razorpay_order_id']] ?? null;

        if (!$order || (int) ($order['case_id'] ?? 0) !== (int) $case->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This Razorpay order does not belong to the current opposition case.',
            ], 422);
        }

        try {
            $verifier->verifyCaptured(
                $validated['razorpay_payment_id'],
                $validated['razorpay_order_id'],
                $validated['razorpay_signature'],
                $validated['razorpay_order_id'],
                (int) ($order['amount'] ?? 0),
                (string) config('razorpay.currency', 'GBP'),
            );
        } catch (PaymentVerificationException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], $exception->httpStatus());
        }

        $updates = ['payment_status' => 'paid'];

        if (Schema::hasColumn('trademark_opposition_cases', 'payment_reference')) {
            $updates['payment_reference'] = $validated['razorpay_order_id'];
        }

        if (Schema::hasColumn('trademark_opposition_cases', 'transaction_id')) {
            $updates['transaction_id'] = $validated['razorpay_payment_id'];
        }

        if (Schema::hasColumn('trademark_opposition_cases', 'paid_at')) {
            $updates['paid_at'] = now();
        }

        if (Schema::hasColumn('trademark_opposition_cases', 'original_package_price')) {
            $updates['original_package_price'] = $order['original_amount'] ?? $case->package_price;
        }

        if (Schema::hasColumn('trademark_opposition_cases', 'paid_amount')) {
            $updates['paid_amount'] = $order['payable_amount'] ?? $case->package_price;
        }

        if (Schema::hasColumn('trademark_opposition_cases', 'discount_amount')) {
            $updates['discount_amount'] = $order['discount_amount'] ?? 0;
        }

        if (Schema::hasColumn('trademark_opposition_cases', 'coupon_label')) {
            $updates['coupon_label'] = $order['coupon_label'] ?? null;
        }

        $case->update($updates);
        $this->transition($case, 'Payment Completed', 'Client completed opposition defence package payment.', 'client');

        unset($orders[$validated['razorpay_order_id']]);
        $request->session()->put('trademark_opposition_payment_orders', $orders);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment verified successfully.',
            'redirect_url' => route('trademark-opposition.show', $case),
        ]);
    }

    public function viewPaymentInvoice(TrademarkOppositionCase $case)
    {
        $this->authorizeClient($case);

        abort_unless(
            $case->payment_status === 'paid'
            && filled($case->payment_reference)
            && filled($case->transaction_id)
            && filled($case->paid_at),
            404
        );

        $issuedAt = $case->paid_at ?? $case->updated_at ?? now();
        $isOppositionFiling = $case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE;
        $invoicePrefix = $isOppositionFiling ? 'OPF-INV-' : 'OPD-INV-';
        $invoiceFilePrefix = $isOppositionFiling ? 'opposition-filing-invoice-' : 'opposition-defence-invoice-';

        $pdf = \PDF::loadView('trademark-opposition.payment-invoice', [
            'case' => $case,
            'user' => $case->user,
            'invoiceNumber' => $invoicePrefix . $issuedAt->format('Y') . '-' . $case->id,
            'issuedAt' => $issuedAt,
            'firmName' => config('app.name', 'Legal Bruz'),
            'firmEmail' => config('mail.from.address'),
        ])->setPaper('a4');

        return $pdf->stream($invoiceFilePrefix . $case->case_number . '.pdf');
    }

    public function approveDraft(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        $this->authorizeClient($case);

        $data = $request->validate([
            'client_approval_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $case->update([
            'client_approval_status' => 'approved',
            'client_approval_note' => $data['client_approval_note'] ?? null,
            'client_change_request' => null,
        ]);

        $note = trim((string) ($data['client_approval_note'] ?? ''));
        $this->transition(
            $case,
            TrademarkOppositionWorkflow::ADMIN_READY_FOR_FILING,
            'Client approved counter statement draft.' . ($note !== '' ? ' Approval note: ' . $note : ''),
            'client'
        );

        return back()->with('success', 'Draft approved. The matter is ready for filing.');
    }

    public function requestDraftChanges(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        $this->authorizeClient($case);

        $data = $request->validate([
            'client_change_request' => ['required', 'string', 'max:3000'],
        ]);

        $case->update([
            'client_approval_status' => 'changes_requested',
            'client_approval_note' => null,
            'client_change_request' => $data['client_change_request'],
        ]);

        $this->transition(
            $case,
            TrademarkOppositionWorkflow::ADMIN_COUNTER_STATEMENT_DRAFTING,
            'Client requested draft reupload: ' . $data['client_change_request'],
            'client'
        );

        return back()->with('success', 'Your reupload request has been sent to the legal team.');
    }

    public function adminIndex(Request $request): View
    {
        $query = TrademarkOppositionCase::query()
            ->where('flow_type', TrademarkOppositionWorkflow::FLOW_DEFEND)
            ->with('user')
            ->latest();

        if ($request->filled('status')) {
            $query->where('current_admin_status', $request->status);
        }
        if ($request->filled('search')) {
            $search = '%'.trim((string) $request->string('search')).'%';
            $query->where(function ($query) use ($search) {
                $query->where('case_number', 'like', $search)
                    ->orWhere('application_number', 'like', $search)
                    ->orWhere('applicant_name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('trademark_name', 'like', $search);
            });
        }

        return view('admin.trademark-opposition.index', [
            'cases' => $query->paginate(20)->withQueryString(),
            'statuses' => $this->adminStatuses(),
            'selectedStatus' => $request->status,
        ]);
    }

    public function adminOpposeIndex(Request $request): View
    {
        $query = TrademarkOppositionCase::query()
            ->where('flow_type', TrademarkOppositionWorkflow::FLOW_OPPOSE)
            ->with('user')
            ->latest();

        if ($request->filled('status')) {
            $query->where('current_admin_status', $request->status);
        }
        if ($request->filled('search')) {
            $search = '%'.trim((string) $request->string('search')).'%';
            $query->where(function ($query) use ($search) {
                $query->where('case_number', 'like', $search)
                    ->orWhere('opposed_application_number', 'like', $search)
                    ->orWhere('user_business_name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('trademark_you_own', 'like', $search)
                    ->orWhere('trademark_to_oppose', 'like', $search);
            });
        }

        return view('admin.trademark-opposition.oppose-index', [
            'cases' => $query->paginate(20)->withQueryString(),
            'statuses' => $this->adminOpposeStatuses(),
            'selectedStatus' => $request->status,
        ]);
    }

    public function destroyAdditionalDocument(Request $request, TrademarkOppositionCase $case, OppositionEvidence $document): RedirectResponse
    {
        abort_unless((int) $document->case_id === (int) $case->id, 404);
        abort_unless($document->uploaded_by === 'admin', 403);
        abort_unless(in_array($document->evidence_type, self::ADMIN_ADDITIONAL_DOCUMENT_TYPES, true), 403);

        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        $this->recordStatus(
            $case,
            $case->current_admin_status,
            $case->current_admin_status,
            'Admin removed additional document: ' . ($document->review_note ?: $document->file_name),
            'admin'
        );

        return back()->with('success', 'Additional document removed.');
    }

    public function adminOpposeShow(TrademarkOppositionCase $case): View
    {
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE, 404);

        return view('admin.trademark-opposition.oppose-show', [
            'case' => $case->load(['user', 'evidence', 'legalReviewPoints', 'draftDocuments', 'statusHistories', 'notificationLogs', 'registryUpdates']),
            'evidenceTypes' => TrademarkOppositionWorkflow::opposeEvidenceTypes(),
            'reviewPoints' => TrademarkOppositionWorkflow::legalReviewPoints(),
            'recommendations' => TrademarkOppositionWorkflow::recommendations(),
            'trackingStatuses' => $this->adminOpposeTrackingStatuses(),
        ]);
    }

    public function adminOpposeReview(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE, 404);

        $data = $request->validate([
            'legal_points' => ['required', 'array', 'min:1'],
            'legal_points.*.review_point' => ['required', 'string', 'max:160', Rule::in(TrademarkOppositionWorkflow::legalReviewPoints())],
            'legal_points.*.admin_note' => ['nullable', 'string', 'max:3000'],
            'legal_points.*.is_client_visible' => ['nullable', 'boolean'],
            'recommendation_level' => ['required', 'in:Strong Opposition Case,Moderate Case,Weak Case'],
            'recommendation_note' => ['required', 'string', 'max:3000'],
            'recommendation_note_visible' => ['nullable', 'boolean'],
            'package_price' => ['required', 'numeric', 'min:1'],
            'package_name' => ['required', 'string', 'max:160'],
            'included_services' => ['required', 'string', 'max:3000'],
        ]);

        $case->legalReviewPoints()->delete();

        $savedPoints = [];
        foreach ($data['legal_points'] as $pointData) {
            $point = $pointData['review_point'];
            if (in_array($point, $savedPoints, true)) {
                continue;
            }

            LegalReviewPoint::create([
                'case_id' => $case->id,
                'review_point' => $point,
                'admin_note' => $pointData['admin_note'] ?? null,
                'is_client_visible' => (bool) ($pointData['is_client_visible'] ?? false),
            ]);
            $savedPoints[] = $point;
        }

        $case->forceFill([
            'recommendation_level' => $data['recommendation_level'],
            'recommendation_note' => $data['recommendation_note'] ?? null,
            'recommendation_note_visible' => (bool) ($data['recommendation_note_visible'] ?? false),
            'package_name' => $data['package_name'],
            'package_price' => $data['package_price'],
            'total_amount' => $data['package_price'],
            'included_services' => $this->linesToArray($data['included_services']),
        ])->save();

        $this->transition($case, TrademarkOppositionWorkflow::ADMIN_LEGAL_ANALYSIS_COMPLETED, 'Admin completed Flow B legal review.', 'admin');
        $this->notifyClient($case, 'Legal Review Completed', 'Your legal review and recommendation have been completed. Please log in to view the recommendation.');

        return back()->with('success', 'Legal review and recommendation saved.');
    }

    public function adminOpposeReviewEvidence(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE, 404);

        $data = $request->validate([
            'evidence_ids' => ['required', 'array', 'min:1'],
            'evidence_ids.*' => ['integer'],
            'action' => ['required', 'in:reviewed,rejected'],
            'note' => ['required_if:action,rejected', 'nullable', 'string', 'max:1500'],
        ]);

        $evidenceItems = $case->evidence()
            ->where('file_path', '!=', 'metadata')
            ->where('uploaded_by', 'client')
            ->whereIn('id', $data['evidence_ids'])
            ->get();

        if ($evidenceItems->isEmpty()) {
            return back()->withErrors(['evidence' => 'Please select at least one uploaded evidence file.']);
        }

        $evidenceLabels = TrademarkOppositionWorkflow::opposeEvidenceTypes();
        $reviewNote = trim((string) ($data['note'] ?? ''));
        $selectedLabels = [];
        $isEvidenceStageEvidence = $evidenceItems->contains(
            fn (OppositionEvidence $evidence) => Str::startsWith($evidence->evidence_type, 'third_party_requested_evidence_')
        );
        $hasEnteredEvidenceStage = $case->statusHistories()
            ->whereIn('new_status', [
                TrademarkOppositionWorkflow::ADMIN_EVIDENCE_BY_OPPONENT,
                TrademarkOppositionWorkflow::ADMIN_EVIDENCE_BY_APPLICANT,
                TrademarkOppositionWorkflow::ADMIN_EVIDENCE_IN_REPLY,
            ])
            ->exists();
        $restoreEvidenceStageStatus = $hasEnteredEvidenceStage && in_array($case->current_admin_status, [
            TrademarkOppositionWorkflow::ADMIN_EVIDENCE_COLLECTION,
            TrademarkOppositionWorkflow::ADMIN_DOCUMENTS_PENDING,
        ], true);
        $labelForEvidence = static function (OppositionEvidence $evidence) use ($evidenceLabels): string {
            if (Str::startsWith($evidence->evidence_type, 'third_party_requested_evidence_')) {
                return Str::headline(Str::after($evidence->evidence_type, 'third_party_requested_evidence_'));
            }

            return $evidenceLabels[$evidence->evidence_type] ?? Str::headline(str_replace('_', ' ', $evidence->evidence_type));
        };

        foreach ($evidenceItems as $evidence) {
            $selectedLabels[] = $labelForEvidence($evidence);
            $isThirdPartyEvidence = Str::startsWith($evidence->evidence_type, 'third_party_requested_evidence_');

            $evidence->update([
                'review_status' => $data['action'],
                'review_note' => $isThirdPartyEvidence ? $labelForEvidence($evidence) : ($reviewNote ?: null),
                'reviewed_at' => now(),
                'reviewed_by' => 'admin',
            ]);
        }

        if ($data['action'] === 'rejected') {
            if ($isEvidenceStageEvidence) {
                $case->update([
                    'third_party_evidence_pending' => true,
                    'third_party_evidence_requests' => $selectedLabels,
                    'third_party_evidence_message' => $reviewNote ?: $case->third_party_evidence_message,
                ]);

                $statusNote = 'Evidence Stage document(s) rejected for reupload: ' . implode(', ', $selectedLabels);
                if ($restoreEvidenceStageStatus) {
                    $this->transition($case, TrademarkOppositionWorkflow::ADMIN_EVIDENCE_BY_OPPONENT, $statusNote, 'admin');
                } else {
                    $this->recordStatus(
                        $case,
                        $case->current_admin_status,
                        $case->current_admin_status,
                        $statusNote,
                        'admin'
                    );
                }

                $message = "The following Evidence Stage document(s) need to be reuploaded:\n\n- "
                    . implode("\n- ", $selectedLabels)
                    . "\n\nIssues to fix:\n" . $reviewNote;

                $this->notifyClient(
                    $case,
                    'Evidence Stage Documents Need Reupload',
                    $message,
                    [],
                    'Reupload Evidence',
                    route('trademark-opposition.oppose.show', $case) . '#action-center'
                );

                return back()->with('success', 'Rejected Evidence Stage document(s) were sent back to the client for reupload.');
            }

            $this->transition($case, TrademarkOppositionWorkflow::ADMIN_DOCUMENTS_PENDING, 'Opposition evidence rejected for reupload: ' . implode(', ', $selectedLabels), 'admin');

            $message = "The following evidence document(s) need to be reuploaded:\n\n- "
                . implode("\n- ", $selectedLabels)
                . "\n\nIssues to fix:\n" . $reviewNote;

            $this->notifyClient($case, 'Evidence Needs Reupload', $message, [], 'Reupload Evidence', route('trademark-opposition.oppose.show', $case));

            return back()->with('success', 'Rejected evidence was sent back to the client for reupload.');
        }

        if ($isEvidenceStageEvidence) {
            $case->update(['third_party_evidence_pending' => false]);
            $statusNote = 'Evidence Stage document(s) reviewed and accepted: ' . implode(', ', $selectedLabels);
            if ($restoreEvidenceStageStatus) {
                $this->transition($case, TrademarkOppositionWorkflow::ADMIN_EVIDENCE_BY_OPPONENT, $statusNote, 'admin');
            } else {
                $this->recordStatus(
                    $case,
                    $case->current_admin_status,
                    $case->current_admin_status,
                    $statusNote,
                    'admin'
                );
            }

            return back()->with('success', 'Evidence Stage document(s) marked as reviewed.');
        }

        $freshCase = $case->fresh('evidence');
        $latestClientEvidence = $freshCase->evidence
            ->where('file_path', '!=', 'metadata')
            ->where('uploaded_by', 'client')
            ->sortByDesc('id')
            ->unique('evidence_type');

        $allLatestEvidenceReviewed = $latestClientEvidence->isNotEmpty()
            && $latestClientEvidence->every(fn (OppositionEvidence $evidence) => $evidence->review_status === 'reviewed');

        if ($allLatestEvidenceReviewed) {
            $this->transition($case, TrademarkOppositionWorkflow::ADMIN_LEGAL_ANALYSIS, 'All opposition evidence reviewed. Legal review is ready.', 'admin');
        } else {
            $this->recordStatus($case, $case->current_admin_status, $case->current_admin_status, 'Opposition evidence reviewed and accepted: ' . implode(', ', $selectedLabels), 'admin');
        }

        return back()->with('success', 'Evidence marked as reviewed.');
    }

    public function adminOpposeRecommendation(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE, 404);

        $data = $request->validate([
            'recommendation_level' => ['required', 'in:Strong Opposition Case,Moderate Case,Weak Case'],
            'recommendation_note' => ['nullable', 'string', 'max:3000'],
            'recommendation_note_visible' => ['nullable', 'boolean'],
        ]);

        $case->update([
            'recommendation_level' => $data['recommendation_level'],
            'recommendation_note' => $data['recommendation_note'] ?? null,
            'recommendation_note_visible' => (bool) ($data['recommendation_note_visible'] ?? false),
        ]);

        return back()->with('success', 'Recommendation saved.');
    }

    public function adminOpposeRequestEvidence(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE, 404);

        $data = $request->validate([
            'note' => ['required', 'string', 'max:3000'],
            'optional_documents' => ['nullable', 'array', 'max:10'],
            'optional_documents.*' => ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:15360'],
            'optional_document_names' => ['nullable', 'array', 'max:10'],
            'optional_document_names.*' => ['nullable', 'string', 'max:255'],
        ], [
            'optional_documents.*.uploaded' => 'An additional document could not be uploaded. Ensure every file is below 15 MB and your PHP upload limit supports it.',
            'optional_documents.*.max' => 'Each additional document must be 15 MB or smaller.',
            'optional_documents.max' => 'You can attach a maximum of 10 additional documents at a time.',
        ]);

        $storedDocuments = collect();
        foreach ($request->file('optional_documents', []) as $index => $file) {
            $document = $this->storeEvidence($case, 'oppose_admin_additional_document', $file, 'admin');
            $documentName = trim((string) ($request->input('optional_document_names', [])[$index] ?? ''));

            if ($documentName !== '') {
                $document->update(['review_note' => $documentName]);
            }

            $storedDocuments->push($document->fresh());
        }

        $requestNote = trim($data['note']);
        $message = "Please upload the requested documents to avoid procedural delays.\n\nRequest note: " . $requestNote;

        $case->forceFill([
            'evidence_request_note' => $requestNote,
        ])->save();

        $mailAttachments = $storedDocuments
            ->map(fn (OppositionEvidence $document) => [
                'path' => Storage::disk('public')->path($document->file_path),
                'name' => $document->review_note ?: $document->file_name,
            ])
            ->all();

        $this->transition($case, TrademarkOppositionWorkflow::ADMIN_EVIDENCE_COLLECTION, $requestNote, 'admin');
        $this->notifyClient(
            $case,
            'Evidence Required',
            $message,
            $mailAttachments,
            'Upload Evidence',
            route('trademark-opposition.oppose.show', $case)
        );

        return back()->with('success', 'Evidence request sent and client notified.');
    }

    public function adminOpposeAdditionalDocuments(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE, 404);

        $data = $request->validate([
            'document_title' => ['nullable', 'string', 'max:255'],
            'document_note' => ['nullable', 'string', 'max:3000'],
            'additional_documents' => ['required', 'array', 'max:10'],
            'additional_documents.*' => ['file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:15360'],
        ], [
            'additional_documents.required' => 'Please attach at least one document to send.',
            'additional_documents.*.uploaded' => 'An additional document could not be uploaded. Ensure every file is below 15 MB and your PHP upload limit supports it.',
            'additional_documents.*.max' => 'Each additional document must be 15 MB or smaller.',
            'additional_documents.max' => 'You can send a maximum of 10 additional documents at a time.',
        ]);

        $storedDocuments = collect();
        foreach ($request->file('additional_documents', []) as $file) {
            $document = $this->storeEvidence($case, 'oppose_admin_additional_document', $file, 'admin');
            if (filled($data['document_title'] ?? null)) {
                $document->update(['review_note' => $data['document_title']]);
            }
            $storedDocuments->push($document->fresh());
        }

        $message = filled($data['document_note'] ?? null)
            ? $data['document_note']
            : 'Admin has shared additional documents for your trademark opposition matter. Please log in to view them.';

        $mailAttachments = $storedDocuments
            ->map(fn (OppositionEvidence $document) => [
                'path' => Storage::disk('public')->path($document->file_path),
                'name' => $document->review_note ?: $document->file_name,
            ])
            ->all();

        $emailSent = $this->notifyClient(
            $case,
            'Additional Documents Shared',
            $message,
            $mailAttachments,
            'View Additional Documents',
            route('trademark-opposition.oppose.show', $case)
        );

        $this->recordStatus(
            $case,
            $case->current_admin_status,
            $case->current_admin_status,
            'Admin sent additional document' . ($storedDocuments->count() === 1 ? '' : 's') . ' to client.',
            'admin'
        );

        return back()->with(
            $emailSent ? 'success' : 'warning',
            $emailSent
                ? 'Additional document' . ($storedDocuments->count() === 1 ? ' was' : 's were') . ' sent to the client.'
                : 'Additional documents were saved, but the email could not be sent. Check mail configuration or logs.'
        );
    }

    public function adminOpposePricing(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        $data = $request->validate([
            'package_name' => ['required', 'string', 'max:160'],
            'package_price' => ['required', 'numeric', 'min:1'],
            'total_amount' => ['required', 'numeric', 'min:1'],
            'included_services' => ['nullable', 'string', 'max:3000'],
            'add_ons' => ['nullable', 'string', 'max:3000'],
        ]);

        $case->update([
            'package_name' => $data['package_name'],
            'package_price' => $data['package_price'],
            'total_amount' => $data['total_amount'],
            'included_services' => $this->linesToArray($data['included_services'] ?? ''),
            'add_ons' => $this->linesToArray($data['add_ons'] ?? ''),
        ]);

        return back()->with('success', 'Pricing package assigned.');
    }

    public function adminOpposeDraftStatus(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:Notice Drafting in Progress,Draft Under Legal Review,Client Approval Pending,Ready for Filing,Notice of Opposition Filed'],
        ]);

        $this->transition($case, $data['status'], 'Admin updated Notice of Opposition drafting status.', 'admin');

        return back()->with('success', 'Draft status updated.');
    }

    public function adminOpposeUploadDraft(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        $data = $request->validate([
            'draft' => ['required', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:10240'],
            'admin_internal_note' => ['nullable', 'string', 'max:3000'],
            'client_note' => ['nullable', 'string', 'max:3000'],
            'optional_documents' => ['nullable', 'array', 'max:10'],
            'optional_documents.*' => ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:15360'],
            'optional_document_names' => ['nullable', 'array', 'max:10'],
            'optional_document_names.*' => ['nullable', 'string', 'max:255'],
        ], [
            'optional_documents.*.uploaded' => 'An additional document could not be uploaded. Ensure every file is below 15 MB.',
            'optional_documents.*.max' => 'Each additional document must be 15 MB or smaller.',
            'optional_documents.max' => 'You can attach a maximum of 10 additional documents at a time.',
        ]);

        $path = $data['draft']->store('opposition/oppose-drafts/' . $case->id, 'public');
        $version = ((int) $case->draftDocuments()->max('version')) + 1;

        DraftDocument::create([
            'case_id' => $case->id,
            'draft_type' => 'notice_of_opposition',
            'file_path' => $path,
            'file_name' => $data['draft']->getClientOriginalName(),
            'version' => $version,
            'uploaded_by' => 'admin',
            'client_status' => 'pending',
        ]);

        $additionalDocuments = collect();
        foreach ($request->file('optional_documents', []) as $index => $file) {
            $document = $this->storeEvidence($case, 'notice_draft_additional_document', $file, 'admin');
            $documentName = trim((string) ($request->input('optional_document_names', [])[$index] ?? ''));

            if ($documentName !== '') {
                $document->update(['review_note' => $documentName]);
            }

            $additionalDocuments->push($document->fresh());
        }

        $caseUpdates = [
            'draft_path' => $path,
            'draft_name' => $data['draft']->getClientOriginalName(),
            'admin_internal_notes' => $data['admin_internal_note'] ?? $case->admin_internal_notes,
            'client_approval_status' => 'pending',
            'client_approval_note' => null,
            'client_change_request' => null,
        ];

        if (Schema::hasColumn('trademark_opposition_cases', 'draft_client_note')) {
            $caseUpdates['draft_client_note'] = trim((string) ($data['client_note'] ?? '')) ?: null;
        }

        $case->update($caseUpdates);

        $clientNote = trim((string) ($data['client_note'] ?? ''));
        $adminNote = trim((string) ($data['admin_internal_note'] ?? ''));
        $this->transition(
            $case,
            TrademarkOppositionWorkflow::ADMIN_CLIENT_APPROVAL_PENDING,
            'Notice of Opposition draft uploaded for client approval.'
                . ($adminNote !== '' ? ' Internal note: ' . $adminNote : '')
                . ($clientNote !== '' ? ' Client note: ' . $clientNote : ''),
            'admin'
        );
        $case->load('evidence');
        $allDraftAdditionalDocuments = $case->evidence
            ->where('evidence_type', 'notice_draft_additional_document')
            ->where('uploaded_by', 'admin');
        $mailAttachments = collect([
            ['path' => Storage::disk('public')->path($path), 'name' => $case->fresh()->draft_display_name],
        ])->merge(
            $allDraftAdditionalDocuments->map(fn (OppositionEvidence $document) => [
                'path' => Storage::disk('public')->path($document->file_path),
                'name' => $document->review_note ?: $document->file_name,
            ])
        )->all();

        $this->notifyClient(
            $case,
            'Draft Ready',
            'Your Notice of Opposition draft'
                . ($allDraftAdditionalDocuments->isNotEmpty() ? ' and supporting documents are' : ' is')
                . ' ready for review and approval.'
                . ($clientNote !== '' ? "\n\nAdmin note:\n" . $clientNote : ''),
            $mailAttachments,
            'Review Notice of Opposition Draft',
            route('trademark-opposition.oppose.show', $case)
        );

        return back()->with('success', 'Draft'
            . ($allDraftAdditionalDocuments->isNotEmpty() ? ' and supporting documents were' : ' was')
            . ' uploaded for client review.');
    }

    public function adminOpposeFile(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        $data = $request->validate([
            'filing_acknowledgment' => ['required', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:10240'],
            'optional_documents' => ['nullable', 'array', 'max:10'],
            'optional_documents.*' => ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:15360'],
            'optional_document_names' => ['nullable', 'array', 'max:10'],
            'optional_document_names.*' => ['nullable', 'string', 'max:255'],
        ], [
            'optional_documents.*.uploaded' => 'An additional document could not be uploaded. Ensure every file is below 15 MB.',
            'optional_documents.*.max' => 'Each additional document must be 15 MB or smaller.',
            'optional_documents.max' => 'You can attach a maximum of 10 additional documents at a time.',
        ]);

        $path = $data['filing_acknowledgment']->store('opposition/oppose-filing-acknowledgments/' . $case->id, 'public');

        $counterStatementDeadline = $case->counter_statement_deadline ?: now()->addMonths(2);

        $case->update([
            'filing_acknowledgment_path' => $path,
            'filing_acknowledgment_name' => $data['filing_acknowledgment']->getClientOriginalName(),
            'counter_statement_deadline' => $counterStatementDeadline,
            'deadline_status' => TrademarkOppositionWorkflow::deadlineStatus(Carbon::parse($counterStatementDeadline)),
            'third_party_status' => TrademarkOppositionWorkflow::THIRD_PARTY_AWAITING_RESPONSE,
        ]);

        foreach ($request->file('optional_documents', []) as $index => $file) {
            $document = $this->storeEvidence($case, 'notice_filing_additional_document', $file, 'admin');
            $documentName = trim((string) ($request->input('optional_document_names', [])[$index] ?? ''));

            if ($documentName !== '') {
                $document->update(['review_note' => $documentName]);
            }
        }

        $this->transition($case, TrademarkOppositionWorkflow::ADMIN_NOTICE_FILED, 'Notice of Opposition filed and acknowledgment uploaded.', 'admin');
        $this->transition($case, TrademarkOppositionWorkflow::ADMIN_COUNTER_STATEMENT_AWAITED, 'Waiting for the applicant to file the Counter Statement.', 'admin');
        $this->syncOpposedTrademarkFilingApplication($case);
        $this->notifyClient($case, 'Filed', 'Your Notice of Opposition has been filed successfully.');

        return back()->with('success', 'Filing acknowledgment uploaded.');
    }

    private function syncOpposedTrademarkFilingApplication(TrademarkOppositionCase $case): void
    {
        if (blank($case->application_number)) {
            return;
        }

        $application = Application::query()
            ->where('application_number', $case->application_number)
            ->first();

        if (!$application || $application->current_status !== TrademarkWorkflow::POST_FILING) {
            return;
        }

        $stage = 'accepted_advertised';
        $meta = $application->workflow_meta ?? [];
        $stageMeta = data_get($meta, "post_filing_journey.stages.$stage", []);
        $stageMeta['status'] = PostFilingJourney::OPPOSED;
        $stageMeta['updated_at'] = now()->toDateTimeString();
        $stageMeta['opposed_at'] = now()->toDateTimeString();
        $stageMeta['opposition_received_on'] = $case->notice_receipt_date
            ? $case->notice_receipt_date->format('Y-m-d')
            : now()->format('Y-m-d');
        $stageMeta['counter_statement_due_on'] = $case->counter_statement_deadline
            ? $case->counter_statement_deadline->format('Y-m-d')
            : now()->addMonths(2)->format('Y-m-d');
        $stageMeta['documents_requested'] = false;
        unset($stageMeta['processing_at'], $stageMeta['completed_at'], $stageMeta['documents_submitted_at']);

        data_set($meta, "post_filing_journey.stages.$stage", $stageMeta);
        $application->workflow_meta = $meta;
        $application->registry_status = TrademarkWorkflow::REGISTRY_OPPOSITION;

        $linkedOppositionCase = TrademarkOppositionCase::query()
            ->where('flow_type', TrademarkOppositionWorkflow::FLOW_OPPOSE)
            ->where('application_number', $application->application_number)
            ->latest('id')
            ->first();

        if ($linkedOppositionCase) {
            $application->opposition_application_id = $linkedOppositionCase->id;
            $application->opposition_status = PostFilingJourney::OPPOSED;
        }

        $application->save();
    }

    public function adminOpposeThirdPartyAction(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE, 404);

        $statuses = [
            TrademarkOppositionWorkflow::THIRD_PARTY_AWAITING_RESPONSE,
            TrademarkOppositionWorkflow::THIRD_PARTY_COUNTER_STATEMENT_RECEIVED,
            TrademarkOppositionWorkflow::THIRD_PARTY_NO_RESPONSE,
            TrademarkOppositionWorkflow::THIRD_PARTY_DEADLINE_EXPIRED,
        ];

        $data = $request->validate([
            'third_party_status' => ['required', Rule::in($statuses)],
            'admin_internal_notes' => ['nullable', 'string', 'max:5000'],
            'action' => ['nullable', Rule::in(['save', 'move_to_evidence', 'close_matter'])],
            'optional_documents' => ['nullable', 'array', 'max:10'],
            'optional_documents.*' => ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:15360'],
            'optional_document_names' => ['nullable', 'array', 'max:10'],
            'optional_document_names.*' => ['nullable', 'string', 'max:255'],
            'evidence_items' => ['nullable', 'array', 'max:10'],
            'evidence_items.*' => ['nullable', 'string', 'max:255', 'distinct'],
            'message_to_client' => ['nullable', 'string', 'max:3000'],
        ], [
            'optional_documents.*.uploaded' => 'An additional document could not be uploaded. Ensure every file is below 15 MB.',
            'optional_documents.*.max' => 'Each additional document must be 15 MB or smaller.',
            'optional_documents.max' => 'You can attach a maximum of 10 additional documents at a time.',
        ]);

        $requestedEvidenceItems = collect($data['evidence_items'] ?? [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
        $messageToClient = trim((string) ($data['message_to_client'] ?? ''));
        $shouldSendEvidenceRequest = $requestedEvidenceItems !== [] || $messageToClient !== '';

        if ($shouldSendEvidenceRequest && ($requestedEvidenceItems === [] || $messageToClient === '')) {
            return back()
                ->withErrors([
                    'evidence_items' => 'Add at least one requested evidence item and a message to client.',
                ])
                ->withInput();
        }

        foreach ($request->file('optional_documents', []) as $index => $file) {
            $document = $this->storeEvidence($case, 'notice_tracking_additional_document', $file, 'admin');
            $documentName = trim((string) ($request->input('optional_document_names', [])[$index] ?? ''));

            if ($documentName !== '') {
                $document->update(['review_note' => $documentName]);
            }
        }

        $status = $data['third_party_status'];
        $case->forceFill([
            'third_party_status' => $status,
            'admin_internal_notes' => $data['admin_internal_notes'] ?? $case->admin_internal_notes,
        ])->save();

        if ($shouldSendEvidenceRequest) {
            $case->update([
                'third_party_evidence_requests' => $requestedEvidenceItems,
                'third_party_evidence_message' => $messageToClient,
                'third_party_evidence_pending' => true,
            ]);

            $message = $messageToClient
                . "\n\nRequested evidence:\n- " . implode("\n- ", $requestedEvidenceItems);

            $this->recordStatus(
                $case,
                $case->current_admin_status,
                $case->current_admin_status,
                'Additional evidence requested from client: ' . implode(', ', $requestedEvidenceItems),
                'admin'
            );
            $this->notifyClient(
                $case,
                'Additional Evidence Required',
                $message,
                [],
                'Upload Additional Evidence',
                route('trademark-opposition.oppose.show', $case) . '#action-center'
            );
        }

        if ($status === TrademarkOppositionWorkflow::THIRD_PARTY_COUNTER_STATEMENT_RECEIVED) {
            $this->transition(
                $case,
                TrademarkOppositionWorkflow::ADMIN_EVIDENCE_BY_OPPONENT,
                'Counter Statement received. Matter moved to Evidence Stage.',
                'admin'
            );
            $this->notifyClient(
                $case,
                'Counter Statement Received',
                'The applicant has filed the Counter Statement. Your matter has moved to the Evidence Stage.'
            );

            return back()->with('success', 'Counter Statement saved and the matter moved to Evidence Stage.');
        }

        if (in_array($status, [
            TrademarkOppositionWorkflow::THIRD_PARTY_NO_RESPONSE,
            TrademarkOppositionWorkflow::THIRD_PARTY_DEADLINE_EXPIRED,
        ], true)) {
            $case->update(['final_outcome' => TrademarkOppositionWorkflow::ADMIN_OPPOSITION_ALLOWED]);
            $this->transition(
                $case,
                TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED,
                $status === TrademarkOppositionWorkflow::THIRD_PARTY_DEADLINE_EXPIRED
                    ? 'Counter Statement deadline expired. Opposition allowed and matter closed.'
                    : 'No Counter Statement response received. Opposition allowed and matter closed.',
                'admin'
            );
            $this->notifyClient(
                $case,
                'Matter Closed',
                'The matter has been closed with the outcome: Opposition Allowed.'
            );

            return back()->with('success', 'Matter closed with the outcome Opposition Allowed.');
        }

        $this->transition(
            $case,
            TrademarkOppositionWorkflow::ADMIN_COUNTER_STATEMENT_AWAITED,
            "Awaiting the applicant's Counter Statement response.",
            'admin'
        );

        return back()->with('success', 'Counter Statement status saved.');
    }

    public function adminOpposeInternalNote(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE, 404);

        $data = $request->validate([
            'admin_internal_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $case->update([
            'admin_internal_notes' => trim((string) ($data['admin_internal_notes'] ?? '')),
        ]);

        $this->recordStatus(
            $case,
            $case->current_admin_status,
            $case->current_admin_status,
            'Admin internal note updated.',
            'admin'
        );

        return back()->with('success', 'Internal note saved.');
    }

    public function adminOpposeThirdPartyEvidenceRequest(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE, 404);

        $data = $request->validate([
            'evidence_items' => ['required', 'array', 'min:1', 'max:10'],
            'evidence_items.*' => ['required', 'string', 'max:255', 'distinct'],
            'message_to_client' => ['required', 'string', 'max:3000'],
        ]);

        $items = collect($data['evidence_items'])
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();

        $case->update([
            'third_party_evidence_requests' => $items,
            'third_party_evidence_message' => trim($data['message_to_client']),
            'third_party_evidence_pending' => true,
        ]);

        $message = trim($data['message_to_client'])
            . "\n\nRequested evidence:\n- " . implode("\n- ", $items);

        $this->recordStatus(
            $case,
            $case->current_admin_status,
            $case->current_admin_status,
            'Additional evidence requested from client: ' . implode(', ', $items),
            'admin'
        );
        $this->notifyClient(
            $case,
            'Additional Evidence Required',
            $message,
            [],
            'Upload Additional Evidence',
            route('trademark-opposition.oppose.show', $case) . '#action-center'
        );

        return back()->with('success', 'Additional evidence request sent to the client.');
    }

    public function adminOpposeEvidenceFiled(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE, 404);
        abort_unless(in_array($case->current_admin_status, [
            TrademarkOppositionWorkflow::ADMIN_EVIDENCE_BY_OPPONENT,
            TrademarkOppositionWorkflow::ADMIN_EVIDENCE_BY_APPLICANT,
            TrademarkOppositionWorkflow::ADMIN_EVIDENCE_IN_REPLY,
            TrademarkOppositionWorkflow::ADMIN_EVIDENCE_FILED,
        ], true), 403);

        $latestEvidenceStageDocuments = $case->evidence()
            ->where('file_path', '!=', 'metadata')
            ->where('uploaded_by', 'client')
            ->where('evidence_type', 'like', 'third_party_requested_evidence_%')
            ->latest('id')
            ->get()
            ->unique('evidence_type');

        abort_unless(
            $latestEvidenceStageDocuments->isNotEmpty()
                && $latestEvidenceStageDocuments->every(fn (OppositionEvidence $evidence) => $evidence->review_status === 'reviewed'),
            422,
            'All latest Evidence Stage documents must be reviewed before moving to Hearing Stage.'
        );

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:3000'],
            'optional_documents' => ['nullable', 'array', 'max:10'],
            'optional_documents.*' => ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:15360'],
            'optional_document_names' => ['nullable', 'array', 'max:10'],
            'optional_document_names.*' => ['nullable', 'string', 'max:255'],
        ], [
            'optional_documents.*.uploaded' => 'An additional document could not be uploaded. Ensure every file is below 15 MB.',
            'optional_documents.*.max' => 'Each additional document must be 15 MB or smaller.',
            'optional_documents.max' => 'You can attach a maximum of 10 additional documents at a time.',
        ]);

        foreach ($request->file('optional_documents', []) as $index => $file) {
            $document = $this->storeEvidence($case, 'notice_tracking_additional_document', $file, 'admin');
            $documentName = trim((string) ($request->input('optional_document_names', [])[$index] ?? ''));

            if ($documentName !== '') {
                $document->update(['review_note' => $documentName]);
            }
        }

        $case->update(['third_party_evidence_pending' => false]);

        $note = trim((string) ($data['note'] ?? ''));
        $evidenceFiledNote = $note !== ''
            ? $note
            : 'Evidence filed before the Trademark Registry.';

        if ($case->current_admin_status !== TrademarkOppositionWorkflow::ADMIN_EVIDENCE_FILED) {
            $this->transition($case, TrademarkOppositionWorkflow::ADMIN_EVIDENCE_FILED, $evidenceFiledNote, 'admin');
        } else {
            $this->recordStatus($case, $case->current_admin_status, $case->current_admin_status, $evidenceFiledNote, 'admin');
        }

        $this->transition(
            $case,
            TrademarkOppositionWorkflow::ADMIN_HEARING_PREPARATION,
            'Evidence filed. Awaiting hearing schedule from the Trademark Registry.',
            'admin'
        );

        $this->notifyClient(
            $case,
            'Evidence Filed',
            'Your evidence has been filed before the Trademark Registry. We are now awaiting the hearing schedule.'
        );

        return back()->with('success', 'Evidence filed and matter moved to Hearing Stage.');
    }

    public function adminOpposeRegistryUpdate(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE, 404);

        $data = $request->validate([
            'update_type' => ['required', Rule::in([
                'Counter Statement Received',
                'Registry Communication',
                'Evidence Deadline Issued',
                'Hearing Notice',
                'Other',
            ])],
            'update_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        OppositionRegistryUpdate::create([
            'case_id' => $case->id,
            'update_type' => $data['update_type'],
            'update_date' => $data['update_date'],
            'notes' => $data['notes'] ?? null,
            'created_by' => 'admin',
        ]);

        return back()->with('success', 'Registry update added.');
    }

    public function adminOpposeTracking(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        $decisionStatuses = [
            TrademarkOppositionWorkflow::ADMIN_OPPOSITION_ALLOWED,
            TrademarkOppositionWorkflow::ADMIN_OPPOSITION_DISMISSED,
            TrademarkOppositionWorkflow::ADMIN_SETTLEMENT_CLOSED,
            TrademarkOppositionWorkflow::ADMIN_WITHDRAWN,
            TrademarkOppositionWorkflow::ADMIN_OTHER,
        ];

        $data = $request->validate([
            'status' => ['required', 'string'],
            'hearing_date' => ['nullable', 'date'],
            'hearing_notice' => ['nullable', 'file', 'mimes:pdf', 'max:15360'],
            'hearing_outcome' => ['nullable', Rule::in(['Further Hearing Required', 'Matter Concluded'])],
            'adjournment_reason' => ['nullable', 'string', 'max:3000'],
            'final_outcome' => ['nullable', 'string', 'max:160'],
            'decision_status' => ['required_if:status,' . TrademarkOppositionWorkflow::ADMIN_DECISION_AWAITED, Rule::in($decisionStatuses)],
            'withdrawn_by' => ['nullable', Rule::in(array_keys(TrademarkOppositionWorkflow::withdrawnByOptions()))],
            'decision_date' => ['nullable', 'date'],
            'decision_order' => ['nullable', 'file', 'mimes:pdf', 'max:15360'],
            'decision_note' => ['nullable', 'required_if:decision_status,' . TrademarkOppositionWorkflow::ADMIN_OTHER, 'string', 'max:3000'],
            'note' => ['nullable', 'string', 'max:3000'],
            'optional_documents' => ['nullable', 'array', 'max:10'],
            'optional_documents.*' => ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:15360'],
            'optional_document_names' => ['nullable', 'array', 'max:10'],
            'optional_document_names.*' => ['nullable', 'string', 'max:255'],
        ], [
            'decision_status.required_if' => 'Please select final outcome.',
            'decision_status.in' => 'Please select final outcome.',
            'decision_note.required_if' => 'Please add final notes when the final outcome is Other.',
            'hearing_notice.max' => 'The hearing notice must be 15 MB or smaller.',
            'hearing_notice.uploaded' => 'The hearing notice could not be uploaded. Ensure it is below 15 MB.',
            'decision_order.max' => 'The decision order must be 15 MB or smaller.',
            'decision_order.uploaded' => 'The decision order could not be uploaded. Ensure it is below 15 MB.',
            'optional_documents.*.uploaded' => 'An additional document could not be uploaded. Ensure every file is below 15 MB.',
            'optional_documents.*.max' => 'Each additional document must be 15 MB or smaller.',
            'optional_documents.max' => 'You can attach a maximum of 10 additional documents at a time.',
        ]);

        $hearingStatuses = [
            TrademarkOppositionWorkflow::ADMIN_HEARING_PREPARATION,
            TrademarkOppositionWorkflow::ADMIN_HEARING_SCHEDULED,
            TrademarkOppositionWorkflow::ADMIN_HEARING_COMPLETED,
            TrademarkOppositionWorkflow::ADMIN_HEARING_ADJOURNED,
        ];
        $isHearingUpdate = in_array($data['status'], $hearingStatuses, true);
        $isDecisionUpdate = $data['status'] === TrademarkOppositionWorkflow::ADMIN_DECISION_AWAITED
            && $request->has('decision_status');
        $selectedDecisionStatus = $data['decision_status'] ?? '';
        $hasFinalDecision = $isDecisionUpdate && $selectedDecisionStatus !== '';
        $optionalDocumentType = $isDecisionUpdate
            ? 'decision_additional_document'
            : 'notice_tracking_additional_document';

        if ($isHearingUpdate && $data['status'] === TrademarkOppositionWorkflow::ADMIN_HEARING_SCHEDULED && blank($data['hearing_date'] ?? null)) {
            return back()->withErrors(['hearing_date' => 'Please select the hearing date when the hearing is scheduled.'])->withInput();
        }

        if ($isHearingUpdate && $data['status'] === TrademarkOppositionWorkflow::ADMIN_HEARING_ADJOURNED && blank($data['hearing_date'] ?? null)) {
            return back()->withErrors(['hearing_date' => 'Please select the new hearing date when the hearing is adjourned.'])->withInput();
        }

        if ($isHearingUpdate && $data['status'] === TrademarkOppositionWorkflow::ADMIN_HEARING_COMPLETED && blank($data['hearing_outcome'] ?? null)) {
            return back()->withErrors(['hearing_outcome' => 'Please select the hearing outcome after the hearing is completed.'])->withInput();
        }

        if ($isHearingUpdate
            && $data['status'] === TrademarkOppositionWorkflow::ADMIN_HEARING_COMPLETED
            && ($data['hearing_outcome'] ?? null) === 'Further Hearing Required'
            && blank($data['hearing_date'] ?? null)) {
            return back()->withErrors(['hearing_date' => 'Please select the new hearing date when a further hearing is required.'])->withInput();
        }

        if ($hasFinalDecision && blank($data['decision_date'] ?? null)) {
            return back()->withErrors(['decision_date' => 'Please select the decision date before closing the matter.'])->withInput();
        }

        if ($hasFinalDecision && !$request->hasFile('decision_order')) {
            return back()->withErrors(['decision_order' => 'Please upload the Registry Decision / Order PDF before closing the matter.'])->withInput();
        }

        if ($hasFinalDecision
            && ($data['decision_status'] ?? null) === TrademarkOppositionWorkflow::ADMIN_WITHDRAWN
            && blank($data['withdrawn_by'] ?? null)) {
            return back()->withErrors(['withdrawn_by' => 'Please select who withdrew the matter.'])->withInput();
        }

        if ($hasFinalDecision
            && ($data['decision_status'] ?? null) === TrademarkOppositionWorkflow::ADMIN_OTHER
            && blank($data['decision_note'] ?? null)) {
            return back()->withErrors(['decision_note' => 'Please add final notes when the final outcome is Other.'])->withInput();
        }

        $baseCaseUpdates = [
            'hearing_date' => $data['hearing_date'] ?? $case->hearing_date,
            'final_outcome' => $data['hearing_outcome'] ?? $data['final_outcome'] ?? $case->final_outcome,
        ];
        if (Schema::hasColumn('trademark_opposition_cases', 'withdrawn_by')) {
            $baseCaseUpdates['withdrawn_by'] = null;
        }
        $case->update($baseCaseUpdates);
        $this->syncLinkedApplicationOppositionStatuses($case);

        if ($request->hasFile('hearing_notice')) {
            $document = $this->storeEvidence($case, 'hearing_notice_document', $request->file('hearing_notice'), 'admin');
            $document->update(['review_note' => 'Hearing Notice']);
        }

        foreach ($request->file('optional_documents', []) as $index => $file) {
            $document = $this->storeEvidence($case, $optionalDocumentType, $file, 'admin');
            $documentName = trim((string) ($request->input('optional_document_names', [])[$index] ?? ''));

            if ($documentName !== '') {
                $document->update(['review_note' => $documentName]);
            }
        }

        $note = $data['note'] ?? ($isHearingUpdate ? 'Admin updated hearing status.' : 'Admin updated Flow B tracking.');
        if ($isHearingUpdate && filled($data['adjournment_reason'] ?? null)) {
            $note = trim($note . ' Adjournment reason: ' . $data['adjournment_reason']);
        }
        if ($isHearingUpdate && filled($data['hearing_outcome'] ?? null)) {
            $note = trim($note . ' Hearing outcome: ' . $data['hearing_outcome']);
        }

        if ($isDecisionUpdate) {
            if ($hasFinalDecision) {
                $decisionResolution = TrademarkOppositionWorkflow::decisionPreview(
                    $selectedDecisionStatus,
                    $data['withdrawn_by'] ?? null
                );

                if (!$decisionResolution) {
                    return back()->withErrors(['decision_status' => 'Please select final outcome.'])->withInput();
                }

                $document = $this->storeEvidence($case, 'decision_order_document', $request->file('decision_order'), 'admin');
                $document->update(['review_note' => 'Registry Decision / Order']);

                $decisionNote = trim((string) ($data['decision_note'] ?? ''));
                $transitionNote = 'Decision issued: ' . $selectedDecisionStatus
                    . '. Decision date: ' . Carbon::parse($data['decision_date'])->format('d M Y') . '.';
                if (($data['decision_status'] ?? null) === TrademarkOppositionWorkflow::ADMIN_WITHDRAWN && filled($data['withdrawn_by'] ?? null)) {
                    $transitionNote .= ' Withdrawn by: ' . ucfirst((string) $data['withdrawn_by']) . '.';
                }
                if ($decisionNote !== '') {
                    $transitionNote .= ' Client note: ' . $decisionNote;
                }

                $finalCaseUpdates = [
                    'final_outcome' => $decisionResolution['stored_final_outcome'],
                ];
                if (Schema::hasColumn('trademark_opposition_cases', 'oppose_case_status')) {
                    $finalCaseUpdates['oppose_case_status'] = $decisionResolution['oppose_case_status'];
                }
                if (Schema::hasColumn('trademark_opposition_cases', 'withdrawn_by')) {
                    $finalCaseUpdates['withdrawn_by'] = $data['withdrawn_by'] ?? null;
                }
                $case->update($finalCaseUpdates);
                $this->applyLinkedOppositionDecisionOutcome($case, $decisionResolution);
                $this->transition($case, TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED, $transitionNote, 'admin');

                $clientMessage = match ($decisionResolution['application_final_opposition_result']) {
                    TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_OPPOSITION_SUCCESSFUL => 'The Trademark Registry has allowed your opposition. Please view the final order from the stage Documents section.',
                    TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_DEFENCE_SUCCESSFUL => 'The Trademark Registry has dismissed the opposition. The trademark can continue. Please view the final order from the stage Documents section.',
                    TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_SETTLEMENT_CLOSED => 'This matter has been closed based on a settlement between the parties.',
                    TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_OPPOSITION_WITHDRAWN => 'The opposition was withdrawn. The trademark may continue. Please view the final order from the stage Documents section.',
                    TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_APPLICATION_WITHDRAWN => 'The trademark application has been withdrawn. Please view the final order from the stage Documents section.',
                    TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_OTHER => 'The matter has been closed. Please view the final documents from the stage Documents section.',
                    TrademarkOppositionWorkflow::ADMIN_OPPOSITION_ALLOWED => 'The Trademark Registry has allowed your opposition. Please view the final order from the stage Documents section.',
                    TrademarkOppositionWorkflow::ADMIN_OPPOSITION_DISMISSED => 'The Trademark Registry has dismissed your opposition. Please download the final order from the Documents section.',
                    TrademarkOppositionWorkflow::ADMIN_SETTLEMENT_CLOSED => 'This matter has been closed based on a settlement between the parties.',
                    default => 'The matter has been closed.',
                };

                $this->notifyClient($case, 'Matter Closed', $clientMessage);

                return back()->with('success', 'Matter closed with the outcome ' . TrademarkOppositionWorkflow::finalOutcomeLabel($decisionResolution['stored_final_outcome']) . '.');
            }

            if (filled($data['decision_note'] ?? null)) {
                $this->recordStatus($case, $case->current_admin_status, $case->current_admin_status, $data['decision_note'], 'admin');
            }

            return back()->with('success', 'Decision status saved.');
        }

        if ($isHearingUpdate && $data['status'] === TrademarkOppositionWorkflow::ADMIN_HEARING_COMPLETED) {
            $this->transition($case, TrademarkOppositionWorkflow::ADMIN_HEARING_COMPLETED, $note, 'admin');
            if (($data['hearing_outcome'] ?? null) === 'Further Hearing Required') {
                $this->transition($case, TrademarkOppositionWorkflow::ADMIN_HEARING_SCHEDULED, 'Further hearing required. New hearing date scheduled.', 'admin');
            } else {
                $this->transition($case, TrademarkOppositionWorkflow::ADMIN_DECISION_AWAITED, 'Hearing completed. Awaiting final decision from the Trademark Registry.', 'admin');
            }
        } else {
            $this->transition($case, $data['status'], $note, 'admin');
        }

        if ($data['status'] === TrademarkOppositionWorkflow::CLIENT_AWAITING_OTHER_PARTY) {
            $this->notifyClient($case, 'Other Party Action Awaited', 'We are now awaiting action from the other party as per Trademark Registry process.');
        }

        if ($data['status'] === TrademarkOppositionWorkflow::ADMIN_HEARING_SCHEDULED) {
            $this->notifyClient($case, 'Hearing Scheduled', 'A hearing has been scheduled by the Trademark Registry.');
        }

        if ($data['status'] === TrademarkOppositionWorkflow::ADMIN_HEARING_ADJOURNED) {
            $this->notifyClient($case, 'Hearing Rescheduled', 'Your hearing has been rescheduled by the Trademark Registry.');
        }

        if ($data['status'] === TrademarkOppositionWorkflow::ADMIN_HEARING_COMPLETED) {
            if (($data['hearing_outcome'] ?? null) === 'Further Hearing Required') {
                $this->notifyClient($case, 'Further Hearing Scheduled', 'A further hearing has been directed and a new hearing date has been scheduled.');
            } else {
                $this->notifyClient($case, 'Hearing Completed', 'The hearing has been completed. We are awaiting the final decision from the Trademark Registry.');
            }
        }

        if (in_array($data['status'], [TrademarkOppositionWorkflow::ADMIN_OPPOSITION_ALLOWED, TrademarkOppositionWorkflow::ADMIN_OPPOSITION_DISMISSED, TrademarkOppositionWorkflow::ADMIN_SETTLEMENT_CLOSED, TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED], true)) {
            $this->notifyClient($case, 'Matter Closed', 'The Registry has issued its decision. Please log in to view details.');
        }

        return back()->with('success', $isHearingUpdate ? 'Hearing status updated.' : 'Tracking status updated.');
    }

    public function adminShow(TrademarkOppositionCase $case): View
    {
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_DEFEND, 404);
        $this->normalizeFiledDefenceStage($case);

        $linkedOppositionCase = TrademarkOppositionCase::query()
            ->where('flow_type', TrademarkOppositionWorkflow::FLOW_OPPOSE)
            ->where(function ($query) use ($case) {
                $query->where('application_number', $case->application_number)
                    ->orWhere('opposed_application_number', $case->application_number);
            })
            ->latest('id')
            ->first();

        if (!$linkedOppositionCase && filled($case->application_number)) {
            $linkedApplication = Application::query()
                ->where('application_number', $case->application_number)
                ->first();

            if ($linkedApplication?->opposition_application_id) {
                $linkedOppositionCase = TrademarkOppositionCase::query()
                    ->where('flow_type', TrademarkOppositionWorkflow::FLOW_OPPOSE)
                    ->find($linkedApplication->opposition_application_id);
            }
        }

        return view('admin.trademark-opposition.show', [
            'case' => $case->load(['user', 'documents', 'evidence', 'grounds', 'statusHistories']),
            'linkedOppositionCase' => $linkedOppositionCase,
            'grounds' => TrademarkOppositionWorkflow::grounds(),
            'riskNotes' => TrademarkOppositionWorkflow::riskNotes(),
            'trackingStatuses' => TrademarkOppositionWorkflow::trackingStatuses(),
        ]);
    }

    public function adminReviewDocuments(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_DEFEND, 404);

        $data = $request->validate([
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['integer'],
            'action' => ['required', 'in:reviewed,rejected'],
            'note' => ['required_if:action,rejected', 'nullable', 'string', 'max:1500'],
        ]);

        $documents = $case->documents()
            ->whereIn('id', $data['document_ids'])
            ->get();

        if ($documents->isEmpty()) {
            return back()->withErrors(['documents' => 'Please select at least one uploaded document.']);
        }

        $documentLabels = TrademarkOppositionWorkflow::requiredDocuments() + TrademarkOppositionWorkflow::optionalDocuments();
        $reviewNote = trim((string) ($data['note'] ?? ''));
        $selectedLabels = [];

        foreach ($documents as $document) {
            $document->update([
                'review_status' => $data['action'],
                'review_note' => $reviewNote ?: null,
                'reviewed_at' => now(),
                'reviewed_by' => 'admin',
            ]);

            $selectedLabels[] = $documentLabels[$document->document_type] ?? $document->document_type;
        }

        if ($data['action'] === 'rejected') {
            $this->transition($case, TrademarkOppositionWorkflow::ADMIN_DOCUMENTS_PENDING, 'Documents rejected for reupload: ' . implode(', ', $selectedLabels), 'admin');

            $message = "The following opposition document(s) need to be reuploaded:\n\n- "
                . implode("\n- ", $selectedLabels)
                . "\n\nIssues to fix:\n" . $reviewNote;

            $this->notifyClient($case, 'Documents Need Reupload', $message);
            Mail::to($case->email)->send(new EventNotification(
                $case->user,
                'Documents Need Reupload',
                $message,
                [],
                route('trademark-opposition.show', $case),
                'Reupload Documents'
            ));

            return back()->with('success', 'Rejected documents were sent back to the client for reupload.');
        }

        $message = "The following opposition document(s) have been reviewed and accepted:\n\n- " . implode("\n- ", $selectedLabels);
        if ($reviewNote !== '') {
            $message .= "\n\nReview note:\n" . $reviewNote;
        }

        $freshCase = $case->fresh('documents');
        $latestDocuments = $freshCase->documents->sortByDesc('id')->unique('document_type');
        $allLatestDocumentsReviewed = $latestDocuments->isNotEmpty()
            && $latestDocuments->every(fn (OppositionDocument $document) => $document->review_status === 'reviewed');

        if ($allLatestDocumentsReviewed) {
            $this->transition($case, TrademarkOppositionWorkflow::ADMIN_EVIDENCE_COLLECTION, 'All opposition documents reviewed. Evidence collection is ready.', 'admin');
        } else {
            $this->recordStatus($case, $case->current_admin_status, $case->current_admin_status, 'Opposition documents reviewed and accepted: ' . implode(', ', $selectedLabels), 'admin');
        }

        $this->notifyClient($case, 'Documents Reviewed', $message);
        Mail::to($case->email)->send(new EventNotification(
            $case->user,
            'Documents Reviewed',
            $message,
            [],
            route('trademark-opposition.show', $case),
            'View Case Status'
        ));

        return back()->with('success', 'Documents marked as reviewed.');
    }

    public function adminAnalyze(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        $data = $request->validate([
            'grounds' => ['required', 'array', 'min:1'],
            'grounds.*' => ['string', 'max:120'],
            'admin_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $case->grounds()->delete();

        foreach ($data['grounds'] as $ground) {
            OppositionGround::create([
                'case_id' => $case->id,
                'ground_name' => $ground,
                'admin_note' => $data['admin_note'] ?? null,
            ]);
        }

        $case->update(['admin_internal_notes' => $data['admin_note'] ?? $case->admin_internal_notes]);
        $this->transition($case, TrademarkOppositionWorkflow::ADMIN_LEGAL_ANALYSIS, 'Admin completed opposition grounds analysis.', 'admin');

        return back()->with('success', 'Legal analysis saved.');
    }

    public function adminRequestEvidence(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        $data = $request->validate([
            'grounds' => ['required', 'array', 'min:1'],
            'grounds.*' => ['string', 'max:120'],
            'evidence_types' => ['required', 'array', 'min:1'],
            'evidence_types.*' => ['string', 'max:120'],
            'note' => ['required', 'string', 'max:3000'],
        ]);

        $availableGrounds = array_keys($this->defenceOppositionGroundDescriptions());
        $selectedGrounds = array_values(array_intersect($data['grounds'], $availableGrounds));
        $availableEvidenceTypes = $this->defenceEvidenceRequestTypes();
        $selectedEvidenceTypes = array_values(array_intersect($data['evidence_types'], $availableEvidenceTypes));

        if ($selectedGrounds === []) {
            return back()->withErrors(['grounds' => 'Please select at least one opposition ground.'])->withInput();
        }

        if ($selectedEvidenceTypes === []) {
            return back()->withErrors(['evidence_types' => 'Please select at least one evidence document to request.'])->withInput();
        }

        $case->grounds()->delete();
        foreach ($selectedGrounds as $ground) {
            OppositionGround::create([
                'case_id' => $case->id,
                'ground_name' => $ground,
                'admin_note' => $data['note'],
            ]);
        }

        $case->update([
            'requested_evidence_types' => $selectedEvidenceTypes,
            'evidence_request_note' => $data['note'],
            'admin_internal_notes' => $data['note'],
        ]);

        $this->transition($case, TrademarkOppositionWorkflow::ADMIN_EVIDENCE_COLLECTION, $data['note'], 'admin');

        $message = "Opposition grounds noted by the legal team:\n\n- "
            . implode("\n- ", $selectedGrounds)
            . "\n\nThe legal team has requested the following evidence documents:\n\n- "
            . implode("\n- ", $selectedEvidenceTypes)
            . "\n\nAdmin note:\n" . $data['note'];

        $this->notifyClient($case, 'Evidence Required', $message);
        Mail::to($case->email)->send(new EventNotification(
            $case->user,
            'Evidence Required',
            $message,
            [],
            route('trademark-opposition.show', $case),
            'Upload Evidence'
        ));

        return back()->with('success', 'Evidence request sent.');
    }

    public function adminReviewEvidence(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        abort_unless($case->flow_type === TrademarkOppositionWorkflow::FLOW_DEFEND, 404);

        $data = $request->validate([
            'evidence_ids' => ['required', 'array', 'min:1'],
            'evidence_ids.*' => ['integer'],
            'action' => ['required', 'in:reviewed,rejected'],
            'note' => ['required_if:action,rejected', 'nullable', 'string', 'max:1500'],
        ]);

        $evidenceItems = $case->evidence()
            ->where('file_path', '!=', 'metadata')
            ->whereIn('id', $data['evidence_ids'])
            ->get();

        if ($evidenceItems->isEmpty()) {
            return back()->withErrors(['evidence' => 'Please select at least one uploaded evidence file.']);
        }

        $evidenceLabels = TrademarkOppositionWorkflow::evidenceTypes();
        $reviewNote = trim((string) ($data['note'] ?? ''));
        $selectedLabels = [];
        $isRegistryStageEvidence = $evidenceItems->contains(
            fn (OppositionEvidence $evidence) => Str::startsWith($evidence->evidence_type, 'third_party_requested_evidence_')
        );
        $labelForEvidence = static function (OppositionEvidence $evidence) use ($evidenceLabels): string {
            if (Str::startsWith($evidence->evidence_type, 'third_party_requested_evidence_')) {
                return $evidence->review_note
                    ?: Str::headline(Str::after($evidence->evidence_type, 'third_party_requested_evidence_'));
            }

            return $evidenceLabels[$evidence->evidence_type] ?? Str::headline(str_replace('_', ' ', $evidence->evidence_type));
        };

        foreach ($evidenceItems as $evidence) {
            $selectedLabels[] = $labelForEvidence($evidence);

            $evidence->update([
                'review_status' => $data['action'],
                'review_note' => $isRegistryStageEvidence
                    ? ($data['action'] === 'rejected' ? ($reviewNote ?: $labelForEvidence($evidence)) : $labelForEvidence($evidence))
                    : ($reviewNote ?: null),
                'reviewed_at' => now(),
                'reviewed_by' => 'admin',
            ]);
        }

        if ($data['action'] === 'rejected') {
            if ($isRegistryStageEvidence) {
                $case->update([
                    'third_party_evidence_pending' => true,
                    'third_party_evidence_requests' => $selectedLabels,
                    'third_party_evidence_message' => $reviewNote ?: $case->third_party_evidence_message,
                ]);

                $this->recordStatus(
                    $case,
                    $case->current_admin_status,
                    $case->current_admin_status,
                    'Evidence Stage document(s) rejected for reupload: ' . implode(', ', $selectedLabels),
                    'admin'
                );

                $message = "The following Evidence Stage document(s) need to be reuploaded:\n\n- "
                    . implode("\n- ", $selectedLabels)
                    . "\n\nIssues to fix:\n" . $reviewNote;

                $this->notifyClient(
                    $case,
                    'Evidence Stage Documents Need Reupload',
                    $message,
                    [],
                    'Reupload Evidence',
                    route('trademark-opposition.show', $case) . '#action-center'
                );

                return back()->with('success', 'Rejected Evidence Stage document(s) were sent back to the client for reupload.');
            }

            $this->transition($case, TrademarkOppositionWorkflow::ADMIN_EVIDENCE_COLLECTION, 'Evidence rejected for reupload: ' . implode(', ', $selectedLabels), 'admin');

            $message = "The following evidence file(s) need to be reuploaded:\n\n- "
                . implode("\n- ", $selectedLabels)
                . "\n\nIssues to fix:\n" . $reviewNote;

            $this->notifyClient($case, 'Evidence Needs Reupload', $message);
            Mail::to($case->email)->send(new EventNotification(
                $case->user,
                'Evidence Needs Reupload',
                $message,
                [],
                route('trademark-opposition.show', $case),
                'Reupload Evidence'
            ));

            return back()->with('success', 'Rejected evidence was sent back to the client for reupload.');
        }

        $freshCase = $case->fresh('evidence');
        if ($isRegistryStageEvidence) {
            $latestRegistryStageEvidence = $freshCase->evidence
                ->where('file_path', '!=', 'metadata')
                ->where('uploaded_by', 'client')
                ->filter(fn (OppositionEvidence $evidence) => Str::startsWith($evidence->evidence_type, 'third_party_requested_evidence_'))
                ->sortByDesc('id')
                ->unique('evidence_type');
            $allLatestRegistryStageEvidenceReviewed = $latestRegistryStageEvidence->isNotEmpty()
                && $latestRegistryStageEvidence->every(fn (OppositionEvidence $evidence) => $evidence->review_status === 'reviewed');

            if ($allLatestRegistryStageEvidenceReviewed) {
                $case->update(['third_party_evidence_pending' => false]);
                $this->transition(
                    $case,
                    TrademarkOppositionWorkflow::ADMIN_HEARING_PREPARATION,
                    'All Evidence Stage documents reviewed. Matter moved to Hearing Stage.',
                    'admin'
                );
                $this->notifyClient(
                    $case,
                    'Hearing Stage',
                    'The evidence documents have been reviewed. The matter has moved to Hearing Stage.'
                );

                return back()->with('success', 'Evidence marked as reviewed and matter moved to Hearing Stage.');
            }

            $this->recordStatus(
                $case,
                $case->current_admin_status,
                $case->current_admin_status,
                'Evidence Stage document(s) reviewed and accepted: ' . implode(', ', $selectedLabels),
                'admin'
            );

            return back()->with('success', 'Evidence Stage document(s) marked as reviewed.');
        }

        $latestEvidence = $freshCase->evidence
            ->where('file_path', '!=', 'metadata')
            ->sortByDesc('id')
            ->unique('evidence_type');
        $allLatestEvidenceReviewed = $latestEvidence->isNotEmpty()
            && $latestEvidence->every(fn (OppositionEvidence $evidence) => $evidence->review_status === 'reviewed');

        if ($allLatestEvidenceReviewed) {
            $this->transition($case, TrademarkOppositionWorkflow::ADMIN_LEGAL_ANALYSIS, 'All requested evidence reviewed. Legal review is ready.', 'admin');
        } else {
            $this->recordStatus($case, $case->current_admin_status, $case->current_admin_status, 'Evidence reviewed and accepted: ' . implode(', ', $selectedLabels), 'admin');
        }

        return back()->with('success', 'Evidence marked as reviewed.');
    }

    public function adminRisk(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        $data = $request->validate([
            'risk_level' => ['required', 'in:Low Risk,Medium Risk,High Risk'],
            'internal_admin_note' => ['nullable', 'string', 'max:3000'],
            'client_visible_note' => ['nullable', 'string', 'max:3000'],
            'package_name' => ['required', 'string', 'max:160'],
            'package_price' => ['required', 'numeric', 'min:1'],
            'included_services' => ['nullable', 'string', 'max:3000'],
            'optional_documents' => ['nullable', 'array'],
            'optional_documents.*' => ['file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:10240'],
        ]);

        $clientVisibleNote = $data['client_visible_note'] ?: TrademarkOppositionWorkflow::riskNotes()[$data['risk_level']];
        $includedServices = $this->linesToArray($data['included_services'] ?? '');

        $case->update([
            'risk_level' => $data['risk_level'],
            'risk_note' => $clientVisibleNote,
            'admin_internal_notes' => $data['internal_admin_note'] ?: null,
            'package_name' => $data['package_name'],
            'package_price' => $data['package_price'],
            'included_services' => $includedServices,
        ]);

        foreach ($request->file('optional_documents', []) as $file) {
            $this->storeEvidence($case, 'legal_review_optional_document', $file, 'admin');
        }

        $this->transition($case, TrademarkOppositionWorkflow::ADMIN_LEGAL_ANALYSIS_COMPLETED, 'Admin sent legal risk assessment.', 'admin');

        return back()->with('success', 'Risk assessment saved.');
    }

    public function adminPricing(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        $data = $request->validate([
            'package_name' => ['required', 'string', 'max:160'],
            'package_price' => ['required', 'numeric', 'min:1'],
            'included_services' => ['nullable', 'string', 'max:3000'],
            'add_ons' => ['nullable', 'string', 'max:3000'],
        ]);

        $case->update([
            'package_name' => $data['package_name'],
            'package_price' => $data['package_price'],
            'included_services' => $this->linesToArray($data['included_services'] ?? ''),
            'add_ons' => $this->linesToArray($data['add_ons'] ?? ''),
        ]);

        return back()->with('success', 'Pricing package published.');
    }

    public function adminUploadDraft(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        $data = $request->validate([
            'draft' => [$case->draft_path ? 'nullable' : 'required', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:10240'],
            'optional_documents' => ['nullable', 'array', 'max:10'],
            'optional_documents.*' => ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:15360'],
            'optional_document_names' => ['nullable', 'array', 'max:10'],
            'optional_document_names.*' => ['nullable', 'string', 'max:255'],
        ], [
            'draft.uploaded' => 'The Counter Statement Draft could not be uploaded. Ensure it is below 10 MB and restart the PHP server after changing upload limits.',
            'optional_documents.*.uploaded' => 'An additional document could not be uploaded. Ensure every file is below 15 MB and the PHP upload limit is at least 15 MB.',
            'optional_documents.*.max' => 'Each additional document must be 15 MB or smaller.',
            'optional_documents.max' => 'You can attach a maximum of 10 additional documents at a time.',
        ]);

        if (isset($data['draft'])) {
            $path = $data['draft']->store('opposition/drafts/' . $case->id, 'public');

            $case->update([
                'draft_path' => $path,
                'draft_name' => $data['draft']->getClientOriginalName(),
            ]);
        }

        foreach ($request->file('optional_documents', []) as $index => $file) {
            $additionalDocument = $this->storeEvidence($case, 'counter_statement_additional_document', $file, 'admin');
            $documentName = trim((string) ($request->input('optional_document_names', [])[$index] ?? ''));

            if ($documentName !== '') {
                $additionalDocument->update(['review_note' => $documentName]);
            }
        }

        $case->update([
            'client_approval_status' => 'pending',
            'client_approval_note' => null,
            'client_change_request' => null,
        ]);

        $this->transition($case, TrademarkOppositionWorkflow::ADMIN_CLIENT_APPROVAL, 'Counter statement draft sent for client approval.', 'admin');
        $case->load('evidence');
        $mailAttachments = collect([
            [
                'path' => Storage::disk('public')->path($case->draft_path),
                'name' => $case->draft_display_name,
            ],
        ])->merge(
            $case->evidence
                ->where('evidence_type', 'counter_statement_additional_document')
                ->map(fn (OppositionEvidence $document) => [
                    'path' => Storage::disk('public')->path($document->file_path),
                    'name' => $document->file_name,
                ])
        )->all();

        $emailSent = $this->notifyClient(
            $case,
            'Counter Statement Draft Ready',
            'Your counter statement draft and supporting documents are attached and ready for review. Sign in to view every document, approve the draft, or request changes.',
            $mailAttachments,
            'Review Counter Statement Draft',
            route('trademark-opposition.action-center', $case)
        );

        return back()->with(
            $emailSent ? 'success' : 'warning',
            $emailSent
                ? 'Counter statement draft uploaded and emailed to the client with all attachments.'
                : 'The draft was uploaded and the in-app notification was created, but the email could not be sent. Check the mail configuration or application log.'
        );
    }

    public function adminFile(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        if (!Schema::hasColumn('trademark_opposition_cases', 'defence_case_status')) {
            return back()
                ->withErrors(['defence_case_status' => 'The defence status field is not available yet. Please run the latest database migration and try again.'])
                ->withInput();
        }

        $filingAcknowledgmentRules = $case->filing_acknowledgment_path
            ? ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:10240']
            : ['required', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:10240'];

        $data = $request->validate([
            'filing_acknowledgment' => $filingAcknowledgmentRules,
            'optional_documents' => ['nullable', 'array', 'max:10'],
            'optional_documents.*' => ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:15360'],
            'optional_document_names' => ['nullable', 'array', 'max:10'],
            'optional_document_names.*' => ['nullable', 'string', 'max:255'],
        ], [
            'optional_documents.*.uploaded' => 'An additional document could not be uploaded. Ensure every file is below 15 MB.',
            'optional_documents.*.max' => 'Each additional document must be 15 MB or smaller.',
            'optional_documents.max' => 'You can attach a maximum of 10 additional documents at a time.',
        ]);

        $filingAcknowledgment = $data['filing_acknowledgment'] ?? null;
        $path = $case->filing_acknowledgment_path;
        $name = $case->filing_acknowledgment_name;

        if ($filingAcknowledgment) {
            $path = $filingAcknowledgment->store('opposition/filing-acknowledgments/' . $case->id, 'public');
            $name = $filingAcknowledgment->getClientOriginalName();
        }

        $case->update([
            'filing_acknowledgment_path' => $path,
            'filing_acknowledgment_name' => $name,
        ]);

        foreach ($request->file('optional_documents', []) as $index => $file) {
            $document = $this->storeEvidence($case, 'counter_statement_filing_additional_document', $file, 'admin');
            $documentName = trim((string) ($request->input('optional_document_names', [])[$index] ?? ''));

            if ($documentName !== '') {
                $document->update(['review_note' => $documentName]);
            }
        }

        $this->transition(
            $case,
            TrademarkOppositionWorkflow::ADMIN_AWAITING_EVIDENCE_STAGE,
            'Counter statement filed and acknowledgment uploaded. Matter moved to Awaiting Evidence Stage.',
            'admin'
        );
        $this->notifyClient(
            $case,
            'Counter Statement Filed',
            'Your Counter Statement has been filed successfully. The matter is now active and awaiting the next procedural stage from the Trademark Registry.'
        );

        return back()->with('success', 'Filing acknowledgment uploaded and matter moved to Awaiting Evidence Stage.');
    }

    public function adminTracking(Request $request, TrademarkOppositionCase $case): RedirectResponse
    {
        if (!Schema::hasColumn('trademark_opposition_cases', 'defence_case_status')) {
            return back()
                ->withErrors(['defence_case_status' => 'The defence status field is not available yet. Please run the latest database migration and try again.'])
                ->withInput();
        }

        $allowedStatuses = TrademarkOppositionWorkflow::trackingStatuses();
        $data = $request->validate([
            'status' => ['required', Rule::in($allowedStatuses)],
            'final_outcome' => ['nullable', Rule::in(array_keys(TrademarkOppositionWorkflow::defenceFinalOutcomeOptions()))],
            'final_client_message' => ['nullable', 'string', 'max:3000'],
            'note' => ['nullable', 'string', 'max:3000'],
            'hearing_date' => ['nullable', 'date'],
            'hearing_notice' => ['nullable', 'file', 'mimes:pdf', 'max:15360'],
            'hearing_outcome' => ['nullable', Rule::in(['Further Hearing Required', 'Matter Concluded'])],
            'adjournment_reason' => ['nullable', 'string', 'max:3000'],
            'evidence_items' => ['nullable', 'array', 'max:10'],
            'evidence_items.*' => ['nullable', 'string', 'max:255'],
            'message_to_client' => ['nullable', 'string', 'max:3000'],
            'resend_registry_request' => ['nullable', 'boolean'],
            'final_order' => ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:15360'],
            'optional_documents' => ['nullable', 'array', 'max:10'],
            'optional_documents.*' => ['nullable', 'file', 'mimes:' . self::ALLOWED_UPLOADS, 'max:15360'],
            'optional_document_names' => ['nullable', 'array', 'max:10'],
            'optional_document_names.*' => ['nullable', 'string', 'max:255'],
            'optional_document_visibilities' => ['nullable', 'array', 'max:10'],
            'optional_document_visibilities.*' => ['nullable', Rule::in(['client', 'admin'])],
        ], [
            'optional_documents.*.uploaded' => 'An additional document could not be uploaded. Ensure every file is below 15 MB.',
            'optional_documents.*.max' => 'Each additional document must be 15 MB or smaller.',
            'optional_documents.max' => 'You can attach a maximum of 10 additional documents at a time.',
            'final_order.max' => 'The final order must be 15 MB or smaller.',
            'final_order.uploaded' => 'The final order could not be uploaded. Ensure it is below 15 MB.',
            'hearing_notice.max' => 'The hearing notice must be 15 MB or smaller.',
            'hearing_notice.uploaded' => 'The hearing notice could not be uploaded. Ensure it is below 15 MB.',
        ]);

        $hearingStatuses = [
            TrademarkOppositionWorkflow::ADMIN_HEARING_PREPARATION,
            TrademarkOppositionWorkflow::ADMIN_HEARING_SCHEDULED,
            TrademarkOppositionWorkflow::ADMIN_HEARING_COMPLETED,
            TrademarkOppositionWorkflow::ADMIN_HEARING_ADJOURNED,
        ];
        $isCurrentHearingStatus = in_array($case->current_admin_status, $hearingStatuses, true);
        $isHearingUpdate = $isCurrentHearingStatus && in_array($data['status'], $hearingStatuses, true);
        $nextStage = TrademarkOppositionWorkflow::nextDefendStage($case->current_admin_status);
        if ($case->current_admin_status === TrademarkOppositionWorkflow::ADMIN_FINAL_OUTCOME) {
            $nextStage = TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED;
        }
        $isRegistryRequestResend = (bool) ($data['resend_registry_request'] ?? false)
            && ($data['status'] ?? null) === $case->current_admin_status
            && in_array($case->current_admin_status, [
                TrademarkOppositionWorkflow::ADMIN_EVIDENCE_BY_OPPONENT,
                TrademarkOppositionWorkflow::ADMIN_EVIDENCE_BY_APPLICANT,
                TrademarkOppositionWorkflow::ADMIN_EVIDENCE_IN_REPLY,
                TrademarkOppositionWorkflow::ADMIN_EVIDENCE_FILED,
            ], true);
        $isMatterClosedUpdate = $case->current_admin_status === TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED
            && ($data['status'] ?? null) === TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED;
        if (($data['status'] ?? null) !== $nextStage && !$isRegistryRequestResend && !$isHearingUpdate && !$isMatterClosedUpdate) {
            return back()
                ->withErrors(['status' => 'Please move this matter through the next sequential stage.'])
                ->withInput();
        }

        if ($isHearingUpdate && $data['status'] === TrademarkOppositionWorkflow::ADMIN_HEARING_SCHEDULED && blank($data['hearing_date'] ?? null)) {
            return back()->withErrors(['hearing_date' => 'Please select the hearing date when the hearing is scheduled.'])->withInput();
        }

        if ($isHearingUpdate && $data['status'] === TrademarkOppositionWorkflow::ADMIN_HEARING_ADJOURNED && blank($data['hearing_date'] ?? null)) {
            return back()->withErrors(['hearing_date' => 'Please select the new hearing date when the hearing is adjourned.'])->withInput();
        }

        if ($isHearingUpdate && $data['status'] === TrademarkOppositionWorkflow::ADMIN_HEARING_COMPLETED && blank($data['hearing_outcome'] ?? null)) {
            return back()->withErrors(['hearing_outcome' => 'Please select the hearing outcome after the hearing is completed.'])->withInput();
        }

        if ($isHearingUpdate
            && $data['status'] === TrademarkOppositionWorkflow::ADMIN_HEARING_COMPLETED
            && ($data['hearing_outcome'] ?? null) === 'Further Hearing Required'
            && blank($data['hearing_date'] ?? null)) {
            return back()->withErrors(['hearing_date' => 'Please select the new hearing date when a further hearing is required.'])->withInput();
        }

        $requestedEvidenceItems = collect($data['evidence_items'] ?? [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
        $messageToClient = trim((string) ($data['message_to_client'] ?? ''));
        $shouldRequestDocuments = $requestedEvidenceItems !== [];

        if (($data['status'] ?? null) === TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED) {
            if (!in_array($case->current_admin_status, [
                TrademarkOppositionWorkflow::ADMIN_FINAL_OUTCOME,
                TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED,
            ], true)) {
                return back()
                    ->withErrors(['status' => 'The matter can only be closed from the Final Outcome stage.'])
                    ->withInput();
            }

            if (blank($data['final_outcome'] ?? null)) {
                return back()
                    ->withErrors(['final_outcome' => 'Please select the final outcome before closing the matter.'])
                    ->withInput();
            }
        }

        foreach ($request->file('optional_documents', []) as $index => $file) {
            $visibility = ($request->input('optional_document_visibilities', [])[$index] ?? 'client') === 'admin'
                ? 'admin'
                : 'client';
            $documentType = match (true) {
                $case->current_admin_status === TrademarkOppositionWorkflow::ADMIN_DECISION_AWAITED && $visibility === 'admin' => 'final_order_internal_document',
                $case->current_admin_status === TrademarkOppositionWorkflow::ADMIN_DECISION_AWAITED => 'decision_additional_document',
                $case->current_admin_status === TrademarkOppositionWorkflow::ADMIN_FINAL_OUTCOME && $visibility === 'admin' => 'final_order_internal_document',
                $case->current_admin_status === TrademarkOppositionWorkflow::ADMIN_FINAL_OUTCOME => 'decision_additional_document',
                $case->current_admin_status === TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED && $visibility === 'admin' => 'final_order_internal_document',
                $case->current_admin_status === TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED => 'decision_additional_document',
                $visibility === 'admin' => 'counter_statement_tracking_internal_document',
                default => 'counter_statement_tracking_additional_document',
            };
            $document = $this->storeEvidence($case, $documentType, $file, 'admin');
            $documentName = trim((string) ($request->input('optional_document_names', [])[$index] ?? ''));

            if ($documentName !== '') {
                $document->update(['review_note' => $documentName]);
            }
        }

        if ($request->hasFile('hearing_notice')) {
            $document = $this->storeEvidence($case, 'hearing_notice_document', $request->file('hearing_notice'), 'admin');
            $document->update(['review_note' => 'Hearing Notice']);
        }

        if ($shouldRequestDocuments) {
            $case->update([
                'third_party_evidence_requests' => $requestedEvidenceItems,
                'third_party_evidence_message' => $messageToClient,
                'third_party_evidence_pending' => true,
            ]);

            $message = ($messageToClient !== '' ? $messageToClient : 'Please upload the requested documents for the current Registry stage.')
                . "\n\nRequested documents:\n- " . implode("\n- ", $requestedEvidenceItems);

            $this->recordStatus(
                $case,
                $case->current_admin_status,
                $case->current_admin_status,
                'Registry-stage documents requested from client: ' . implode(', ', $requestedEvidenceItems),
                'admin'
            );
            $this->notifyClient(
                $case,
                $isRegistryRequestResend ? 'Evidence Stage Request Updated' : 'Additional Documents Required',
                $message,
                [],
                'Upload Requested Documents',
                route('trademark-opposition.show', $case) . '#action-center'
            );
        } elseif ($messageToClient !== '') {
            $case->update(['third_party_evidence_message' => $messageToClient]);
        }

        if ($isRegistryRequestResend) {
            return back()->with('success', 'Evidence Stage request updated and client notified.');
        }

        if ($isHearingUpdate) {
            $case->update([
                'hearing_date' => $data['hearing_date'] ?? $case->hearing_date,
                'final_outcome' => $data['hearing_outcome'] ?? $case->final_outcome,
            ]);

            $note = $data['note'] ?? 'Admin updated hearing status.';
            if (filled($data['adjournment_reason'] ?? null)) {
                $note = trim($note . ' Adjournment reason: ' . $data['adjournment_reason']);
            }
            if (filled($data['hearing_outcome'] ?? null)) {
                $note = trim($note . ' Hearing outcome: ' . $data['hearing_outcome']);
            }

            if ($data['status'] === TrademarkOppositionWorkflow::ADMIN_HEARING_COMPLETED) {
                $this->transition($case, TrademarkOppositionWorkflow::ADMIN_HEARING_COMPLETED, $note, 'admin');
                if (($data['hearing_outcome'] ?? null) === 'Further Hearing Required') {
                    $this->transition($case, TrademarkOppositionWorkflow::ADMIN_HEARING_SCHEDULED, 'Further hearing required. New hearing date scheduled.', 'admin');
                    $this->notifyClient($case, 'Further Hearing Scheduled', $messageToClient !== '' ? $messageToClient : 'A further hearing has been directed and a new hearing date has been scheduled.');
                } else {
                    $this->transition($case, TrademarkOppositionWorkflow::ADMIN_DECISION_AWAITED, 'Hearing completed. Awaiting final decision from the Trademark Registry.', 'admin');
                    $this->notifyClient($case, 'Hearing Completed', $messageToClient !== '' ? $messageToClient : 'The hearing has been completed. We are awaiting the final decision from the Trademark Registry.');
                }
            } else {
                $this->transition($case, $data['status'], $note, 'admin');
                if ($data['status'] === TrademarkOppositionWorkflow::ADMIN_HEARING_SCHEDULED) {
                    $this->notifyClient($case, 'Hearing Scheduled', $messageToClient !== '' ? $messageToClient : 'A hearing has been scheduled by the Trademark Registry.');
                } elseif ($data['status'] === TrademarkOppositionWorkflow::ADMIN_HEARING_ADJOURNED) {
                    $this->notifyClient($case, 'Hearing Rescheduled', $messageToClient !== '' ? $messageToClient : 'Your hearing has been rescheduled by the Trademark Registry.');
                } else {
                    $this->notifyClient($case, 'Hearing Stage', $messageToClient !== '' ? $messageToClient : 'The matter is now in hearing stage. Our team will share hearing details when available.');
                }
            }

            return back()->with('success', 'Hearing status updated.');
        }

        if (($data['status'] ?? null) === TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED) {
            if ($request->hasFile('final_order')) {
                $finalOrder = $this->storeEvidence($case, 'final_order_document', $request->file('final_order'), 'admin');
                $finalOrder->update(['review_note' => 'Final Registry Order']);
            }

            $finalOutcome = $data['final_outcome'];
            $defenceCaseStatus = match ($finalOutcome) {
                TrademarkOppositionWorkflow::DEFENCE_CASE_SUCCEEDED => TrademarkOppositionWorkflow::DEFENCE_CASE_SUCCEEDED,
                TrademarkOppositionWorkflow::DEFENCE_CASE_FAILED_TO_SUCCEED => TrademarkOppositionWorkflow::DEFENCE_CASE_FAILED_TO_SUCCEED,
                TrademarkOppositionWorkflow::ADMIN_SETTLEMENT_CLOSED,
                TrademarkOppositionWorkflow::ADMIN_WITHDRAWN,
                TrademarkOppositionWorkflow::ADMIN_OTHER => TrademarkOppositionWorkflow::DEFENCE_CASE_CLOSED,
                default => null,
            };

            $case->update([
                'final_outcome' => $finalOutcome,
                'defence_case_status' => $defenceCaseStatus,
                'final_client_message' => $data['final_client_message'] ?? null,
                'third_party_evidence_pending' => false,
                'admin_internal_notes' => $data['note'] ?? $case->admin_internal_notes,
            ]);
            $this->syncLinkedApplicationOppositionStatuses($case);

            $outcomeLabel = TrademarkOppositionWorkflow::defenceFinalOutcomeOptions()[$finalOutcome] ?? Str::headline((string) $finalOutcome);
            $transitionNote = 'Matter closed with final outcome: ' . $outcomeLabel . '.';
            if (filled($data['note'] ?? null)) {
                $transitionNote .= ' Note: ' . $data['note'];
            }

            if ($case->current_admin_status === TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED) {
                $case->forceFill([
                    'current_client_stage' => TrademarkOppositionWorkflow::CLIENT_MATTER_CLOSED,
                ])->save();
                $this->recordStatus($case, TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED, TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED, $transitionNote, 'admin');
            } else {
                $this->transition($case, TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED, $transitionNote, 'admin');
            }
            $this->notifyClient(
                $case,
                'Matter Closed',
                ($data['final_client_message'] ?? null)
                    ?: 'The Registry matter has been closed with final outcome: ' . $outcomeLabel . '.'
            );

            return redirect()
                ->route('admin.trademark-opposition.show', $case)
                ->with('success', 'Matter closed and client notified.');
        }

        $updates = [];
        if (array_key_exists('final_client_message', $data)) {
            $updates['final_client_message'] = $data['final_client_message'] ?: $case->final_client_message;
        }
        $case->update($updates);

        $transitionNote = $data['note'] ?? $this->defaultDefenceTrackingNote($data['status']);
        $this->transition($case, $data['status'], $transitionNote, 'admin');

        $notification = $this->defenceTrackingNotification($data['status'], $case);
        if ($notification) {
            $this->notifyClient($case, $notification['title'], $messageToClient !== '' ? $messageToClient : $notification['message']);
        }

        return back()->with('success', 'Tracking status updated.');
    }

    public function viewDocument(Request $request, TrademarkOppositionCase $case, string $kind, int $id)
    {
        $this->authorizeDocumentAccess($case);

        $record = $kind === 'evidence'
            ? $case->evidence()->findOrFail($id)
            : $case->documents()->findOrFail($id);

        if (!Storage::disk('public')->exists($record->file_path)) {
            abort(404);
        }

        $path = Storage::disk('public')->path($record->file_path);

        return $request->boolean('download')
            ? response()->download($path, $record->file_name)
            : response()->file($path);
    }

    public function viewCaseFile(Request $request, TrademarkOppositionCase $case, string $file)
    {
        $this->authorizeDocumentAccess($case);

        $path = match ($file) {
            'draft' => $case->draft_path,
            'filing-acknowledgment' => $case->filing_acknowledgment_path,
            'counter-statement' => $case->counter_statement_path,
            default => null,
        };

        if (!$path || !Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $absolutePath = Storage::disk('public')->path($path);
        $downloadName = match ($file) {
            'draft' => $case->draft_display_name,
            'filing-acknowledgment' => $case->filing_acknowledgment_name,
            'counter-statement' => $case->counter_statement_name,
            default => basename($path),
        };

        return $request->boolean('download')
            ? response()->download($absolutePath, $downloadName ?: basename($path))
            : response()->file($absolutePath);
    }

    private function storeDocument(TrademarkOppositionCase $case, string $documentType, $file, string $uploadedBy, bool $required, string $reviewStatus = 'pending'): OppositionDocument
    {
        $path = $file->store('opposition/documents/' . $case->id, 'public');

        return OppositionDocument::create([
            'case_id' => $case->id,
            'document_type' => $documentType,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $uploadedBy,
            'is_required' => $required,
            'review_status' => $reviewStatus,
        ]);
    }

    private function storeEvidence(TrademarkOppositionCase $case, string $evidenceType, $file, string $uploadedBy): OppositionEvidence
    {
        $path = $file->store('opposition/evidence/' . $case->id, 'public');
        $reviewStatus = 'pending';

        if ($uploadedBy === 'client') {
            $previousEvidence = $case->evidence()
                ->where('evidence_type', $evidenceType)
                ->where('uploaded_by', 'client')
                ->latest('id')
                ->first();

            if ($previousEvidence?->review_status === 'rejected') {
                $reviewStatus = 'reuploaded';
            }
        }

        return OppositionEvidence::create([
            'case_id' => $case->id,
            'evidence_type' => $evidenceType,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $uploadedBy,
            'review_status' => $reviewStatus,
        ]);
    }

    private function defenceEvidenceRequestTypes(): array
    {
        return [
            'Invoices',
            'GST Certificate',
            'Packaging',
            'Website Screenshots',
            'Domain Registration',
            'Amazon Listings',
            'Flipkart Listings',
            'Advertising Material',
            'Social Media Evidence',
            'Client Purchase Orders',
            'Catalogue',
            'Photos of Product',
            'Sales Figures',
            'Marketing Spend Proof',
        ];
    }

    private function defenceOppositionGroundDescriptions(): array
    {
        return [
            'Section 9' => 'Trademark is descriptive, generic, or lacks distinctiveness.',
            'Section 11' => 'Trademark is similar to an earlier trademark.',
            'Prior Use Claim' => 'Opponent claims they were using the mark before the applicant.',
            'Prior Registration Claim' => 'Opponent already has a registered trademark.',
            'Passing Off' => 'Opponent claims public confusion or misuse of brand reputation.',
            'Copyright Claim' => 'Opponent claims logo/design/content copyright issue.',
            'Bad Faith Filing' => 'Opponent claims the applicant filed with wrong intention.',
            'Well-known Mark Claim' => 'Opponent claims their brand is well-known.',
            'Dilution' => 'Opponent claims the new mark weakens their famous brand.',
            'Misrepresentation' => 'Opponent claims wrong or misleading representation.',
            'Multiple Grounds' => 'More than one ground is mentioned in the notice.',
        ];
    }

    private function evidenceRequestKey(string $label): string
    {
        return Str::slug($label, '_');
    }

    private function defaultDefenceTrackingNote(string $status): string
    {
        return match ($status) {
            TrademarkOppositionWorkflow::ADMIN_AWAITING_EVIDENCE_STAGE => 'Matter is awaiting the next evidence-stage direction from the Trademark Registry.',
            TrademarkOppositionWorkflow::ADMIN_EVIDENCE_BY_OPPONENT => 'Evidence by opponent stage started.',
            TrademarkOppositionWorkflow::ADMIN_EVIDENCE_BY_APPLICANT => 'Evidence by applicant stage started.',
            TrademarkOppositionWorkflow::ADMIN_EVIDENCE_IN_REPLY => 'Evidence in reply stage started.',
            TrademarkOppositionWorkflow::ADMIN_EVIDENCE_FILED => 'Evidence filed with the Trademark Registry.',
            TrademarkOppositionWorkflow::ADMIN_HEARING_PREPARATION => 'Matter moved to hearing preparation.',
            TrademarkOppositionWorkflow::ADMIN_HEARING_SCHEDULED => 'Registry hearing scheduled.',
            TrademarkOppositionWorkflow::ADMIN_HEARING_COMPLETED => 'Registry hearing completed.',
            TrademarkOppositionWorkflow::ADMIN_DECISION_AWAITED => 'Hearing completed. Awaiting final decision from the Trademark Registry.',
            default => 'Admin updated post-filing tracking.',
        };
    }

    private function defenceTrackingNotification(string $status, TrademarkOppositionCase $case): ?array
    {
        return match ($status) {
            TrademarkOppositionWorkflow::ADMIN_AWAITING_EVIDENCE_STAGE => [
                'title' => 'Awaiting Registry Stage',
                'message' => 'The matter is now awaiting the evidence stage. We will notify you when evidence filing or any action is required.',
            ],
            TrademarkOppositionWorkflow::ADMIN_EVIDENCE_BY_OPPONENT => [
                'title' => 'Evidence by Opponent',
                'message' => 'The matter has moved to Evidence by Opponent stage. We will update you if documents or action are required.',
            ],
            TrademarkOppositionWorkflow::ADMIN_EVIDENCE_BY_APPLICANT => [
                'title' => 'Evidence by Applicant',
                'message' => 'The matter has moved to applicant evidence stage. Please check if our legal team has requested any documents from you.',
            ],
            TrademarkOppositionWorkflow::ADMIN_EVIDENCE_IN_REPLY => [
                'title' => 'Evidence in Reply',
                'message' => 'The matter has moved to evidence in reply stage. We will update you if any action is required.',
            ],
            TrademarkOppositionWorkflow::ADMIN_EVIDENCE_FILED => [
                'title' => 'Evidence Filed',
                'message' => 'Evidence filing has been marked as completed. The matter will move toward hearing stage or further Registry action.',
            ],
            TrademarkOppositionWorkflow::ADMIN_HEARING_PREPARATION => [
                'title' => 'Hearing Preparation',
                'message' => 'The matter has moved to hearing preparation stage. Our team will share hearing details when available.',
            ],
            TrademarkOppositionWorkflow::ADMIN_HEARING_SCHEDULED => [
                'title' => 'Hearing Scheduled',
                'message' => 'A hearing has been scheduled by the Trademark Registry' . ($case->hearing_date ? ' for ' . $case->hearing_date->format('d M Y') : '') . '.',
            ],
            TrademarkOppositionWorkflow::ADMIN_HEARING_COMPLETED => [
                'title' => 'Hearing Completed',
                'message' => 'The hearing has been completed. The matter is now waiting for further Registry update.',
            ],
            TrademarkOppositionWorkflow::ADMIN_DECISION_AWAITED => [
                'title' => 'Decision Awaited',
                'message' => 'The matter is now awaiting the final decision from the Trademark Registry.',
            ],
            TrademarkOppositionWorkflow::ADMIN_FINAL_OUTCOME => [
                'title' => 'Final Outcome Ready',
                'message' => 'The Registry decision or final update is ready for review. Please log in to check the final status.',
            ],
            default => null,
        };
    }

    private function hasOpposeRequiredEvidence(TrademarkOppositionCase $case): bool
    {
        $uploaded = $case->evidence()
            ->where('file_path', '!=', 'metadata')
            ->where('uploaded_by', 'client')
            ->latest('id')
            ->get()
            ->unique('evidence_type')
            ->reject(fn (OppositionEvidence $evidence) => $evidence->review_status === 'rejected')
            ->pluck('evidence_type')
            ->all();

        foreach (TrademarkOppositionWorkflow::opposeRequiredEvidenceGroups() as $group) {
            if (count(array_intersect($group, $uploaded)) === 0) {
                return false;
            }
        }

        return true;
    }

    private function transition(TrademarkOppositionCase $case, string $status, ?string $note = null, string $changedBy = 'system'): void
    {
        $oldStatus = $case->current_admin_status;
        $clientStage = TrademarkOppositionWorkflow::clientStageForAdminStatus($status);
        $paidServiceStages = [
            TrademarkOppositionWorkflow::CLIENT_DRAFTING,
            TrademarkOppositionWorkflow::CLIENT_DOCUMENT_FILED,
            TrademarkOppositionWorkflow::CLIENT_AWAITING_OTHER_PARTY,
            TrademarkOppositionWorkflow::CLIENT_AWAITING_EVIDENCE_STAGE,
            TrademarkOppositionWorkflow::CLIENT_EVIDENCE_STAGE,
            TrademarkOppositionWorkflow::CLIENT_HEARING_STAGE,
            TrademarkOppositionWorkflow::CLIENT_DECISION_AWAITED,
        ];

        abort_unless(
            ($status !== 'Payment Completed' && ! in_array($clientStage, $paidServiceStages, true))
                || $case->payment_status === 'paid',
            409,
            'Verified payment is required before this opposition stage can begin.',
        );

        $case->forceFill([
            'current_admin_status' => $status,
            'current_client_stage' => $clientStage,
        ])->save();

        $this->recordStatus($case, $oldStatus, $status, $note, $changedBy);
    }

    private function normalizeFiledDefenceStage(TrademarkOppositionCase $case): void
    {
        if (
            $case->flow_type !== TrademarkOppositionWorkflow::FLOW_DEFEND
            || $case->current_admin_status !== TrademarkOppositionWorkflow::ADMIN_COUNTER_STATEMENT_FILED
            || blank($case->filing_acknowledgment_path)
        ) {
            return;
        }

        $this->transition(
            $case,
            TrademarkOppositionWorkflow::ADMIN_AWAITING_EVIDENCE_STAGE,
            'Counter statement filing was completed. Matter moved to Awaiting Evidence Stage.',
            'system'
        );
    }

    private function recordStatus(TrademarkOppositionCase $case, ?string $oldStatus, string $newStatus, ?string $note = null, string $changedBy = 'system'): void
    {
        CaseStatusHistory::create([
            'case_id' => $case->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $changedBy,
            'note' => $note,
        ]);
    }

    private function logOppositionDefenceCaseStarted(Application $application, TrademarkOppositionCase $case): void
    {
        if (!Schema::hasTable('application_status_logs')) {
            return;
        }

        ApplicationStatusLog::create([
            'application_id' => $application->id,
            'from_status' => $application->current_status,
            'to_status' => $application->current_status,
            'actor_type' => 'system',
            'actor_id' => null,
            'reason' => 'Client started an opposition defence case for this opposed trademark filing.',
            'metadata' => [
                'event' => 'opposition_defence_case_started',
                'opposition_case_id' => $case->id,
                'opposition_case_number' => $case->case_number,
            ],
        ]);
    }

    private function notifyAdminsOfOppositionDefenceCase(Application $application, TrademarkOppositionCase $case): void
    {
        try {
            $applicationLabel = $application->application_number ?: ('Application #' . $application->id);
            $title = 'Opposition Defence Case Started for ' . $applicationLabel;
            $body = implode(PHP_EOL, array_filter([
                'A client has started an opposition defence case for an opposed filing.',
                '',
                'Application: ' . $applicationLabel,
                'Trademark: ' . ($application->brand_name ?: 'N/A'),
                'Opposition Case: ' . $case->case_number,
                'Opposition Defence Case ID: ' . $case->id,
                '',
                'Open the filing detail page to review the case and stage action.',
            ]));

            Admin::query()->pluck('email')->filter()->unique()->each(function (string $email) use ($title, $body) {
                Mail::raw($body, function ($mail) use ($email, $title) {
                    $mail->to($email)->subject($title);
                });
            });
        } catch (\Throwable $e) {
            // Keep defence-case creation resilient if admin mail is unavailable.
        }
    }

    private function notifyClient(TrademarkOppositionCase $case, string $title, string $message, array $mailAttachments = [], string $actionText = 'Open Opposition Case', ?string $actionUrl = null): bool
    {
        $actionUrl ??= $case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE
            ? route('trademark-opposition.oppose.show', $case)
            : route('trademark-opposition.show', $case);

        Notification::create([
            'user_id' => $case->user_id,
            'type' => 'trademark_opposition',
            'title' => $title,
            'message' => $message,
            'data' => [
                'opposition_case_id' => $case->id,
                'action_url' => $actionUrl,
            ],
        ]);

        NotificationLog::create([
            'case_id' => $case->id,
            'notification_type' => $title,
            'channel' => 'in_app',
            'message' => $message,
            'sent_at' => now(),
            'status' => 'sent',
        ]);

        try {
            Mail::to($case->email)->send(new EventNotification(
                $case->user,
                $title,
                $message,
                $mailAttachments,
                $actionUrl,
                $actionText
            ));

            NotificationLog::create([
                'case_id' => $case->id,
                'notification_type' => $title,
                'channel' => 'email',
                'message' => $message,
                'sent_at' => now(),
                'status' => 'sent',
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Trademark opposition notification email failed.', [
                'case_id' => $case->id,
                'title' => $title,
                'error' => $exception->getMessage(),
            ]);

            NotificationLog::create([
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

    private function authorizeClient(TrademarkOppositionCase $case): void
    {
        abort_unless(Auth::id() === $case->user_id, 403);
    }

    private function authorizeDocumentAccess(TrademarkOppositionCase $case): void
    {
        if (Auth::guard('admin')->check()) {
            return;
        }

        $this->authorizeClient($case);
    }

    private function linesToArray(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $value))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    private function discountedPackagePricing(string $service, float $amount, ?int $couponId = null): array
    {
        $originalAmount = round(max($amount, 0), 2);
        $availableCoupons = Auth::check()
            ? DiscountCoupon::availableForPayment($service, Auth::id())
            : collect();
        $coupon = $couponId
            ? $availableCoupons->firstWhere('id', $couponId)
            : null;

        if (! $coupon && Auth::check()) {
            $coupon = DiscountCoupon::autoApplyForPayment($service, Auth::id());
        }

        $discountAmount = $coupon ? round($coupon->discountAmountFor($originalAmount), 2) : 0.0;
        $payableAmount = round(max($originalAmount - $discountAmount, 0), 2);

        return [
            'original_amount' => $originalAmount,
            'payable_amount' => $payableAmount,
            'discount_amount' => $discountAmount,
            'coupon_label' => $coupon ? $coupon->code : null,
        ];
    }

    private function adminStatuses(): array
    {
        return [
            TrademarkOppositionWorkflow::ADMIN_APPLICATION_RECEIVED,
            TrademarkOppositionWorkflow::ADMIN_DOCUMENTS_PENDING,
            TrademarkOppositionWorkflow::ADMIN_OPPOSITION_REVIEW,
            TrademarkOppositionWorkflow::ADMIN_LEGAL_ANALYSIS,
            TrademarkOppositionWorkflow::ADMIN_EVIDENCE_COLLECTION,
            TrademarkOppositionWorkflow::ADMIN_COUNTER_STATEMENT_DRAFTING,
            TrademarkOppositionWorkflow::ADMIN_CLIENT_APPROVAL,
            TrademarkOppositionWorkflow::ADMIN_READY_FOR_FILING,
            TrademarkOppositionWorkflow::ADMIN_COUNTER_STATEMENT_FILED,
            ...TrademarkOppositionWorkflow::trackingStatuses(),
            'Payment Completed',
        ];
    }

    private function adminOpposeStatuses(): array
    {
        return [
            TrademarkOppositionWorkflow::ADMIN_APPLICATION_RECEIVED,
            TrademarkOppositionWorkflow::ADMIN_DOCUMENTS_PENDING,
            TrademarkOppositionWorkflow::CLIENT_LEGAL_REVIEW,
            TrademarkOppositionWorkflow::ADMIN_LEGAL_ANALYSIS_COMPLETED,
            'Payment Completed',
            TrademarkOppositionWorkflow::ADMIN_NOTICE_DRAFTING,
            TrademarkOppositionWorkflow::ADMIN_DRAFT_UNDER_LEGAL_REVIEW,
            TrademarkOppositionWorkflow::ADMIN_CLIENT_APPROVAL_PENDING,
            TrademarkOppositionWorkflow::ADMIN_READY_FOR_FILING,
            TrademarkOppositionWorkflow::ADMIN_NOTICE_FILED,
            ...$this->adminOpposeTrackingStatuses(),
        ];
    }

    private function syncLinkedApplicationOppositionStatuses(TrademarkOppositionCase $case): void
    {
        $application = Application::query()
            ->when(
                $case->flow_type === TrademarkOppositionWorkflow::FLOW_DEFEND,
                fn ($query) => $query->where(function ($nested) use ($case) {
                    $nested->where('opposition_defence_case_id', $case->id)
                        ->orWhere('application_number', $case->application_number);
                }),
                fn ($query) => $query->where(function ($nested) use ($case) {
                    $nested->where('opposition_application_id', $case->id)
                        ->orWhere('application_number', $case->application_number);
                })
            )
            ->first();

        if (!$application) {
            return;
        }

        $updates = [];

        if ($case->flow_type === TrademarkOppositionWorkflow::FLOW_DEFEND) {
            $updates['opposition_defence_case_id'] = $case->id;

            $defenceFinalResult = $this->applicationFinalResultFromDefenceCase($case);
            if ($defenceFinalResult !== null) {
                $updates['opposition_defence_status'] = $case->defence_case_status;
            }

            if (Schema::hasColumn('applications', 'final_opposition_result')) {
                $updates['final_opposition_result'] = $defenceFinalResult;
            }

            $workflowMeta = $application->workflow_meta ?? [];
            $acceptedAdvertisedMeta = data_get($workflowMeta, 'post_filing_journey.stages.accepted_advertised', []);
            $acceptedAdvertisedMeta['final_opposition_result'] = $defenceFinalResult;
            data_set($workflowMeta, 'post_filing_journey.stages.accepted_advertised', $acceptedAdvertisedMeta);
            $updates['workflow_meta'] = $workflowMeta;
        } else {
            $updates['opposition_application_id'] = $case->id;
            $updates['opposition_status'] = $case->oppose_case_status ?: $application->opposition_status;
        }

        $application->update($updates);
    }

    private function applicationFinalResultFromDefenceCase(TrademarkOppositionCase $case): ?string
    {
        if ($case->flow_type !== TrademarkOppositionWorkflow::FLOW_DEFEND) {
            return null;
        }

        if (blank($case->filing_acknowledgment_path) || blank($case->defence_case_status)) {
            return null;
        }

        if (filled($case->final_outcome)) {
            return match ($case->final_outcome) {
                TrademarkOppositionWorkflow::DEFENCE_CASE_SUCCEEDED => TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_DEFENCE_SUCCESSFUL,
                TrademarkOppositionWorkflow::DEFENCE_CASE_FAILED_TO_SUCCEED => TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_OPPOSITION_SUCCESSFUL,
                TrademarkOppositionWorkflow::ADMIN_SETTLEMENT_CLOSED => TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_SETTLEMENT_CLOSED,
                TrademarkOppositionWorkflow::ADMIN_WITHDRAWN => TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_OPPOSITION_WITHDRAWN,
                TrademarkOppositionWorkflow::ADMIN_OTHER => TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_OTHER,
                default => null,
            };
        }

        return match ($case->defence_case_status) {
            TrademarkOppositionWorkflow::DEFENCE_CASE_SUCCEEDED => TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_DEFENCE_SUCCESSFUL,
            TrademarkOppositionWorkflow::DEFENCE_CASE_FAILED_TO_SUCCEED => TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_OPPOSITION_SUCCESSFUL,
            default => null,
        };
    }

    private function applyLinkedOppositionDecisionOutcome(TrademarkOppositionCase $case, array $decisionResolution): void
    {
        $application = Application::query()
            ->where(function ($query) use ($case) {
                $query->where('opposition_application_id', $case->id)
                    ->orWhere('application_number', $case->application_number);
            })
            ->first();

        $defenceCase = null;
        if ($application) {
            $defenceCase = $application->oppositionDefenceCase;
        }
        if (!$defenceCase) {
            $defenceCase = TrademarkOppositionCase::query()
                ->where('flow_type', TrademarkOppositionWorkflow::FLOW_DEFEND)
                ->where('application_number', $case->application_number)
                ->latest('id')
                ->first();
        }

        if (!$application) {
            return;
        }

        $applicationUpdates = [
            'opposition_application_id' => $case->id,
        ];

        if (Schema::hasColumn('applications', 'opposition_status') && ($decisionResolution['application_opposition_status'] ?? null) !== null) {
            $applicationUpdates['opposition_status'] = $decisionResolution['application_opposition_status'];
        }

        if ($defenceCase && Schema::hasColumn('applications', 'opposition_defence_case_id')) {
            $applicationUpdates['opposition_defence_case_id'] = $defenceCase->id;
        }

        $workflowMeta = $application->workflow_meta ?? [];
        $acceptedAdvertisedMeta = data_get($workflowMeta, 'post_filing_journey.stages.accepted_advertised', []);
        if (!empty($case->final_outcome)) {
            $acceptedAdvertisedMeta['final_opposition_outcome'] = $case->final_outcome;
        }
        data_set($workflowMeta, 'post_filing_journey.stages.accepted_advertised', $acceptedAdvertisedMeta);
        $applicationUpdates['workflow_meta'] = $workflowMeta;

        $application->update($applicationUpdates);
    }

    private function adminOpposeTrackingStatuses(): array
    {
        return [
            TrademarkOppositionWorkflow::CLIENT_AWAITING_OTHER_PARTY,
            TrademarkOppositionWorkflow::ADMIN_COUNTER_STATEMENT_AWAITED,
            TrademarkOppositionWorkflow::CLIENT_EVIDENCE_STAGE,
            TrademarkOppositionWorkflow::ADMIN_EVIDENCE_BY_OPPONENT,
            TrademarkOppositionWorkflow::ADMIN_EVIDENCE_BY_APPLICANT,
            TrademarkOppositionWorkflow::ADMIN_EVIDENCE_IN_REPLY,
            TrademarkOppositionWorkflow::ADMIN_HEARING_SCHEDULED,
            TrademarkOppositionWorkflow::ADMIN_HEARING_PREPARATION,
            TrademarkOppositionWorkflow::ADMIN_HEARING_COMPLETED,
            TrademarkOppositionWorkflow::ADMIN_HEARING_ADJOURNED,
            TrademarkOppositionWorkflow::ADMIN_DECISION_AWAITED,
            TrademarkOppositionWorkflow::ADMIN_OPPOSITION_ALLOWED,
            TrademarkOppositionWorkflow::ADMIN_OPPOSITION_DISMISSED,
            TrademarkOppositionWorkflow::ADMIN_SETTLEMENT_CLOSED,
            TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED,
        ];
    }
}
