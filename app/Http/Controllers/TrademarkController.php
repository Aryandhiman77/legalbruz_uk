<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Document;
use App\Models\Payment;
use App\Models\TrademarkPricing;
use App\Services\TrademarkWorkflowService;
use App\Services\UkPostcodeLookupService;
use App\Support\TrademarkWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrademarkController extends Controller
{
    private const MOBILE_NUMBER_REGEX = '/^(?:\+44|0)7[0-9]{9}$/';
    private const PINCODE_REGEX = '/^(?:GIR\s*0AA|[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2})$/i';

    /**
     * Show trademark type selection
     */
    public function showTypeSelection()
    {
        return view('trademark.type-selection');
    }

    /**
     * Show KYC checklist based on entity type
     */
    public function showKycChecklist($type)
    {
        $kycRequirements = [
            'individual' => [
                'Passport or Driving Licence',
                'Address Proof',
                'Email & Phone',
            ],
            'company' => [
                'Certificate of Incorporation',
                'Registered Office Address',
                'Authorized Signatory ID Proof',
            ]
        ];

        return view('trademark.kyc-checklist', [
            'type' => $type,
            'requirements' => $kycRequirements[$type] ?? []
        ]);
    }

    /**
     * Show application form
     */
    public function showApplicationForm($type)
    {
        return view('trademark.application-form', ['entity_type' => $type]);
    }

    /**
     * Store trademark application
     */
    public function storeApplication(Request $request, TrademarkWorkflowService $workflow, UkPostcodeLookupService $postcodeLookup)
    {
        $this->normalizeUkMobileInputs($request);

        $validated = $request->validate([
            'billing_name' => 'required|string|max:255',
            'billing_address' => 'required|string|max:500',
            'billing_email' => 'required|email|max:255',
            'billing_mobile' => ['required', 'string', 'max:30', 'regex:' . self::MOBILE_NUMBER_REGEX],
            'applicant_type' => ['required', Rule::in(array_keys(config('uk_site.applicant_types', [])))],
            'applicant_name' => 'required|string|max:255',
            'company_number' => 'nullable|required_if:applicant_type,limited_company,llp|string|max:40',
            'company_registration_country' => 'nullable|required_if:applicant_type,limited_company,llp|string|max:100',
            'applicant_address_line_1' => 'required|string|max:255',
            'applicant_address_line_2' => 'nullable|string|max:255',
            'applicant_postcode' => ['required', 'string', 'max:10', 'regex:' . self::PINCODE_REGEX],
            'applicant_nation' => 'required|in:England,Scotland,Wales,Northern Ireland',
            'applicant_region' => 'nullable|string|max:255',
            'applicant_town_city' => 'required|string|max:255',
            'applicant_country' => 'required|in:United Kingdom',
            'uk_address_for_service' => 'required|string|max:1000',
            'applicant_phone' => ['required', 'string', 'max:30', 'regex:' . self::MOBILE_NUMBER_REGEX],
            'applicant_email' => 'required|email|max:255',
            'authorised_person_name' => 'required|string|max:255',
            'authorised_person_position' => ['required', 'string', Rule::in(array_keys(config('uk_site.authorised_person_positions', [])))],
            'authorised_person_email' => 'required|email|max:255',
            'authorised_person_phone' => ['required', 'string', 'max:30', 'regex:' . self::MOBILE_NUMBER_REGEX],
            'authority_confirmed' => 'accepted',
            'joint_applicants' => 'required|in:yes,no',
            'additional_applicant_type' => 'nullable|required_if:joint_applicants,yes|in:individual,limited_company,llp,partnership,charity,other',
            'additional_applicant_name' => 'nullable|required_if:joint_applicants,yes|string|max:255',
            'additional_company_number' => 'nullable|string|max:40',
            'additional_applicant_address' => 'nullable|required_if:joint_applicants,yes|string|max:500',
            'additional_applicant_email' => 'nullable|required_if:joint_applicants,yes|email|max:255',
            'additional_applicant_phone' => ['nullable', 'required_if:joint_applicants,yes', 'string', 'max:30', 'regex:' . self::MOBILE_NUMBER_REGEX],
            'trademark_type' => 'required|in:word,logo,combined,other',
            'mark_brand' => 'required|string|max:255',
            'trademark_language' => 'required|string|max:100',
            'trademark_translation' => 'nullable|string|max:500',
            'trademark_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'business_activities' => 'required|string|max:3000',
            'currently_in_use' => 'required|in:yes,no',
            'first_use_date' => 'nullable|date|before_or_equal:today',
            'supporting_evidence' => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:5120',
            'proposed_classes' => 'nullable|string|max:500',
            'special_limitations' => 'nullable|string|max:1000',
            'application_route' => 'required|in:standard,right_start',
            'earlier_foreign_application' => 'required|in:yes,no',
            'priority_country' => 'nullable|required_if:earlier_foreign_application,yes|string|max:100',
            'priority_application_number' => 'nullable|required_if:earlier_foreign_application,yes|string|max:100',
            'priority_filing_date' => 'nullable|required_if:earlier_foreign_application,yes|date|before_or_equal:today',
            'priority_earlier_applicant' => 'nullable|required_if:earlier_foreign_application,yes|string|max:255',
            'priority_document' => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:5120',
        ], $this->validationMessages());

        $this->hydrateUkLocations($validated, $postcodeLookup);

        $logoPath = $request->hasFile('trademark_image')
            ? $request->file('trademark_image')->store('logos', 'public')
            : null;
        $supportingEvidencePath = $request->hasFile('supporting_evidence')
            ? $request->file('supporting_evidence')->store('supporting-evidence', 'public')
            : null;
        $priorityDocumentPath = $request->hasFile('priority_document')
            ? $request->file('priority_document')->store('priority-documents', 'public')
            : null;
        $priorityDetails = $validated['earlier_foreign_application'] === 'yes' ? [
            'priority_claim_required' => true,
            'priority_country' => $validated['priority_country'],
            'priority_application_number' => $validated['priority_application_number'],
            'priority_filing_date' => $validated['priority_filing_date'],
            'earlier_applicant' => $validated['priority_earlier_applicant'],
            'priority_document' => $priorityDocumentPath,
            'priority_deadline' => null,
            'approved_by_admin' => false,
        ] : ['priority_claim_required' => false];
        $applicantFullAddress = implode(', ', array_filter([
            $validated['applicant_address_line_1'],
            $validated['applicant_address_line_2'] ?? null,
            $validated['applicant_town_city'],
            $validated['applicant_region'] ?? null,
            $validated['applicant_postcode'],
            $validated['applicant_country'],
        ]));

        $application = Application::create([
            'user_id' => Auth::id(),
            'type' => 'trademark',
            'entity_type' => $validated['applicant_type'] === 'individual' ? 'individual' : 'company',
            'applicant_name' => $validated['applicant_name'],
            'phone' => $validated['applicant_phone'],
            'email' => $validated['applicant_email'],
            'brand_name' => $validated['mark_brand'],
            'logo_path' => $logoPath,
            'description' => $validated['business_activities'],
            'industry' => null,
            'usage_type' => 'uk',
            'first_use_date' => $validated['currently_in_use'] === 'yes' ? ($validated['first_use_date'] ?? null) : null,
            'currently_selling' => $validated['currently_in_use'] === 'yes',
            'address' => $applicantFullAddress,
            'goods_services' => $validated['business_activities'],
            'usage' => $validated['currently_in_use'] === 'yes' ? 'used' : 'proposed',
            'members_details' => [
                'billing_details' => [
                    'billing_name' => $validated['billing_name'],
                    'billing_email' => $validated['billing_email'],
                    'billing_phone' => $validated['billing_mobile'],
                    'billing_address' => trim($validated['billing_address']),
                    'invoice_number' => null,
                    'professional_fee' => null,
                    'ukipo_official_fee' => null,
                    'total_paid' => null,
                    'payment_status' => 'Payment pending',
                ],
                'applicant_details' => [
                    'applicant_type' => $validated['applicant_type'],
                    'legal_name' => $validated['applicant_name'],
                    'company_registration_number' => $validated['company_number'] ?? null,
                    'company_registration_country' => $validated['company_registration_country'] ?? null,
                    'address_line_1' => $validated['applicant_address_line_1'],
                    'address_line_2' => $validated['applicant_address_line_2'] ?? null,
                    'town_city' => $validated['applicant_town_city'],
                    'county_or_region' => $validated['applicant_region'] ?? null,
                    'nation' => $validated['applicant_nation'],
                    'postcode' => $validated['applicant_postcode'],
                    'country' => $validated['applicant_country'],
                    'email' => $validated['applicant_email'],
                    'phone' => $validated['applicant_phone'],
                    'uk_address_for_service' => $validated['uk_address_for_service'],
                ],
                'authorised_person' => [
                    'full_name' => $validated['authorised_person_name'],
                    'position_or_capacity' => $validated['authorised_person_position'],
                    'email' => $validated['authorised_person_email'],
                    'phone' => $validated['authorised_person_phone'],
                    'authority_confirmed' => true,
                    'final_application_approved' => false,
                    'approval_date' => null,
                ],
                'joint_applicants' => $validated['joint_applicants'] === 'yes',
                'additional_applicants' => $validated['joint_applicants'] === 'yes' ? [[
                    'applicant_type' => $validated['additional_applicant_type'],
                    'legal_name' => $validated['additional_applicant_name'],
                    'company_registration_number' => $validated['additional_company_number'] ?? null,
                    'address' => $validated['additional_applicant_address'],
                    'email' => $validated['additional_applicant_email'],
                    'phone' => $validated['additional_applicant_phone'],
                ]] : [],
                'trademark_details' => [
                    'mark_type' => $validated['trademark_type'],
                    'trade_mark_wording' => $validated['mark_brand'],
                    'logo_mark_file' => $logoPath,
                    'language' => $validated['trademark_language'],
                    'translation' => $validated['trademark_translation'] ?? null,
                    'business_activities' => $validated['business_activities'],
                    'currently_in_use' => $validated['currently_in_use'] === 'yes',
                    'first_use_date' => $validated['currently_in_use'] === 'yes' ? ($validated['first_use_date'] ?? null) : null,
                    'supporting_evidence' => $supportingEvidencePath,
                    'proposed_classes' => $validated['proposed_classes'] ?? null,
                    'final_approved_classes' => null,
                    'final_goods_and_services_specification' => null,
                    'special_limitations' => $validated['special_limitations'] ?? null,
                    'application_route' => $validated['application_route'],
                ],
                'priority_details' => $priorityDetails,
            ],
            'workflow_meta' => [
                'application_route' => $validated['application_route'],
                'priority' => $priorityDetails,
                'client_approval' => [
                    'applicant_approved' => false,
                    'mark_approved' => false,
                    'classes_and_specification_approved' => false,
                    'genuine_use_or_intention_confirmed' => false,
                    'filing_authority_confirmed' => false,
                ],
                'ukipo' => [
                    'application_number' => null,
                    'filing_date' => null,
                    'examination_deadline' => null,
                    'publication_date' => null,
                    'opposition_deadline' => null,
                    'registration_number' => null,
                    'renewal_date' => null,
                ],
            ],
            // A newly completed intake is still awaiting its first payment.
            // PAYMENT_PENDING is a legacy alias for the *final* payment stage.
            'status' => TrademarkWorkflow::DRAFT,
            'service_status' => TrademarkWorkflow::DRAFT,
        ]);

        $workflow->initialize($application);
        $workflow->notifyAdminsOfClientAction(
            $application->loadMissing('user'),
            'New UK trade mark application submitted',
            'The client completed and submitted the UK trade mark application form. The first 50% payment is still pending.'
        );

        return redirect()->route('payment.show', $application->id)
            ->with('success', 'Application created. Please complete 50% payment.');
    }

    /**
     * Show payment page with requirements
     */
    public function showPayment($applicationId)
    {
        $application = Application::findOrFail($applicationId);

        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        $payment = $application->payments()->where('status', 'completed')->first();
        if ($payment) {
            return redirect()->route('trademark.status', $application->id)
                ->with('info', 'Payment already completed. Your application is with the admin team.');
        }

        $totalAmount = TrademarkPricing::amountForApplicantType($application->entity_type);
        $amount = round($totalAmount * 0.5);

        return view('trademark.payment', [
            'application' => $application,
            'amount' => $amount,
            'totalAmount' => $totalAmount,
        ]);
    }

    /**
     * Show detailed application form
     */
    public function showDetailedForm($applicationId)
    {
        $application = Application::findOrFail($applicationId);

        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        return redirect()->route('trademark.status', $application->id)
            ->with('error', 'Application editing is disabled after payment.');
    }

    /**
     * Store detailed form
     */
    public function storeDetailedForm(Request $request, $applicationId, TrademarkWorkflowService $workflow)
    {
        $application = Application::findOrFail($applicationId);

        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        return redirect()->route('trademark.status', $application->id)
            ->with('error', 'Application editing is disabled after payment.');
    }

    /**
     * Show document upload page
     */
    public function showDocumentUpload($applicationId)
    {
        $application = Application::findOrFail($applicationId);

        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        return redirect(route('trademark.status', ['id' => $application->id, 'stage_action' => 1]) . '#stage-action');
    }

    /**
     * Store uploaded documents
     */
    public function storeDocuments(Request $request, $applicationId, TrademarkWorkflowService $workflow)
    {
        $application = Application::findOrFail($applicationId);

        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'documents.*' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $uploadedDocumentCount = 0;

        foreach ($request->all() as $key => $value) {
            if ($request->hasFile($key) && $key !== '_token') {
                $file = $request->file($key);
                $path = $file->store('documents/' . $application->id, 'public');

                Document::create([
                    'application_id' => $application->id,
                    'user_id' => Auth::id(),
                    'document_type' => $key,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getClientOriginalExtension(),
                    'file_size' => $file->getSize(),
                    'status' => 'pending',
                ]);
                $uploadedDocumentCount++;
            }
        }

        $workflow->refreshOnboardingStatus($application);

        if ($uploadedDocumentCount > 0) {
            $workflow->notifyAdminsOfClientAction(
                $application->loadMissing('user'),
                'Applicant submitted application documents',
                $uploadedDocumentCount . ' document(s) were uploaded by the client for review.'
            );
        }

        return redirect()->route('trademark.status', $application->id)
            ->with('success', 'Documents uploaded successfully.');
    }

    /**
     * Show document download page
     */
    public function showDocumentDownload($applicationId)
    {
        $application = Application::findOrFail($applicationId);

        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        return view('trademark.document-download', ['applicationId' => $applicationId]);
    }

    /**
     * Show application status
     */
    public function showStatus($applicationId)
    {
        $application = Application::with($this->applicationRelations())->findOrFail($applicationId);

        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        $application = $this->normalizeApplicationRelations($application);

        return view('trademark.status', ['application' => $application]);
    }

    /**
     * Show previously submitted application data and documents for a workflow stage.
     */
    public function showStageDetails($applicationId, string $stage)
    {
        $application = Application::with($this->applicationRelations())->findOrFail($applicationId);

        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        $application = $this->normalizeApplicationRelations($application);

        return view('trademark.stage-details', [
            'application' => $application,
            'stage' => $stage,
        ]);
    }

    /**
     * View stored trademark image
     */
    public function viewTrademarkImage(Request $request, $applicationId)
    {
        $application = Application::findOrFail($applicationId);

        if (!$this->canViewApplicationFile($application)) {
            abort(403);
        }

        $imagePath = $this->resolvePublicFilePath([
            $this->decodedFileQuery($request),
            data_get($application->members_details, 'trademark_details.logo_mark_file'),
            data_get($application->members_details, 'trademark_details.image_of_trademark'),
            $application->logo_path,
        ], ['logos']);

        if (!$imagePath) {
            abort(404, 'Trademark image file is missing from storage. Please re-upload the trademark image from the application details form.');
        }

        return response()->file($imagePath);
    }

    /**
     * View stored proof of use file
     */
    public function viewProofOfUse(Request $request, $applicationId)
    {
        $application = Application::findOrFail($applicationId);

        if (!$this->canViewApplicationFile($application)) {
            abort(403);
        }

        $proofOfUsePath = $this->resolvePublicFilePath([
            $this->decodedFileQuery($request),
            data_get($application->members_details, 'trademark_details.supporting_evidence'),
            data_get($application->members_details, 'priority_details.priority_document'),
            data_get($application->members_details, 'trademark_details.proof_of_use_of_trademark'),
        ], ['supporting-evidence', 'priority-documents', 'proof-of-use']);

        if (!$proofOfUsePath) {
            abort(404, 'Proof of use file not found.');
        }

        return response()->file($proofOfUsePath);
    }

    private function canViewApplicationFile(Application $application): bool
    {
        if (Auth::guard('admin')->check()) {
            return true;
        }

        return Auth::check() && $application->user_id === Auth::id();
    }

    private function resolvePublicFilePath(array $paths, array $fallbackDirectories = []): ?string
    {
        foreach ($paths as $path) {
            if (!is_string($path) || trim($path) === '') {
                continue;
            }

            $absolutePath = $this->resolveAbsolutePublicFilePath($path);

            if ($absolutePath) {
                return $absolutePath;
            }

            $normalizedPath = $this->normalizePublicStoragePath($path);

            if ($normalizedPath && Storage::disk('public')->exists($normalizedPath)) {
                return Storage::disk('public')->path($normalizedPath);
            }

            $publicPath = $this->resolvePublicWebFilePath($normalizedPath);

            if ($publicPath) {
                return $publicPath;
            }

            $fallbackPath = $this->resolveFallbackPublicStoragePath($normalizedPath, $fallbackDirectories);

            if ($fallbackPath) {
                return $fallbackPath;
            }
        }

        return null;
    }

    private function decodedFileQuery(Request $request): ?string
    {
        $encodedPath = $request->query('file');

        if (!is_string($encodedPath) || $encodedPath === '') {
            return null;
        }

        $decodedPath = base64_decode($encodedPath, true);

        return is_string($decodedPath) && $decodedPath !== '' ? $decodedPath : null;
    }

    private function resolveAbsolutePublicFilePath(string $path): ?string
    {
        $urlPath = parse_url(trim($path), PHP_URL_PATH);
        $path = rawurldecode($urlPath ?: $path);

        if (!str_starts_with($path, '/')) {
            return null;
        }

        $realPath = realpath($path);
        $publicRoot = realpath(Storage::disk('public')->path(''));

        if (!$realPath || !$publicRoot) {
            return null;
        }

        $publicRoot = rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($realPath, $publicRoot) ? $realPath : null;
    }

    private function normalizePublicStoragePath(string $path): string
    {
        $urlPath = parse_url(trim($path), PHP_URL_PATH);
        $path = $urlPath ?: $path;
        $path = rawurldecode(str_replace('\\', '/', $path));
        $path = preg_replace('#/+#', '/', $path);
        $path = ltrim($path, '/');

        foreach ([
            'storage/app/public/',
            'app/public/',
            'public/storage/',
            'storage/',
        ] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return substr($path, strlen($prefix));
            }
        }

        return $path;
    }

    private function resolvePublicWebFilePath(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        $realPath = realpath(public_path($path));
        $publicRoot = realpath(public_path());

        if (!$realPath || !$publicRoot || !is_file($realPath)) {
            return null;
        }

        $publicRoot = rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($realPath, $publicRoot) ? $realPath : null;
    }

    private function resolveFallbackPublicStoragePath(string $path, array $directories): ?string
    {
        $filename = basename($path);

        if ($filename === '' || $filename === '.' || $filename === '..') {
            return null;
        }

        foreach ($directories as $directory) {
            $candidate = trim($directory, '/') . '/' . $filename;

            if (Storage::disk('public')->exists($candidate)) {
                return Storage::disk('public')->path($candidate);
            }
        }

        return null;
    }

    /**
     * Generate and download Affidavit
     */
    public function downloadAffidavit($applicationId)
    {
        $application = Application::with('user')->findOrFail($applicationId);

        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        $html = $this->generateAffidavitHTML($application);

        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="Affidavit_' . $application->id . '_' . date('Ymd') . '.html"');
    }

    /**
     * Generate and download Power of Attorney (POA)
     */
    public function downloadPOA($applicationId)
    {
        $application = Application::with('user')->findOrFail($applicationId);

        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        $html = $this->generatePOAHTML($application);

        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="POA_' . $application->id . '_' . date('Ymd') . '.html"');
    }

    /**
     * Generate Affidavit HTML
     */
    private function generateAffidavitHTML($application)
    {
        $user = $application->user;
        $date = now()->format('d.m.Y');
        $firstUseDate = $application->first_use_date ?? 'N/A';

        return <<<'HTML'
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; margin: 40px; }
                    .header { text-align: center; font-weight: bold; margin-bottom: 30px; }
                    .title { font-size: 16px; font-weight: bold; text-align: center; margin: 20px 0; }
                    .content { text-align: justify; margin: 20px 0; }
                    .signature-section { margin-top: 60px; }
                    .signature-line { margin-top: 40px; display: inline-block; width: 250px; border-top: 1px solid black; text-align: center; }
                    .box { border: 1px solid black; padding: 20px; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>AFFIDAVIT</h2>
                </div>

                <div class="box">
                    <p><strong>AFFIDAVIT FOR TRADEMARK REGISTRATION</strong></p>

                    <div class="content">
HTML;

        $html .= '<p>I, <strong>' . htmlspecialchars($user->name) . '</strong>, Son/Daughter/Wife of ______________, ';
        $html .= 'resident of ______________, do hereby solemnly affirm and declare as follows:</p>';

        $html .= '<p><strong>1.</strong> That I am the applicant for Trademark Registration bearing ';
        $html .= 'Application No. <strong>' . htmlspecialchars($application->id) . '</strong>.</p>';

        $html .= '<p><strong>2.</strong> That the Brand/Trademark proposed to be registered is ';
        $html .= '<strong>"' . htmlspecialchars($application->brand_name) . '"</strong>.</p>';

        $html .= <<<'HTML'
                        <p><strong>3.</strong> That I have the rights to use this trademark and am the true
                        owner of the same.</p>

                        <p><strong>4.</strong> That the information provided in the application is true
                        and correct to the best of my knowledge and belief.</p>

                        <p><strong>5.</strong> That I shall use the said mark in connection with the goods/services
                        specified in the application.</p>

                        <p><strong>6.</strong> That no false information has been furnished in this application.</p>

                        <p><strong>7.</strong> That the Trademark has been first used in connection with the
                        goods/services on <strong>
HTML;

        $html .= htmlspecialchars($firstUseDate) . '</strong>.</p>';

        $html .= <<<'HTML'

                        <p>I solemnly declare that the contents of this Affidavit are true to the best of
                        my knowledge and belief. I am well acquainted with the facts stated herein.
                        I have not concealed any material fact.</p>
                    </div>
                </div>

                <div class="signature-section">
                    <p><strong>Affiant's Name:</strong>
HTML;

        $html .= htmlspecialchars($user->name) . '</p>';
        $html .= '<p><strong>Date:</strong> ' . htmlspecialchars($date) . '</p>';

        $html .= <<<'HTML'
                    <p><strong>Place:</strong> ____________________</p>

                    <div class="signature-line">
                        Signature of Affiant
                    </div>
                </div>

                <div style="text-align: center; margin-top: 60px; border-top: 1px solid black; padding-top: 20px;">
                    <p><strong>BEFORE ME:</strong></p>
                    <div class="signature-line">
                        Notary / First Class Magistrate
                    </div>
                </div>
            </body>
            </html>
HTML;

        return $html;
    }

    /**
     * Generate POA HTML
     */
    private function generatePOAHTML($application)
    {
        $user = $application->user;
        $date = now()->format('d.m.Y');

        $html = <<<'HTML'
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; margin: 40px; }
                    .header { text-align: center; font-weight: bold; margin-bottom: 30px; }
                    .title { font-size: 16px; font-weight: bold; text-align: center; margin: 20px 0; }
                    .content { text-align: justify; margin: 20px 0; }
                    .signature-section { margin-top: 60px; }
                    .signature-line { margin-top: 40px; display: inline-block; width: 250px; border-top: 1px solid black; text-align: center; }
                    .box { border: 1px solid black; padding: 20px; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>POWER OF ATTORNEY</h2>
                </div>

                <div class="box">
                    <p><strong>POWER OF ATTORNEY FOR TRADEMARK REGISTRATION AND REPRESENTATION</strong></p>

                    <div class="content">
HTML;

        $html .= '<p>I, <strong>' . htmlspecialchars($user->name) . '</strong>, resident of ______________, ';
        $html .= 'do hereby authorise and appoint <strong>Legal Bruz Ltd.</strong>, ';
        $html .= 'at the address stated in the engagement letter, ';
        $html .= 'to act as my Attorney in the matter of registration of Trademark bearing ';
        $html .= 'Application No. <strong>' . htmlspecialchars($application->id) . '</strong>.</p>';

        $html .= <<<'HTML'

                        <p><strong>1. SCOPE OF AUTHORITY:</strong></p>
                        <p>I hereby authorize my said Attorney to:</p>
                        <ul>
                            <li>File the Trademark application with appropriate authorities</li>
                            <li>Appear in proceedings before the Trademark Registry</li>
                            <li>Make submissions and arguments on my behalf</li>
                            <li>Reply to all official communications</li>
                            <li>Execute necessary documents and affidavits</li>
                            <li>Conduct negotiations and correspondence</li>
                            <li>Receive all official letters and certificates</li>
                            <li>Withdraw or amend the application if necessary</li>
                            <li>Perform all acts necessary for the prosecution of the application</li>
                        </ul>

                        <p><strong>2. REPRESENTATION:</strong></p>
                        <p>The said Attorney is hereby authorized to represent me in all matters related
                        to Trademark Registration (Application No.
HTML;

        $html .= htmlspecialchars($application->id) . ') for the brand ';
        $html .= '<strong>"' . htmlspecialchars($application->brand_name) . '"</strong>.</p>';

        $html .= <<<'HTML'

                        <p><strong>3. RATIFICATION:</strong></p>
                        <p>I hereby ratify and confirm all acts and deeds done by my said Attorney
                        in relation to the above matter.</p>

                        <p><strong>4. REVOCATION:</strong></p>
                        <p>All previous authorizations, if any, in respect of this matter are hereby
                        revoked and superseded by this Power of Attorney.</p>

                        <p><strong>5. VALIDITY:</strong></p>
                        <p>This Power of Attorney shall remain valid until the matter is finally
                        disposed of or until revoked by me in writing.</p>
                    </div>
                </div>

                <div class="signature-section">
                    <p><strong>Constituting Attorney Name:</strong>
HTML;

        $html .= htmlspecialchars($user->name) . '</p>';
        $html .= '<p><strong>Date:</strong> ' . htmlspecialchars($date) . '</p>';

        $html .= <<<'HTML'
                    <p><strong>Place:</strong> ____________________</p>

                    <div class="signature-line">
                        Signature of Constituting Attorney
                    </div>
                </div>

                <div style="text-align: center; margin-top: 60px; border-top: 1px solid black; padding-top: 20px;">
                    <p><strong>BEFORE ME:</strong></p>
                    <div class="signature-line">
                        Notary / First Class Magistrate
                    </div>
                </div>
            </body>
            </html>
HTML;

        return $html;
    }

    private function applicationRelations(): array
    {
        $relations = ['documents', 'payments', 'user'];

        if (Schema::hasTable('application_tasks')) {
            $relations[] = 'tasks';
        }

        if (Schema::hasTable('draft_versions')) {
            $relations[] = 'draftVersions';
        }

        if (Schema::hasTable('application_status_logs')) {
            $relations[] = 'statusLogs';
        }

        return $relations;
    }

    private function validationMessages(): array
    {
        return [
            'billing_mobile.regex' => 'Enter a valid UK mobile number beginning with 07 or +44 7.',
            'applicant_phone.regex' => 'Enter a valid UK mobile number beginning with 07 or +44 7.',
            'authorised_person_phone.regex' => 'Enter a valid UK mobile number beginning with 07 or +44 7.',
            'additional_applicant_phone.regex' => 'Enter a valid UK mobile number beginning with 07 or +44 7.',
            'applicant_postcode.regex' => 'Enter a valid UK postcode.',
            'first_use_date.before_or_equal' => 'The first-use date cannot be in the future.',
            'priority_filing_date.before_or_equal' => 'The priority filing date cannot be in the future.',
            'authority_confirmed.accepted' => 'Confirm that the authorised person has authority to act for the applicant.',
        ];
    }

    private function normalizeUkMobileInputs(Request $request): void
    {
        $normalized = [];

        foreach (['billing_mobile', 'applicant_phone', 'authorised_person_phone', 'additional_applicant_phone'] as $field) {
            $mobile = preg_replace('/[\s()-]+/', '', (string) $request->input($field));

            if (str_starts_with($mobile, '07')) {
                $mobile = '+44' . substr($mobile, 1);
            }

            $normalized[$field] = $mobile;
        }

        $request->merge($normalized);
    }

    private function hydrateUkLocations(array &$validated, UkPostcodeLookupService $postcodeLookup): void
    {
        $location = $postcodeLookup->lookup((string) $validated['applicant_postcode']);

        if (($location['status'] ?? 'error') !== 'success') {
            throw ValidationException::withMessages([
                'applicant_postcode' => $location['message'] ?? 'Enter a valid UK postcode.',
            ]);
        }

        $validated['applicant_postcode'] = $location['postcode'];
        $validated['applicant_nation'] = $location['nation'];
        $validated['applicant_region'] = $location['region'];
        $validated['applicant_town_city'] = $location['town_city'];
        $validated['applicant_country'] = 'United Kingdom';
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
