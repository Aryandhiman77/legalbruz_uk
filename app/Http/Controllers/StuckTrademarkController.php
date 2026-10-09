<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentVerificationException;
use App\Mail\EventNotification;
use App\Models\DiscountCoupon;
use App\Models\Notification;
use App\Models\StuckTrademarkCase;
use App\Models\StuckTrademarkDocument;
use App\Models\StuckTrademarkStatusLog;
use App\Services\RazorpayPaymentVerifier;
use App\Support\StuckTrademarkWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StuckTrademarkController extends Controller
{
    public function landing()
    {
        return view('stuck-trademark.landing');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'applicant_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'trademark_name' => 'required|string|max:255',
            'application_number' => 'nullable|string|max:100',
            'trademark_class' => 'required|string|max:100',
            'filing_date' => 'nullable|date',
            'registry_status' => 'nullable|string|max:255',
            'prior_attorney_name' => 'nullable|string|max:255',
            'prior_attorney_contact' => 'nullable|string|max:255',
            'previous_attorney_details' => 'nullable|string|max:5000',
            'notices_received' => 'nullable|string|max:5000',
            'hearing_notices_missed' => 'nullable|string|max:5000',
            'status_unchanged_since' => 'required|string|max:255',
            'objection_or_hearing_notice_received' => 'required|in:yes,no,not_sure',
            'previous_attorney_explained_delay' => 'required|in:yes,no,not_sure,not_applicable',
            'correction_requirement_informed' => 'required|in:yes,no,not_sure',
            'problem_summary' => 'nullable|string|max:3000',
            'status_screenshot' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'notice_documents' => 'nullable|array',
            'notice_documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
            'hearing_notice_documents' => 'nullable|array',
            'hearing_notice_documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $derivedIssues = collect([
            $validated['registry_status'] ?: null,
            $validated['objection_or_hearing_notice_received'] === 'yes' ? 'objected' : null,
            filled($validated['hearing_notices_missed'] ?? null) ? 'hearing_missed' : null,
            filled($validated['previous_attorney_details'] ?? null) ? 'attorney_issue' : null,
            $validated['correction_requirement_informed'] === 'yes' ? 'formalities_issue' : null,
            $validated['registry_status'] === 'no_update' ? 'no_update' : null,
        ])->filter()->unique()->values()->all();

        $validated['issue_types'] = $derivedIssues ?: ['no_update'];
        $validated['urgency'] = 'standard';
        $validated['email'] = $validated['email'] ?: Auth::user()->email;
        $validated['phone'] = $validated['phone'] ?: (Auth::user()->phone ?? 'Not provided');
        $validated['problem_summary'] = ($validated['problem_summary'] ?? null) ?: 'Submitted via structured recovery intake questions.';

        unset($validated['status_screenshot'], $validated['notice_documents'], $validated['hearing_notice_documents']);

        $existingCase = StuckTrademarkCase::query()
            ->where('user_id', Auth::id())
            ->where('status', StuckTrademarkWorkflow::CLIENT_ONBOARDING)
            ->where('trademark_name', $validated['trademark_name'])
            ->where('application_number', $validated['application_number'] ?? null)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->latest('id')
            ->first();

        if ($existingCase) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Step 1 intake is already saved. Continue with client onboarding.',
                'case_id' => $existingCase->id,
                'next_step' => 2,
                'onboarding_url' => route('stuck-trademark.onboarding.submit', $existingCase),
                'documents_page_url' => route('stuck-trademark.documents', $existingCase),
                'documents_url' => route('stuck-trademark.documents.store', $existingCase),
                'show_url' => route('stuck-trademark.show', $existingCase),
            ]);
            }

            return redirect()->route('stuck-trademark.onboarding', $existingCase)
                ->with('success', 'Step 1 intake is already saved. Please finish client onboarding.');
        }

        $hasIntakeDocuments = $this->hasIntakeDocuments($request);

        $case = StuckTrademarkCase::create([
            ...$validated,
            'user_id' => Auth::id(),
            'case_number' => 'STR-' . now()->format('Ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => StuckTrademarkWorkflow::CLIENT_ONBOARDING,
            'audit_fee' => $validated['urgency'] === 'urgent' ? 2499 : ($validated['urgency'] === 'critical' ? 3999 : 1499),
        ]);

        $this->storeIntakeDocuments($request, $case);
        $this->logStatus($case, null, StuckTrademarkWorkflow::INTAKE_SUBMITTED, 'Service discovery completed', 'Applicant started the Filed and Stuck trademark recovery workflow.');
        $this->logStatus($case, StuckTrademarkWorkflow::INTAKE_SUBMITTED, StuckTrademarkWorkflow::CLIENT_ONBOARDING, 'Client onboarding started', 'Applicant completed Step 1 intake and moved to client onboarding.');
        if ($hasIntakeDocuments) {
            $this->logStatus($case, StuckTrademarkWorkflow::CLIENT_ONBOARDING, StuckTrademarkWorkflow::DOCUMENTS_UPLOADED, 'Documents uploaded', 'Applicant uploaded initial recovery documents during intake.');
        }
        $this->notifyApplicant(
            $case,
            'stuck_trademark_intake_submitted',
            'Step 1 completed',
            'Your stuck trademark recovery case ' . $case->case_number . ' has been created. Please complete client onboarding to continue.'
        );

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Step 1 intake is complete.',
                'case_id' => $case->id,
                'next_step' => 2,
                'onboarding_url' => route('stuck-trademark.onboarding.submit', $case),
                'documents_page_url' => route('stuck-trademark.documents', $case),
                'documents_url' => route('stuck-trademark.documents.store', $case),
                'show_url' => route('stuck-trademark.show', $case),
            ]);
        }

        return redirect()->route('stuck-trademark.onboarding', $case)
            ->with('success', 'Step 1 intake is complete. Please finish client onboarding.');
    }

    public function showOnboarding(StuckTrademarkCase $case)
    {
        $this->authorizeApplicant($case);

        return view('stuck-trademark.onboarding', ['case' => $case]);
    }

    public function submitOnboarding(Request $request, StuckTrademarkCase $case)
    {
        $this->authorizeApplicant($case);

        $validated = $request->validate([
            'applicant_name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'applicant_address' => 'required|string|max:3000',
            'trademark_name' => 'required|string|max:255',
            'application_number' => 'nullable|string|max:100',
            'trademark_class' => 'required|string|max:100',
            'filing_date' => 'nullable|date',
            'registry_status' => 'nullable|string|max:255',
            'prior_attorney_name' => 'nullable|string|max:255',
            'filing_channel' => 'required|in:personally,professional,not_sure',
            'received_notices' => 'required|in:yes,no,not_sure',
            'replies_filed_earlier' => 'required|in:yes,no,not_sure',
            'onboarding_issue_types' => 'required|array|min:1',
            'onboarding_issue_types.*' => 'string|max:80',
        ]);

        $case->update([
            ...$validated,
            'issue_types' => array_values(array_unique(array_merge($case->issue_types ?? [], $validated['onboarding_issue_types']))),
        ]);

        $from = $case->status;
        $toStatus = $case->documents()
            ->whereIn('document_type', $this->caseDocumentTypes())
            ->exists()
            ? StuckTrademarkWorkflow::DOCUMENTS_UNDER_VERIFICATION
            : StuckTrademarkWorkflow::PROBLEM_IDENTIFIED;

        $case->update(['status' => $toStatus]);

        $this->logStatus($case, $from, StuckTrademarkWorkflow::CLIENT_ONBOARDING, 'Client onboarding completed', 'Applicant completed Step 2 onboarding details.');
        $this->logStatus($case, StuckTrademarkWorkflow::CLIENT_ONBOARDING, StuckTrademarkWorkflow::PROBLEM_IDENTIFIED, 'Problem identified', 'Applicant selected the recovery issue categories.');

        if ($toStatus === StuckTrademarkWorkflow::DOCUMENTS_UNDER_VERIFICATION) {
            $this->logStatus($case, StuckTrademarkWorkflow::PROBLEM_IDENTIFIED, $toStatus, 'Document verification started', 'Uploaded documents are ready for admin verification.');
        }

        $this->notifyApplicant(
            $case,
            'stuck_trademark_onboarding_completed',
            'Client onboarding completed',
            'Your onboarding details have been submitted. You can now track the recovery case from your dashboard.'
        );

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Client onboarding completed successfully.',
                'case_id' => $case->id,
                'next_step' => 3,
                'documents_page_url' => route('stuck-trademark.documents', $case),
                'documents_url' => route('stuck-trademark.documents.store', $case),
                'show_url' => route('stuck-trademark.show', $case),
            ]);
        }

        return redirect()->route('stuck-trademark.documents', $case)
            ->with('success', 'Client onboarding completed successfully.');
    }

    public function showDocuments(StuckTrademarkCase $case)
    {
        $this->authorizeApplicant($case);

        return view('stuck-trademark.documents', [
            'case' => $case->load(['documents']),
        ]);
    }

    public function show(StuckTrademarkCase $case)
    {
        $this->authorizeApplicant($case);
        $this->ensureAuditPaymentBreakdown($case);

        return view('stuck-trademark.show', [
            'case' => $case->load(['documents', 'statusLogs', 'executionUpdates', 'executionDocuments']),
            'timeline' => StuckTrademarkWorkflow::timeline(),
            'auditPaymentCoupons' => DiscountCoupon::availableForPayment('audit_package_purchase', Auth::id()),
            'executionPaymentCoupons' => DiscountCoupon::availableForPayment('execution_package_purchase', Auth::id()),
        ]);
    }

    public function uploadDocuments(Request $request, StuckTrademarkCase $case)
    {
        $this->authorizeApplicant($case);

        $validated = $request->validate([
            'document_type' => 'nullable|string|max:100',
            'documents' => 'nullable|array',
            'documents.*' => 'file|mimes:pdf,jpg,jpeg,png,webp,doc,docx|max:10240',
            'document_uploads' => 'nullable|array',
            'document_uploads.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx|max:10240',
        ]);

        $uploads = $this->collectDocumentUploads($request, $validated['document_type'] ?? null);

        if ($uploads === []) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Please attach at least one document before submitting.',
                ], 422);
            }

            return redirect()->back()->with('error', 'Please attach at least one document before submitting.');
        }

        $requestedTypes = $case->documents()
            ->whereIn('status', ['reupload_requested', 'rejected'])
            ->pluck('document_type')
            ->unique()
            ->values();

        $isReupload = $case->status === StuckTrademarkWorkflow::REUPLOAD_REQUIRED
            || $requestedTypes->intersect(array_keys($uploads))->isNotEmpty();

        if (!$isReupload) {
            $existingCurrentTypes = $case->documents()
                ->whereIn('document_type', $this->mandatoryCaseDocumentTypes())
                ->pluck('document_type')
                ->unique()
                ->values()
                ->all();
            $submittedTypes = array_keys($uploads);
            $missingMandatoryTypes = array_values(array_diff(
                $this->mandatoryCaseDocumentTypes(),
                array_unique(array_merge($existingCurrentTypes, $submittedTypes))
            ));

            if ($missingMandatoryTypes !== []) {
                $message = 'Please attach all mandatory core documents before submitting: '
                    . collect($missingMandatoryTypes)
                        ->map(fn (string $type) => $this->caseDocumentTypeLabels()[$type] ?? ucwords(str_replace('_', ' ', $type)))
                        ->implode(', ')
                    . '.';

                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $message,
                    ], 422);
                }

                return redirect()->back()->with('error', $message);
            }
        }

        foreach (array_keys($uploads) as $documentType) {
            $case->documents()
                ->where('document_type', $documentType)
                ->whereIn('status', ['reupload_requested', 'rejected'])
                ->update([
                    'status' => 'reuploaded',
                    'verified_at' => null,
                ]);
        }

        $this->storeDocumentUploads($uploads, $case, $isReupload ? 'reuploaded' : 'pending');
        $from = $case->status;
        $case->update(['status' => StuckTrademarkWorkflow::DOCUMENTS_UNDER_VERIFICATION]);
        $this->logStatus(
            $case,
            $from,
            $case->status,
            $isReupload ? 'Documents reuploaded' : 'Documents uploaded',
            $isReupload
                ? 'Applicant reuploaded corrected recovery documents for admin verification.'
                : 'Applicant uploaded recovery documents for admin verification.'
        );
        $this->notifyAdmins(
            $isReupload ? 'Applicant reuploaded recovery documents' : 'Applicant uploaded recovery documents',
            ($case->case_number ?: 'Recovery case #' . $case->id) . ' is ready for document verification.'
        );
        $this->notifyApplicant(
            $case,
            $isReupload ? 'stuck_trademark_documents_reuploaded' : 'stuck_trademark_documents_uploaded',
            $isReupload ? 'Documents reuploaded' : 'Documents uploaded',
            $isReupload
                ? 'Your corrected recovery documents were uploaded and sent back for admin verification.'
                : 'Your recovery documents were uploaded and are ready for admin verification.'
        );

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Documents submitted successfully.',
                'show_url' => route('stuck-trademark.show', $case),
            ]);
        }

        return redirect()->route('stuck-trademark.show', $case)->with('success', 'Documents submitted successfully.');
    }

    public function markAuditPaid(StuckTrademarkCase $case)
    {
        $this->authorizeApplicant($case);

        return redirect()->route('stuck-trademark.show', $case)
            ->with('error', 'Please complete the Razorpay checkout to activate the audit package.');
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

    public function showAuditPackage(StuckTrademarkCase $case)
    {
        $this->authorizeApplicant($case);

        return view('stuck-trademark.audit-package', [
            'case' => $case->load(['documents']),
            'canPurchaseAuditPackage' => $case->audit_payment_status !== 'paid'
                && $this->documentsReadyForAudit($case),
            'paymentCoupons' => DiscountCoupon::availableForPayment('audit_package_purchase', Auth::id()),
        ]);
    }

    public function createAuditOrder(Request $request, StuckTrademarkCase $case)
    {
        $this->authorizeApplicant($case);

        if (!$this->documentsReadyForAudit($case)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please complete document verification before purchasing the audit package.',
            ], 422);
        }

        if ($case->audit_payment_status === 'paid') {
            return response()->json([
                'status' => 'error',
                'message' => 'Audit package payment is already complete.',
            ], 422);
        }

        $pricing = $this->discountedPackagePricing(
            'audit_package_purchase',
            (float) $case->audit_fee,
            $request->integer('audit_discount_coupon_id') ?: null
        );
        $amount = $pricing['payable_amount'];

        if ($amount <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Audit package amount is not configured for this case.',
            ], 422);
        }

        try {
            $razorpayKeyId = config('razorpay.key_id');
            $razorpaySecret = config('razorpay.key_secret');

            if (!$razorpayKeyId || !$razorpaySecret) {
                throw new \Exception('Razorpay credentials are not configured.');
            }

            $amountInPaise = (int) round($amount * 100);
            $receipt = 'str_audit_' . $case->id . '_' . time();

            $ch = curl_init('https://api.razorpay.com/v1/orders');
            curl_setopt($ch, CURLOPT_USERPWD, "$razorpayKeyId:$razorpaySecret");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'amount' => $amountInPaise,
                'currency' => config('razorpay.currency', 'INR'),
                'receipt' => $receipt,
                'description' => 'Stuck Trademark Audit Package - ' . $case->case_number,
                'notes' => [
                    'case_id' => (string) $case->id,
                    'case_number' => $case->case_number,
                    'payment_type' => 'stuck_trademark_audit',
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

            $orders = $request->session()->get('stuck_trademark_audit_orders', []);
            $orders[$order['id']] = [
                'case_id' => $case->id,
                'amount' => $amountInPaise,
                'original_amount' => $pricing['original_amount'],
                'payable_amount' => $pricing['payable_amount'],
                'discount_amount' => $pricing['discount_amount'],
                'coupon_label' => $pricing['coupon_label'],
            ];
            $request->session()->put('stuck_trademark_audit_orders', $orders);

            return response()->json([
                'status' => 'success',
                'order_id' => $order['id'],
                'amount' => $amountInPaise,
                'currency' => $order['currency'] ?? config('razorpay.currency', 'INR'),
                'key' => $razorpayKeyId,
                'user_email' => Auth::user()->email,
                'user_phone' => Auth::user()->phone ?? $case->phone,
                'user_name' => Auth::user()->name ?? $case->applicant_name,
                'description' => 'Audit Package - ' . $case->case_number,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create audit payment order: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function verifyAuditPaymentSignature(Request $request, StuckTrademarkCase $case, RazorpayPaymentVerifier $verifier)
    {
        $this->authorizeApplicant($case);

        if ($case->audit_payment_status === 'paid') {
            return response()->json([
                'status' => 'success',
                'message' => 'Audit package payment is already complete.',
                'redirect_url' => route('stuck-trademark.show', $case),
            ]);
        }

        $validated = $request->validate([
            'razorpay_payment_id' => 'required|string',
            'razorpay_order_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $orders = $request->session()->get('stuck_trademark_audit_orders', []);
        $order = $orders[$validated['razorpay_order_id']] ?? null;

        if (!$order || (int) ($order['case_id'] ?? 0) !== (int) $case->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This Razorpay order does not belong to the current recovery case.',
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

        $this->activateAuditPackage(
            $case,
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $order
        );

        unset($orders[$validated['razorpay_order_id']]);
        $request->session()->put('stuck_trademark_audit_orders', $orders);

        return response()->json([
            'status' => 'success',
            'message' => 'Audit payment verified successfully.',
            'redirect_url' => route('stuck-trademark.show', $case),
        ]);
    }

    private function activateAuditPackage(
        StuckTrademarkCase $case,
        ?string $orderId = null,
        ?string $paymentId = null,
        ?array $paymentDetails = null
    ): void
    {
        $from = $case->status;
        $updates = [
            'audit_payment_status' => 'paid',
            'audit_paid_at' => now(),
            'status' => StuckTrademarkWorkflow::AUDIT_IN_PROGRESS,
        ];

        if ($paymentDetails && Schema::hasColumn('stuck_trademark_cases', 'audit_original_fee')) {
            $updates['audit_original_fee'] = $paymentDetails['original_amount'] ?? $case->audit_fee;
        }

        if ($paymentDetails && Schema::hasColumn('stuck_trademark_cases', 'audit_paid_amount')) {
            $updates['audit_paid_amount'] = $paymentDetails['payable_amount'] ?? $case->audit_fee;
        }

        if ($paymentDetails && Schema::hasColumn('stuck_trademark_cases', 'audit_discount_amount')) {
            $updates['audit_discount_amount'] = $paymentDetails['discount_amount'] ?? 0;
        }

        if ($paymentDetails && Schema::hasColumn('stuck_trademark_cases', 'audit_coupon_label')) {
            $updates['audit_coupon_label'] = $paymentDetails['coupon_label'] ?? null;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('stuck_trademark_cases', 'audit_payment_reference')) {
            $updates['audit_payment_reference'] = $orderId;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('stuck_trademark_cases', 'audit_transaction_id')) {
            $updates['audit_transaction_id'] = $paymentId;
        }

        $case->update($updates);
        $this->logStatus($case, $from, StuckTrademarkWorkflow::AUDIT_PENDING, 'Audit package purchased', 'Applicant selected and paid for the audit package.');
        $this->logStatus($case, StuckTrademarkWorkflow::AUDIT_PENDING, $case->status, 'Audit in progress', 'Legal expert review has started for case status, documents, and registry issues.');
        $this->notifyApplicant($case, 'stuck_trademark_audit_started', 'Audit in progress', 'Your audit package is active. Our legal team is reviewing your trademark recovery case.');
    }

    private function ensureAuditPaymentBreakdown(StuckTrademarkCase $case): void
    {
        if ($case->audit_payment_status !== 'paid') {
            return;
        }

        $originalAmount = (float) ($case->audit_original_fee ?: $case->audit_fee);
        $paidAmount = $case->audit_paid_amount !== null ? (float) $case->audit_paid_amount : null;
        $discountAmount = $case->audit_discount_amount !== null ? (float) $case->audit_discount_amount : null;
        $couponLabel = $case->audit_coupon_label;

        if ($paidAmount === null) {
            $coupon = $couponLabel
                ? DiscountCoupon::where('code', $couponLabel)->first()
                : DiscountCoupon::autoApplyForPayment('audit_package_purchase', (int) $case->user_id);

            if ($coupon) {
                $discountAmount = $coupon->discountAmountFor($originalAmount);
                $paidAmount = $coupon->discountedAmountFor($originalAmount);
                $couponLabel = $coupon->code;
            } else {
                $discountAmount = $discountAmount ?? 0;
                $paidAmount = max($originalAmount - $discountAmount, 0);
            }
        } elseif ($discountAmount === null) {
            $discountAmount = max($originalAmount - $paidAmount, 0);
        }

        $updates = [];

        if (Schema::hasColumn('stuck_trademark_cases', 'audit_original_fee') && $case->audit_original_fee === null) {
            $updates['audit_original_fee'] = $originalAmount;
        }

        if (Schema::hasColumn('stuck_trademark_cases', 'audit_paid_amount') && $case->audit_paid_amount === null) {
            $updates['audit_paid_amount'] = round($paidAmount, 2);
        }

        if (Schema::hasColumn('stuck_trademark_cases', 'audit_discount_amount') && $case->audit_discount_amount === null) {
            $updates['audit_discount_amount'] = round($discountAmount, 2);
        }

        if (Schema::hasColumn('stuck_trademark_cases', 'audit_coupon_label') && $case->audit_coupon_label === null && $couponLabel) {
            $updates['audit_coupon_label'] = $couponLabel;
        }

        if ($updates !== []) {
            $case->forceFill($updates)->save();
            $case->refresh();
        }
    }

    public function viewAuditInvoice(StuckTrademarkCase $case)
    {
        $this->authorizeApplicant($case);

        abort_unless($case->audit_payment_status === 'paid', 404);
        $this->ensureAuditPaymentBreakdown($case);

        $issuedAt = $case->audit_paid_at ?? $case->updated_at ?? now();

        $pdf = \PDF::loadView('stuck-trademark.audit-invoice', [
            'case' => $case,
            'user' => $case->user,
            'invoiceNumber' => 'STR-INV-' . $issuedAt->format('Y') . '-' . $case->id,
            'issuedAt' => $issuedAt,
            'firmName' => config('app.name', 'Legal Bruz'),
            'firmEmail' => config('mail.from.address'),
        ])->setPaper('a4');

        return $pdf->stream('audit-invoice-' . $case->case_number . '.pdf');
    }

    public function approveExecution(StuckTrademarkCase $case)
    {
        $this->authorizeApplicant($case);

        if ($case->status !== StuckTrademarkWorkflow::AWAITING_APPROVAL) {
            return redirect()->back()->with('error', 'Execution can be approved only after audit delivery.');
        }

        if ((float) $case->execution_fee <= 0) {
            return redirect()->back()->with('error', 'Execution package fee is not configured yet.');
        }

        $from = $case->status;
        $case->update([
            'execution_payment_status' => 'pending',
            'status' => StuckTrademarkWorkflow::EXECUTION_PAYMENT_PENDING,
        ]);
        $this->logStatus($case, $from, $case->status, 'Execution approved by applicant', 'Applicant approved the recommended recovery execution package.');
        $this->notifyApplicant($case, 'stuck_trademark_execution_approved', 'Execution approval received', 'Your approval for the execution package has been recorded. The team will proceed with payment and execution instructions.');

        return redirect()->route('stuck-trademark.show', $case)->with('success', 'Execution package approval recorded. Payment instructions are now pending.');
    }

    public function createExecutionOrder(Request $request, StuckTrademarkCase $case)
    {
        $this->authorizeApplicant($case);

        if ($case->status !== StuckTrademarkWorkflow::EXECUTION_PAYMENT_PENDING || $case->execution_payment_status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Execution payment is available only after approving the execution package.',
            ], 422);
        }

        $pricing = $this->discountedPackagePricing(
            'execution_package_purchase',
            (float) $case->execution_fee,
            $request->integer('execution_discount_coupon_id') ?: null
        );
        $amount = $pricing['payable_amount'];

        if ($amount <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Execution package fee is not configured for this case.',
            ], 422);
        }

        try {
            $razorpayKeyId = config('razorpay.key_id');
            $razorpaySecret = config('razorpay.key_secret');

            if (!$razorpayKeyId || !$razorpaySecret) {
                throw new \Exception('Razorpay credentials are not configured.');
            }

            $amountInPaise = (int) round($amount * 100);
            $receipt = 'str_exec_' . $case->id . '_' . time();

            $ch = curl_init('https://api.razorpay.com/v1/orders');
            curl_setopt($ch, CURLOPT_USERPWD, "$razorpayKeyId:$razorpaySecret");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'amount' => $amountInPaise,
                'currency' => config('razorpay.currency', 'INR'),
                'receipt' => $receipt,
                'description' => 'Stuck Trademark Execution Package - ' . $case->case_number,
                'notes' => [
                    'case_id' => (string) $case->id,
                    'case_number' => $case->case_number,
                    'payment_type' => 'stuck_trademark_execution',
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

            $orders = $request->session()->get('stuck_trademark_execution_orders', []);
            $orders[$order['id']] = [
                'case_id' => $case->id,
                'amount' => $amountInPaise,
                'original_amount' => $pricing['original_amount'],
                'payable_amount' => $pricing['payable_amount'],
                'discount_amount' => $pricing['discount_amount'],
                'coupon_label' => $pricing['coupon_label'],
            ];
            $request->session()->put('stuck_trademark_execution_orders', $orders);

            return response()->json([
                'status' => 'success',
                'order_id' => $order['id'],
                'amount' => $amountInPaise,
                'currency' => $order['currency'] ?? config('razorpay.currency', 'INR'),
                'key' => $razorpayKeyId,
                'user_email' => Auth::user()->email,
                'user_phone' => Auth::user()->phone ?? $case->phone,
                'user_name' => Auth::user()->name ?? $case->applicant_name,
                'description' => 'Execution Package - ' . $case->case_number,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create execution payment order: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function verifyExecutionPaymentSignature(Request $request, StuckTrademarkCase $case, RazorpayPaymentVerifier $verifier)
    {
        $this->authorizeApplicant($case);

        if ($case->execution_payment_status === 'paid') {
            return response()->json([
                'status' => 'success',
                'message' => 'Execution payment is already complete.',
                'redirect_url' => route('stuck-trademark.show', $case),
            ]);
        }

        $validated = $request->validate([
            'razorpay_payment_id' => 'required|string',
            'razorpay_order_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $orders = $request->session()->get('stuck_trademark_execution_orders', []);
        $order = $orders[$validated['razorpay_order_id']] ?? null;

        if (!$order || (int) ($order['case_id'] ?? 0) !== (int) $case->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This Razorpay order does not belong to the current recovery case.',
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

        $from = $case->status;
        $updates = [
            'execution_payment_status' => 'paid',
            'execution_paid_at' => now(),
            'status' => StuckTrademarkWorkflow::EXECUTION_ACTIVE,
        ];

        if (Schema::hasColumn('stuck_trademark_cases', 'execution_original_fee')) {
            $updates['execution_original_fee'] = $order['original_amount'] ?? $case->execution_fee;
        }

        if (Schema::hasColumn('stuck_trademark_cases', 'execution_paid_amount')) {
            $updates['execution_paid_amount'] = $order['payable_amount'] ?? $case->execution_fee;
        }

        if (Schema::hasColumn('stuck_trademark_cases', 'execution_discount_amount')) {
            $updates['execution_discount_amount'] = $order['discount_amount'] ?? 0;
        }

        if (Schema::hasColumn('stuck_trademark_cases', 'execution_coupon_label')) {
            $updates['execution_coupon_label'] = $order['coupon_label'] ?? null;
        }

        $case->update($updates);

        $this->logStatus($case, $from, $case->status, 'Execution package payment completed', 'Applicant paid the approved execution package fee.');
        $this->notifyApplicant($case, 'stuck_trademark_execution_payment_paid', 'Execution payment received', 'Your execution package payment has been received. Recovery work is now active.');

        unset($orders[$validated['razorpay_order_id']]);
        $request->session()->put('stuck_trademark_execution_orders', $orders);

        return response()->json([
            'status' => 'success',
            'message' => 'Execution payment verified successfully.',
            'redirect_url' => route('stuck-trademark.show', $case),
        ]);
    }

    public function approveAuditReport(Request $request, StuckTrademarkCase $case)
    {
        $this->authorizeApplicant($case);

        if ($case->status !== StuckTrademarkWorkflow::AUDIT_REPORT_REVIEW) {
            return redirect()->back()->with('error', 'Audit report can be approved only while it is awaiting your review.');
        }

        abort_unless($case->audit_report_path, 404);

        $validated = $request->validate([
            'audit_report_client_note' => 'nullable|string|max:2000',
        ]);

        $from = $case->status;
        $updates = [
            'status' => StuckTrademarkWorkflow::AWAITING_APPROVAL,
        ];

        if (Schema::hasColumn('stuck_trademark_cases', 'audit_report_client_note')) {
            $updates['audit_report_client_note'] = $validated['audit_report_client_note'] ?? null;
        }

        if (Schema::hasColumn('stuck_trademark_cases', 'audit_report_approved_at')) {
            $updates['audit_report_approved_at'] = now();
        }

        if (Schema::hasColumn('stuck_trademark_cases', 'audit_report_reupload_requested_at')) {
            $updates['audit_report_reupload_requested_at'] = null;
        }

        $case->update($updates);

        $this->logStatus(
            $case,
            $from,
            $case->status,
            'Audit report approved by applicant',
            $validated['audit_report_client_note'] ?? 'Applicant approved the audit report and moved to execution package review.'
        );
        $this->notifyApplicant(
            $case,
            'stuck_trademark_audit_report_approved',
            'Audit report approved',
            'Your audit report approval has been recorded. Please review the execution package recommendation.'
        );
        $this->notifyAdmins(
            'Applicant approved audit report',
            ($case->case_number ?: 'Recovery case #' . $case->id) . ' is ready for execution package approval.'
        );

        return redirect()->route('stuck-trademark.show', $case)->with('success', 'Audit report approved. Please review the execution package recommendation.');
    }

    public function requestAuditReportReupload(Request $request, StuckTrademarkCase $case)
    {
        $this->authorizeApplicant($case);

        if ($case->status !== StuckTrademarkWorkflow::AUDIT_REPORT_REVIEW) {
            return redirect()->back()->with('error', 'Audit report changes can be requested only while it is awaiting your review.');
        }

        abort_unless($case->audit_report_path, 404);

        $validated = $request->validate([
            'audit_report_client_note' => 'nullable|string|max:2000',
        ]);

        $from = $case->status;
        $updates = [
            'status' => StuckTrademarkWorkflow::AUDIT_REPORT_REUPLOAD_REQUESTED,
        ];

        if (Schema::hasColumn('stuck_trademark_cases', 'audit_report_client_note')) {
            $updates['audit_report_client_note'] = $validated['audit_report_client_note'] ?? null;
        }

        if (Schema::hasColumn('stuck_trademark_cases', 'audit_report_reupload_requested_at')) {
            $updates['audit_report_reupload_requested_at'] = now();
        }

        if (Schema::hasColumn('stuck_trademark_cases', 'audit_report_approved_at')) {
            $updates['audit_report_approved_at'] = null;
        }

        $case->update($updates);

        $this->logStatus(
            $case,
            $from,
            $case->status,
            'Audit report reupload requested',
            $validated['audit_report_client_note'] ?? 'Applicant requested a corrected audit report upload.'
        );
        $this->notifyApplicant(
            $case,
            'stuck_trademark_audit_report_reupload_requested',
            'Audit report changes requested',
            'Your requested audit report changes have been sent to the legal team.'
        );
        $this->notifyAdmins(
            'Applicant requested audit report changes',
            ($case->case_number ?: 'Recovery case #' . $case->id) . " needs an updated audit report.\n\nRequest:\n" . ($validated['audit_report_client_note'] ?? 'No note provided.')
        );

        return redirect()->route('stuck-trademark.show', $case)->with('success', 'Audit report change request sent to the legal team.');
    }

    public function skipExecution(StuckTrademarkCase $case)
    {
        $this->authorizeApplicant($case);

        if ($case->status !== StuckTrademarkWorkflow::AWAITING_APPROVAL) {
            return redirect()->back()->with('error', 'Execution can be skipped only after audit delivery.');
        }

        $from = $case->status;
        $case->update([
            'execution_payment_status' => 'skipped',
            'status' => StuckTrademarkWorkflow::EXECUTION_ACTIVE,
        ]);
        $this->logStatus($case, $from, $case->status, 'Execution package skipped by applicant', 'Applicant skipped the recommended recovery execution package.');
        $this->notifyApplicant($case, 'stuck_trademark_execution_skipped', 'Execution package skipped', 'Your recovery execution package has been skipped. The case has moved to the next recovery step.');

        return redirect()->route('stuck-trademark.show', $case)->with('success', 'Execution package skipped. Your recovery case has moved to the next step.');
    }

    public function adminIndex(Request $request)
    {
        $cases = StuckTrademarkCase::with('user')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.trim((string) $request->string('search')).'%';
                $query->where(function ($query) use ($search) {
                    $query->where('case_number', 'like', $search)
                        ->orWhere('application_number', 'like', $search)
                        ->orWhere('applicant_name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('trademark_name', 'like', $search);
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.stuck-trademark.index', [
            'cases' => $cases,
            'statuses' => StuckTrademarkWorkflow::labels(),
        ]);
    }

    public function adminShow(StuckTrademarkCase $case)
    {
        return view('admin.stuck-trademark.show', [
            'case' => $case->load(['user', 'documents', 'statusLogs', 'executionUpdates', 'executionDocuments']),
            'statuses' => StuckTrademarkWorkflow::labels(),
        ]);
    }

    public function adminUpdate(Request $request, StuckTrademarkCase $case)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(StuckTrademarkWorkflow::labels()))],
            'assigned_expert_name' => 'nullable|string|max:255',
            'audit_summary' => 'nullable|string|max:5000',
            'risk_level' => 'nullable|in:low,medium,high,critical',
            'execution_recommendation' => 'nullable|string|max:5000',
            'execution_fee' => 'nullable|numeric|min:0|max:1000000',
            'execution_scope' => 'nullable|string|max:5000',
            'next_follow_up_at' => 'nullable|date',
            'note' => 'nullable|string|max:2000',
        ]);

        $from = $case->status;
        $updates = $validated;
        unset($updates['note']);

        if (filled($validated['next_follow_up_at'] ?? null)) {
            $updates['next_follow_up_at'] = Carbon::parse($validated['next_follow_up_at'], config('app.timezone', 'Europe/London'))->utc();
        }

        if ($validated['status'] === StuckTrademarkWorkflow::RESOLVED && !$case->resolved_at) {
            $updates['resolved_at'] = now();
        }

        if ($validated['status'] === StuckTrademarkWorkflow::CLOSED && !$case->closed_at) {
            $updates['closed_at'] = now();
        }

        $auditProtectedStatuses = [
            StuckTrademarkWorkflow::AUDIT_IN_PROGRESS,
            StuckTrademarkWorkflow::AUDIT_COMPLETED,
            StuckTrademarkWorkflow::AUDIT_REPORT_REVIEW,
            StuckTrademarkWorkflow::AUDIT_REPORT_REUPLOAD_REQUESTED,
            StuckTrademarkWorkflow::AWAITING_APPROVAL,
            StuckTrademarkWorkflow::EXECUTION_PAYMENT_PENDING,
            StuckTrademarkWorkflow::EXECUTION_ACTIVE,
            StuckTrademarkWorkflow::REGISTRY_FOLLOW_UP,
            StuckTrademarkWorkflow::MONITORING,
            StuckTrademarkWorkflow::ADDITIONAL_ACTION_REQUIRED,
            StuckTrademarkWorkflow::RESOLVED,
        ];
        if (in_array($validated['status'], $auditProtectedStatuses, true)
            && $case->audit_payment_status !== 'paid') {
            return redirect()
                ->route('admin.stuck-trademark.show', $case)
                ->with('error', 'This workflow stage cannot be activated until Razorpay confirms the audit payment as captured.');
        }

        $executionProtectedStatuses = [
            StuckTrademarkWorkflow::EXECUTION_ACTIVE,
            StuckTrademarkWorkflow::REGISTRY_FOLLOW_UP,
            StuckTrademarkWorkflow::MONITORING,
            StuckTrademarkWorkflow::ADDITIONAL_ACTION_REQUIRED,
            StuckTrademarkWorkflow::RESOLVED,
        ];
        if (in_array($validated['status'], $executionProtectedStatuses, true)
            && $case->execution_payment_status !== 'paid') {
            return redirect()
                ->route('admin.stuck-trademark.show', $case)
                ->with('error', 'Execution cannot be activated until Razorpay confirms the payment as captured.');
        }

        $case->update($updates);
        $this->logStatus($case, $from, $case->status, 'Admin updated recovery case', $validated['note'] ?: 'Recovery case workflow was updated by admin.', 'admin', Auth::guard('admin')->id());
        $this->notifyApplicant($case, 'stuck_trademark_status_updated', 'Recovery case updated', $validated['note'] ?: 'Your recovery case status is now: ' . $case->status_label . '.');

        return redirect()->route('admin.stuck-trademark.show', $case)->with('success', 'Recovery case updated.');
    }

    public function adminVerifyDocument(Request $request, StuckTrademarkDocument $document)
    {
        if (!$this->isVerifiableDocumentType($document->document_type)) {
            return redirect()
                ->back()
                ->with('error', 'This document type is not available for admin verification.');
        }

        $validated = $request->validate([
            'status' => 'required|in:verified,reupload_requested',
            'verification_notes' => 'required_if:status,reupload_requested|nullable|string|max:1500',
        ]);
        $verificationNotes = $validated['verification_notes'] ?? null;

        $document->update([
            'status' => $validated['status'],
            'verification_notes' => $verificationNotes,
            'verified_at' => $validated['status'] === 'verified' ? now() : null,
        ]);

        $case = $document->case;
        $from = $case->status;
        $toStatus = $validated['status'] === 'reupload_requested'
            ? StuckTrademarkWorkflow::REUPLOAD_REQUIRED
            : ($this->allCurrentDocumentsVerified($case) ? StuckTrademarkWorkflow::AUDIT_PENDING : StuckTrademarkWorkflow::DOCUMENTS_UNDER_VERIFICATION);

        $case->update(['status' => $toStatus]);

        if ($toStatus === StuckTrademarkWorkflow::AUDIT_PENDING) {
            $this->logStatus($case, $from, StuckTrademarkWorkflow::DOCUMENTS_VERIFIED, 'Documents verified', 'All uploaded recovery documents were verified by admin.', 'admin', Auth::guard('admin')->id());
            $from = StuckTrademarkWorkflow::DOCUMENTS_VERIFIED;
        }

        $this->logStatus(
            $case,
            $from,
            $case->status,
            $validated['status'] === 'verified'
                ? ($toStatus === StuckTrademarkWorkflow::AUDIT_PENDING ? 'Audit package available' : 'Document verified')
                : 'Document changes requested',
            $verificationNotes,
            'admin',
            Auth::guard('admin')->id()
        );
        $this->notifyApplicant(
            $case,
            'stuck_trademark_document_' . $validated['status'],
            $validated['status'] === 'verified' ? 'Document verified' : 'Document changes requested',
            $verificationNotes ?: ($validated['status'] === 'verified' ? 'A recovery case document has been verified.' : 'A recovery case document needs changes. Please reupload the corrected file from your dashboard.')
        );

        return redirect()->back()->with('success', $validated['status'] === 'verified' ? 'Document verified.' : 'Document change request sent to the applicant.');
    }

    public function adminBulkVerifyDocuments(Request $request, StuckTrademarkCase $case)
    {
        $validated = $request->validate([
            'document_ids' => 'required|array|min:1',
            'document_ids.*' => 'integer|exists:stuck_trademark_documents,id',
            'status' => 'required|in:verified,reupload_requested',
            'verification_notes' => 'required_if:status,reupload_requested|nullable|string|max:1500',
        ]);

        $documents = $case->documents()
            ->whereIn('id', $validated['document_ids'])
            ->get()
            ->filter(fn (StuckTrademarkDocument $document) => $this->isVerifiableDocumentType($document->document_type));

        if ($documents->isEmpty()) {
            return redirect()->back()->with('error', 'Please select at least one document that can be verified.');
        }

        $latestDocumentIdsByType = $case->documents()
            ->whereIn('document_type', $this->verifiableDocumentTypes())
            ->latest('id')
            ->get()
            ->unique('document_type')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $documents = $documents
            ->filter(fn (StuckTrademarkDocument $document) => in_array((int) $document->id, $latestDocumentIdsByType, true))
            ->filter(function (StuckTrademarkDocument $document) {
                $status = $document->status === 'rejected' ? 'reupload_requested' : $document->status;
                return !in_array($status, ['verified', 'reupload_requested'], true);
            })
            ->values();

        if ($documents->isEmpty()) {
            return redirect()->back()->with('error', 'Selected documents are already handled or archived.');
        }

        $verificationNotes = $validated['verification_notes'] ?? null;

        foreach ($documents as $document) {
            $document->update([
                'status' => $validated['status'],
                'verification_notes' => $verificationNotes,
                'verified_at' => $validated['status'] === 'verified' ? now() : null,
            ]);
        }

        $case->refresh();
        $from = $case->status;
        $toStatus = $validated['status'] === 'reupload_requested'
            ? StuckTrademarkWorkflow::REUPLOAD_REQUIRED
            : ($this->allCurrentDocumentsVerified($case) ? StuckTrademarkWorkflow::AUDIT_PENDING : StuckTrademarkWorkflow::DOCUMENTS_UNDER_VERIFICATION);

        $case->update(['status' => $toStatus]);

        if ($toStatus === StuckTrademarkWorkflow::AUDIT_PENDING) {
            $this->logStatus($case, $from, StuckTrademarkWorkflow::DOCUMENTS_VERIFIED, 'Documents verified', 'All uploaded recovery documents were verified by admin.', 'admin', Auth::guard('admin')->id());
            $from = StuckTrademarkWorkflow::DOCUMENTS_VERIFIED;
        }

        $count = $documents->count();
        $documentList = $documents
            ->map(function (StuckTrademarkDocument $document) {
                $documentType = $this->caseDocumentTypeLabels()[$document->document_type] ?? ucwords(str_replace('_', ' ', $document->document_type));

                return '- ' . $documentType . ': ' . $document->file_name;
            })
            ->implode("\n");
        $title = $validated['status'] === 'verified'
            ? ($toStatus === StuckTrademarkWorkflow::AUDIT_PENDING ? 'Audit package available' : 'Documents verified')
            : 'Document changes requested';
        $message = $validated['status'] === 'verified'
            ? $count . ' recovery document' . ($count === 1 ? ' was' : 's were') . " verified by admin.\n\nVerified documents:\n" . $documentList
            : "Selected recovery documents need changes. Please reupload the corrected files from your dashboard.\n\nDocuments requiring reupload:\n" . $documentList;

        if ($verificationNotes) {
            $message .= "\n\nAdmin note: " . $verificationNotes;
        }

        $this->logStatus($case, $from, $case->status, $title, $message, 'admin', Auth::guard('admin')->id());
        $this->notifyApplicant(
            $case,
            'stuck_trademark_documents_bulk_' . $validated['status'],
            $validated['status'] === 'verified' ? 'Documents verified' : 'Document changes requested',
            $message,
            route('stuck-trademark.show', $case),
            'Open Recovery Case'
        );

        return redirect()->back()->with('success', $validated['status'] === 'verified'
            ? $count . ' document' . ($count === 1 ? '' : 's') . ' verified.'
            : 'Reupload request sent for ' . $count . ' document' . ($count === 1 ? '' : 's') . '.');
    }

    public function adminUploadAuditReport(Request $request, StuckTrademarkCase $case)
    {
        if (!$this->canUploadAuditReport($case)) {
            return redirect()
                ->route('admin.stuck-trademark.show', $case)
                ->with('error', 'Audit report can be uploaded only after all current documents are verified and the audit package is paid.');
        }

        $validated = $request->validate([
            'audit_report' => 'required|file|mimes:pdf|max:15360',
            'audit_note' => 'nullable|string|max:5000',
            'risk_level' => 'nullable|in:low,medium,high,critical',
            'execution_recommendation' => 'nullable|string|max:5000',
            'execution_fee' => 'required|numeric|min:1|max:1000000',
            'optional_document_names' => 'nullable|array|max:10',
            'optional_document_names.*' => 'nullable|string|max:255',
            'optional_documents' => 'nullable|array|max:10',
            'optional_documents.*' => 'nullable|file|max:15360',
        ]);

        $file = $request->file('audit_report');
        $path = $file->store('stuck-trademark/audit-reports/' . $case->id, 'public');
        $from = $case->status;
        $optionalDocuments = $this->storeAuditOptionalDocuments($request, $case, $validated['optional_document_names'] ?? []);

        $updates = [
            'audit_report_path' => $path,
            'audit_report_name' => $file->getClientOriginalName(),
            'audit_summary' => $validated['audit_note'] ?? $case->audit_summary,
            'risk_level' => $validated['risk_level'] ?? null,
            'execution_recommendation' => $validated['execution_recommendation'] ?? null,
            'execution_fee' => $validated['execution_fee'],
            'status' => StuckTrademarkWorkflow::AUDIT_COMPLETED,
        ];

        if (Schema::hasColumn('stuck_trademark_cases', 'audit_report_client_note')) {
            $updates['audit_report_client_note'] = null;
        }

        if (Schema::hasColumn('stuck_trademark_cases', 'audit_report_approved_at')) {
            $updates['audit_report_approved_at'] = null;
        }

        if (Schema::hasColumn('stuck_trademark_cases', 'audit_report_reupload_requested_at')) {
            $updates['audit_report_reupload_requested_at'] = null;
        }

        $case->update($updates);
        $supportingDocumentMessage = $optionalDocuments->isNotEmpty()
            ? ' Supporting documents attached: ' . $optionalDocuments->pluck('file_name')->implode(', ') . '.'
            : '';
        $this->logStatus($case, $from, $case->status, 'Audit report created', ($validated['audit_note'] ?? 'Finalized audit report was uploaded for client download.') . $supportingDocumentMessage, 'admin', Auth::guard('admin')->id());

        $from = $case->status;
        $case->update([
            'status' => StuckTrademarkWorkflow::AUDIT_REPORT_REVIEW,
        ]);
        $this->logStatus($case, $from, $case->status, 'Audit report delivered', 'Legal audit report was uploaded and shared for applicant review.', 'admin', Auth::guard('admin')->id());
        $this->notifyApplicantWithAuditReport(
            $case->fresh(),
            $validated['audit_note'] ?? null,
            $optionalDocuments
        );

        return redirect()->route('admin.stuck-trademark.show', $case)->with('success', 'Audit report uploaded and case moved to client audit report review.');
    }

    public function viewDocument(StuckTrademarkDocument $document)
    {
        $case = $document->case;
        if (!Auth::guard('admin')->check()) {
            $this->authorizeApplicant($case);
        }

        return response()->file(Storage::disk('public')->path($document->file_path));
    }

    public function viewAuditReport(StuckTrademarkCase $case)
    {
        if (!Auth::guard('admin')->check()) {
            $this->authorizeApplicant($case);
        }

        abort_unless($case->audit_report_path && Storage::disk('public')->exists($case->audit_report_path), 404);

        return response()->file(Storage::disk('public')->path($case->audit_report_path));
    }

    private function hasIntakeDocuments(Request $request): bool
    {
        return $request->hasFile('status_screenshot')
            || $request->hasFile('notice_documents')
            || $request->hasFile('hearing_notice_documents');
    }

    private function storeIntakeDocuments(Request $request, StuckTrademarkCase $case): void
    {
        $uploads = [];

        if ($request->hasFile('status_screenshot')) {
            $uploads['registry_status_screenshot'][] = $request->file('status_screenshot');
        }

        if ($request->hasFile('notice_documents')) {
            foreach ($request->file('notice_documents') as $file) {
                $uploads['notice_received'][] = $file;
            }
        }

        if ($request->hasFile('hearing_notice_documents')) {
            foreach ($request->file('hearing_notice_documents') as $file) {
                $uploads['missed_hearing_notice'][] = $file;
            }
        }

        if ($uploads !== []) {
            $this->storeDocumentUploads($uploads, $case);
        }
    }

    private function storeUploadedDocuments(Request $request, StuckTrademarkCase $case, ?string $fallbackType = null, string $status = 'pending'): void
    {
        if (!$request->hasFile('documents')) {
            return;
        }

        foreach ($request->file('documents') as $file) {
            $path = $file->store('stuck-trademark/documents/' . $case->id, 'public');
            StuckTrademarkDocument::create([
                'case_id' => $case->id,
                'user_id' => $case->user_id,
                'document_type' => $fallbackType ?: 'initial_case_document',
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientOriginalExtension(),
                'file_size' => $file->getSize(),
                'status' => $status,
            ]);
        }
    }

    private function collectDocumentUploads(Request $request, ?string $fallbackType = null): array
    {
        $uploads = [];

        if ($request->hasFile('document_uploads')) {
            foreach ($request->file('document_uploads') as $documentType => $file) {
                if (!$file) {
                    continue;
                }

                $uploads[(string) $documentType][] = $file;
            }
        }

        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                if (!$file) {
                    continue;
                }

                $uploads[$fallbackType ?: 'initial_case_document'][] = $file;
            }
        }

        return $uploads;
    }

    private function storeDocumentUploads(array $uploads, StuckTrademarkCase $case, string $status = 'pending'): void
    {
        foreach ($uploads as $documentType => $files) {
            foreach ($files as $file) {
                $path = $file->store('stuck-trademark/documents/' . $case->id, 'public');

                StuckTrademarkDocument::create([
                    'case_id' => $case->id,
                    'user_id' => $case->user_id,
                    'document_type' => $documentType,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getClientOriginalExtension(),
                    'file_size' => $file->getSize(),
                    'status' => $status,
                ]);
            }
        }
    }

    private function storeAuditOptionalDocuments(Request $request, StuckTrademarkCase $case, array $documentNames)
    {
        return collect($request->file('optional_documents', []))
            ->filter()
            ->values()
            ->map(function ($file, int $index) use ($case, $documentNames) {
                $path = $file->store('stuck-trademark/audit-optional-documents/' . $case->id, 'public');
                $displayName = trim((string) ($documentNames[$index] ?? ''));

                return StuckTrademarkDocument::create([
                    'case_id' => $case->id,
                    'user_id' => $case->user_id,
                    'document_type' => 'audit_supporting_document',
                    'file_path' => $path,
                    'file_name' => $displayName !== '' ? $displayName : $file->getClientOriginalName(),
                    'file_type' => $file->getClientOriginalExtension(),
                    'file_size' => $file->getSize(),
                    'status' => 'verified',
                    'verified_at' => now(),
                ]);
            });
    }

    private function allCurrentDocumentsVerified(StuckTrademarkCase $case): bool
    {
        $case->loadMissing('documents');

        $documents = $case->documents
            ->sortByDesc('id')
            ->unique('document_type')
            ->filter(fn (StuckTrademarkDocument $document) => $this->isVerifiableDocumentType($document->document_type))
            ->values();

        if ($documents->isEmpty()) {
            return false;
        }

        return $documents->every(fn (StuckTrademarkDocument $document) => $document->status === 'verified');
    }

    private function isCaseDocumentType(?string $documentType): bool
    {
        return in_array($documentType, $this->caseDocumentTypes(), true);
    }

    private function isVerifiableDocumentType(?string $documentType): bool
    {
        return in_array($documentType, $this->verifiableDocumentTypes(), true);
    }

    private function verifiableDocumentTypes(): array
    {
        return array_merge($this->applicationDocumentTypes(), $this->caseDocumentTypes());
    }

    private function applicationDocumentTypes(): array
    {
        return [
            'registry_status_screenshot',
            'notice_received',
            'missed_hearing_notice',
        ];
    }

    private function caseDocumentTypes(): array
    {
        return [
            'tm_acknowledgment_receipt',
            'status_screenshot',
            'authorization_letter',
            'pan_aadhaar_gst',
            'previous_notices',
            'reply_copies',
            'hearing_notices',
            'user_affidavit',
            'previous_attorney_communication',
        ];
    }

    private function mandatoryCaseDocumentTypes(): array
    {
        return [
            'tm_acknowledgment_receipt',
            'status_screenshot',
            'authorization_letter',
            'pan_aadhaar_gst',
        ];
    }

    private function caseDocumentTypeLabels(): array
    {
        return [
            'registry_status_screenshot' => 'Registry Status Screenshot',
            'notice_received' => 'Notice Received',
            'missed_hearing_notice' => 'Missed Hearing Notice',
            'tm_acknowledgment_receipt' => 'TM acknowledgment receipt',
            'status_screenshot' => 'Status screenshot',
            'authorization_letter' => 'Authorization letter',
            'pan_aadhaar_gst' => 'PAN/Aadhaar/GST',
            'previous_notices' => 'Previous notices',
            'reply_copies' => 'Reply copies',
            'hearing_notices' => 'Hearing notices',
            'user_affidavit' => 'User affidavit',
            'previous_attorney_communication' => 'Previous attorney communication',
        ];
    }

    private function documentsReadyForAudit(StuckTrademarkCase $case): bool
    {
        return in_array($case->status, [
                StuckTrademarkWorkflow::AUDIT_PENDING,
                StuckTrademarkWorkflow::DOCUMENTS_VERIFIED,
                StuckTrademarkWorkflow::DOCUMENTS_UNDER_VERIFICATION,
            ], true)
            && $this->allCurrentDocumentsVerified($case);
    }

    private function canUploadAuditReport(StuckTrademarkCase $case): bool
    {
        return $this->allCurrentDocumentsVerified($case)
            && $case->audit_payment_status === 'paid'
            && in_array($case->status, [
                StuckTrademarkWorkflow::AUDIT_IN_PROGRESS,
                StuckTrademarkWorkflow::AUDIT_REPORT_REUPLOAD_REQUESTED,
            ], true);
    }

    private function authorizeApplicant(StuckTrademarkCase $case): void
    {
        abort_unless(Auth::id() === $case->user_id, 403);
    }

    private function logStatus(StuckTrademarkCase $case, ?string $from, string $to, string $title, ?string $message, string $actorType = 'user', ?int $actorId = null): void
    {
        StuckTrademarkStatusLog::create([
            'case_id' => $case->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actorType,
            'actor_id' => $actorId ?: Auth::id(),
            'title' => $title,
            'message' => $message,
        ]);
    }

    private function notifyApplicant(
        StuckTrademarkCase $case,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        string $actionText = 'Open Application Status'
    ): void
    {
        Notification::create([
            'user_id' => $case->user_id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => [
                'stuck_trademark_case_id' => $case->id,
                'case_number' => $case->case_number,
                'status' => $case->status,
            ],
        ]);

        try {
            Mail::to($case->email)->send(new EventNotification(
                $case->user,
                $title,
                $message,
                [],
                $actionUrl,
                $actionText
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notifyApplicantWithAuditReport(StuckTrademarkCase $case, ?string $auditNote = null, $supportingDocuments = null): void
    {
        $supportingDocuments = collect($supportingDocuments ?? []);
        $caseUrl = route('stuck-trademark.show', $case);
        $title = 'Audit report delivered';
        $message = "Your finalized stuck trademark audit report is ready and attached to this email.\n\n"
            . 'Please review it here: ' . $caseUrl . "\n\n"
            . 'You can approve the report or request a corrected upload from the recovery case page.';

        if (filled($auditNote)) {
            $message .= "\n\nAdmin note: " . $auditNote;
        }

        if (filled($case->risk_level)) {
            $message .= "\n\nRisk: " . ucfirst($case->risk_level);
        }

        if (filled($case->execution_recommendation)) {
            $message .= "\n\nProfessional recommendation: " . $case->execution_recommendation;
        }

        if ((float) $case->execution_fee > 0) {
            $message .= "\n\nExecution package fee: ₹" . number_format((float) $case->execution_fee, 2);
        }

        Notification::create([
            'user_id' => $case->user_id,
            'type' => 'stuck_trademark_audit_delivered',
            'title' => $title,
            'message' => 'Your finalized stuck trademark audit report is ready. Please review it and approve or request a corrected upload.',
            'data' => [
                'stuck_trademark_case_id' => $case->id,
                'case_number' => $case->case_number,
                'status' => $case->status,
                'action_url' => $caseUrl,
            ],
        ]);

        try {
            $attachmentPath = Storage::disk('public')->path($case->audit_report_path);
            $mailAttachments = [[
                'path' => $attachmentPath,
                'name' => $case->audit_report_name ?: 'stuck-trademark-audit-report-' . $case->case_number . '.pdf',
                'mime' => 'application/pdf',
            ]];

            foreach ($supportingDocuments as $document) {
                if (!Storage::disk('public')->exists($document->file_path)) {
                    continue;
                }

                $mailAttachments[] = [
                    'path' => Storage::disk('public')->path($document->file_path),
                    'name' => $document->file_name,
                ];
            }

            Mail::to($case->email)->send(new EventNotification(
                $case->user,
                $title,
                $message,
                $mailAttachments,
                $caseUrl,
                'Open Recovery Case'
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notifyAdmins(string $title, string $message): void
    {
        return;
    }
}
