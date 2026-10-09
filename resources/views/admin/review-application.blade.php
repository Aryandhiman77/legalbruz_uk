@extends('layouts.app')

@section('head')
    @php
        $viteManifestPath = public_path('build/manifest.json');
        $viteManifest = is_file($viteManifestPath)
            ? json_decode((string) file_get_contents($viteManifestPath), true)
            : [];
        $hasPdfEditorBundle = is_file(public_path('hot'))
            || isset($viteManifest['resources/js/pdf-editor.js']);
    @endphp

    @if ($hasPdfEditorBundle)
        @vite('resources/js/pdf-editor.js')
    @else
        {{-- Keep the admin review page available when production assets have not
             been rebuilt yet. The next normal npm build switches back to the
             locally hosted bundle automatically. --}}
        <script type="module">
            import * as pdfjsLib from 'https://cdn.jsdelivr.net/npm/pdfjs-dist@5.4.149/build/pdf.min.mjs';

            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@5.4.149/build/pdf.worker.min.mjs';
            window.pdfjsLib = pdfjsLib;
        </script>
    @endif
@endsection

@section('content')
    @php
        $workflow = \App\Support\TrademarkWorkflow::class;
        $displayTimezone = config('app.timezone', 'Europe/London');
        $formatDateTime = fn ($timestamp, string $format = 'd M Y, h:i A T') => $timestamp
            ? \Illuminate\Support\Carbon::parse($timestamp)->timezone($displayTimezone)->format($format)
            : null;
        $details = $application->members_details ?? [];
        $legacyBilling = $details['billing_company_details'] ?? [];
        $legacyApplicant = $details['trademark_applicant_details'] ?? [];
        $legacySignatory = $details['details_of_signatory'] ?? [];
        $legacyAdditional = $details['details_of_co_applicant_or_partners'] ?? [];
        $legacyTrademark = $details['trademark_details'] ?? [];
        $billing = $details['billing_details'] ?? [
            'billing_name' => data_get($legacyBilling, 'billing_name'),
            'billing_email' => data_get($legacyBilling, 'billing_email'),
            'billing_phone' => data_get($legacyBilling, 'billing_mobile'),
            'billing_address' => data_get($legacyBilling, 'billing_address'),
        ];
        $applicant = $details['applicant_details'] ?? [
            'applicant_type' => data_get($legacyApplicant, 'type_of_applicant', $application->entity_type),
            'legal_name' => data_get($legacyApplicant, 'applicant_name', $application->applicant_name),
            'company_registration_number' => null,
            'company_registration_country' => null,
            'address_line_1' => data_get($legacyApplicant, 'address_of_applicant'),
            'address_line_2' => null,
            'town_city' => data_get($legacyApplicant, 'district'),
            'county_or_region' => data_get($legacyApplicant, 'region'),
            'nation' => data_get($legacyApplicant, 'state'),
            'postcode' => data_get($legacyApplicant, 'pin_code'),
            'country' => 'United Kingdom',
            'email' => data_get($legacyApplicant, 'email_id', $application->email),
            'phone' => data_get($legacyApplicant, 'phone_mobile_number', $application->phone),
            'uk_address_for_service' => null,
        ];
        $authorisedPerson = $details['authorised_person'] ?? [
            'full_name' => data_get($legacySignatory, 'name_of_signatory'),
            'position_or_capacity' => data_get($legacySignatory, 'designation_of_signatory'),
            'email' => data_get($legacySignatory, 'email_id'),
            'phone' => data_get($legacySignatory, 'phone_mobile_number'),
            'authority_confirmed' => null,
            'final_application_approved' => filled($application->client_approved_at),
            'approval_date' => $application->client_approved_at,
        ];
        $additionalApplicants = $details['additional_applicants'] ?? (array_filter($legacyAdditional) ? [$legacyAdditional] : []);
        $additionalApplicant = $additionalApplicants[0] ?? [];
        $tradeMark = $details['trademark_details'] ?? [];
        $priority = $details['priority_details'] ?? data_get($application->workflow_meta, 'priority', []);
        $ukipo = data_get($application->workflow_meta, 'ukipo', []);
        $payments = $application->payments->sortByDesc('id')->values();
        $completedPayments = $payments->filter(fn ($payment) => in_array(strtolower((string) $payment->status), ['completed', 'approved'], true));
        $paidTotal = (float) $completedPayments->sum('amount');
        $latestPayment = $payments->first();
        $latestCompletedPayment = $completedPayments->first();
        $billing = array_merge($billing, [
            'invoice_number' => data_get($billing, 'invoice_number') ?: $latestCompletedPayment?->reference_number,
            'professional_fee' => data_get($billing, 'professional_fee') ?: $latestPayment?->total_amount,
            'ukipo_official_fee' => data_get($billing, 'ukipo_official_fee'),
            'total_paid' => data_get($billing, 'total_paid') ?: ($paidTotal ?: null),
            'payment_status' => $latestPayment?->status ?: data_get($billing, 'payment_status'),
        ]);
        $applicant['joint_applicants'] = array_key_exists('joint_applicants', $details)
            ? (bool) $details['joint_applicants']
            : ! empty($additionalApplicants);
        $sections = [
            'Billing Details' => $billing,
            'Applicant Details' => $applicant,
            'Authorised Person' => $authorisedPerson,
            'Additional Applicant' => $additionalApplicant,
            'Trade Mark Details' => [
                'mark_type' => data_get($tradeMark, 'mark_type', data_get($legacyTrademark, 'trademark_type')),
                'trade_mark_wording' => data_get($tradeMark, 'trade_mark_wording', data_get($legacyTrademark, 'mark_brand_in_words', $application->brand_name)),
                'logo_mark_file' => data_get($tradeMark, 'logo_mark_file', data_get($legacyTrademark, 'image_of_trademark', $application->logo_path)),
                'language' => data_get($tradeMark, 'language', data_get($legacyTrademark, 'language_of_trademark')),
                'translation' => data_get($tradeMark, 'translation'),
                'business_activities' => data_get($tradeMark, 'business_activities', $application->goods_services),
                'currently_in_use' => data_get($tradeMark, 'currently_in_use', $application->currently_selling),
                'first_use_date' => data_get($tradeMark, 'first_use_date', $application->first_use_date),
                'supporting_evidence' => data_get($tradeMark, 'supporting_evidence', data_get($legacyTrademark, 'proof_of_use_of_trademark')),
                'proposed_classes' => data_get($tradeMark, 'proposed_classes'),
                'final_approved_classes' => data_get($tradeMark, 'final_approved_classes', $application->classes),
                'final_goods_and_services_specification' => data_get($tradeMark, 'final_goods_and_services_specification'),
                'special_limitations' => data_get($tradeMark, 'special_limitations', data_get($legacyTrademark, 'conditions_or_limitations')),
                'application_route' => data_get($tradeMark, 'application_route', data_get($application->workflow_meta, 'application_route')),
            ],
            'Priority Claim' => $priority,
            'UKIPO Details' => [
                'application_number' => data_get($ukipo, 'application_number', $application->application_number),
                'filing_date' => data_get($ukipo, 'filing_date', $application->filed_at),
                'examination_deadline' => data_get($ukipo, 'examination_deadline'),
                'publication_date' => data_get($ukipo, 'publication_date'),
                'opposition_deadline' => data_get($ukipo, 'opposition_deadline'),
                'registration_number' => data_get($ukipo, 'registration_number'),
                'renewal_date' => data_get($ukipo, 'renewal_date'),
            ],
        ];
        $formatValue = function ($value) {
            if (is_bool($value)) {
                return $value ? 'Yes' : 'No';
            }

            if ($value instanceof \DateTimeInterface) {
                return $value->format('d M Y');
            }

            if (is_array($value)) {
                return implode(', ', array_filter($value, fn ($item) => $item !== null && $item !== '')) ?: 'N/A';
            }

            return filled($value) ? $value : 'N/A';
        };
        $formatMoney = static function ($amount): string {
            $amount = (float) $amount;
            $decimals = abs($amount - round($amount)) > 0.00001 ? 2 : 0;

            return '£' . number_format($amount, $decimals);
        };
        $paymentTypeLabel = static function ($payment): string {
            $type = strtolower((string) ($payment->payment_type ?? ''));

            if ($type === 'full') {
                return 'Full Payment';
            }

            if ($type === 'final') {
                return 'Final 50%';
            }

            return 'Advance 50%';
        };
        $draft = $application->draftVersions->sortByDesc('id')->first();
        $statusLogs = $application->statusLogs->sortByDesc('id')->take(8);
        $postFilingJourney = \App\Support\PostFilingJourney::class;
        $postFilingStages = $postFilingJourney::stages($application);
        $postFilingActiveStageKey = $postFilingJourney::activeStageKey($application);
        $postFilingDocumentsByType = $application->documents
            ->filter(fn ($doc) => str_starts_with((string) $doc->document_type, 'post_filing_'))
            ->groupBy('document_type');
        $adminStatusLabel = ($application->registry_status === $workflow::REGISTRY_REGISTERED || filled($application->registered_at))
            ? 'Registered'
            : $application->status_label;
        $isPostFilingAdminDocument = function ($doc) {
            return str_starts_with((string) $doc->document_type, 'post_filing_')
                && (
                    str_contains((string) $doc->file_path, 'workflow/admin/post-filing/')
                    || str_contains(strtolower((string) $doc->verification_notes), 'sent by admin')
                    || str_contains(strtolower((string) $doc->verification_notes), 'document sent by admin')
                );
        };
        $isAdminOnboardingDocument = function ($doc) {
            return in_array((string) $doc->document_type, ['engagement_letter', 'application_summary', 'final_specification', 'filing_authority', 'other_document'], true)
                && (
                    str_contains((string) $doc->file_path, 'workflow/admin/')
                    || str_contains(strtolower((string) $doc->verification_notes), 'uploaded by admin')
                    || str_contains(strtolower((string) $doc->verification_notes), 'resent by admin')
                    || str_contains(strtolower((string) $doc->verification_notes), 'sent by admin')
                );
        };
        $isGenericAdminOnboardingNote = function (?string $note) {
            $normalized = strtolower(trim((string) $note));

            return in_array($normalized, [
                'additional onboarding document uploaded by admin.',
                'additional onboarding document resent by admin.',
                'document sent by admin.',
            ], true);
        };
        $postFilingIsFullyCompleted = collect($postFilingStages)->isNotEmpty()
            && collect($postFilingStages)->every(fn ($stage) => $postFilingJourney::statusFor($application, $stage['key']) === $postFilingJourney::COMPLETED);
        $registeredAdminDocuments = $postFilingDocumentsByType
            ->get($postFilingJourney::documentType('registered'), collect())
            ->filter(fn ($doc) => $isPostFilingAdminDocument($doc));
        $placementDocuments = [
            'engagement_letter' => 'Engagement Letter',
        ];
        $plainUploadDocuments = [];
        $signatureRequiredDocuments = ['engagement_letter'];
        $oppositionApplication = $oppositionApplication ?? $application->oppositionApplication;
        $oppositionDefenceCase = $oppositionDefenceCase ?? $application->oppositionDefenceCase;
        $oppositionApplicationRoute = function ($case) {
            if (! $case) {
                return null;
            }

            return $case->flow_type === \App\Support\TrademarkOppositionWorkflow::FLOW_OPPOSE
                ? route('admin.trademark-opposition.oppose.show', $case)
                : route('admin.trademark-opposition.show', $case);
        };
        $compactFileName = function (?string $fileName, int $limit = 42) {
            $name = trim((string) $fileName);

            if ($name === '') {
                return 'N/A';
            }

            if (mb_strlen($name) <= $limit) {
                return $name;
            }

            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $baseName = pathinfo($name, PATHINFO_FILENAME);
            $suffix = $extension !== '' ? '.' . $extension : '';
            $available = max($limit - mb_strlen($suffix) - 1, 12);

            return mb_substr($baseName, 0, $available) . '…' . $suffix;
        };
        $bulkReviewableDocuments = $application->documents->filter(function ($doc) {
            $isAlreadyVerified = $doc->status === 'verified' || (bool) $doc->verified_at;
            $requiresVerification = in_array($doc->document_type, ['engagement_letter (Signed)', 'filing_authority (Signed)'], true);

            return !$isAlreadyVerified && $requiresVerification && in_array($doc->status, ['uploaded', 'reuploaded'], true);
        });
        $signedEngagementLetterForReview = $application->documents
            ->where('document_type', 'engagement_letter (Signed)')
            ->sortByDesc('id')
            ->first();
        $engagementLetterAwaitingAdminReview = $signedEngagementLetterForReview
            && !$signedEngagementLetterForReview->verified_at
            && $signedEngagementLetterForReview->status !== 'verified'
            && in_array($signedEngagementLetterForReview->status, ['uploaded', 'reuploaded'], true);
        $placementValue = function (string $documentType, string $fieldType, string $key) use ($application) {
            $storedField = collect(data_get($application->workflow_meta, "signature_fields.$documentType", []))
                ->firstWhere('type', $fieldType);

            return old(
                "signature_fields.$documentType.$fieldType.$key",
                data_get($storedField, $key, '')
            );
        };
    @endphp

    <div class="container-fluid py-4 admin-application-review-page">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">Application Review</h1>
                <p class="text-muted mb-0">
                    Application #{{ $application->id }}
                    @if ($application->application_number)
                        | {{ $application->application_number }}
                    @endif
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary fs-6">{{ $adminStatusLabel }}</span>
                <a href="#submitted-data" class="btn btn-outline-primary btn-sm">Submitted Data</a>
                <a href="#submitted-documents" class="btn btn-outline-primary btn-sm">Documents</a>
                <a href="{{ route('admin.applications') }}" class="btn btn-outline-secondary btn-sm">Back</a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                @php
                    $matterOverviewHasErrors = $errors->hasAny([
                        'brand_name', 'applicant_name', 'applicant_type', 'email', 'phone', 'business_activities',
                    ]);
                @endphp
                <form method="POST" action="{{ route('admin.application.matter-overview.update', $application) }}" class="card shadow-sm border-0 mb-4" id="submitted-data" data-matter-overview-form data-start-editing="{{ $matterOverviewHasErrors ? 'true' : 'false' }}">
                    @csrf
                    @method('PUT')
                    <div class="card-header admin-card-header d-flex justify-content-between align-items-center gap-3">
                        <div>
                            <h5 class="mb-1">Matter Overview</h5>
                            <small>Edit the client-submitted matter details below.</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-light btn-sm matter-overview-save" data-matter-overview-toggle>
                                <i class="bi {{ $matterOverviewHasErrors ? 'bi-check2-circle' : 'bi-pencil-square' }} me-1" data-matter-overview-icon></i>
                                <span data-matter-overview-label>{{ $matterOverviewHasErrors ? 'Save Changes' : 'Edit' }}</span>
                            </button>
                            <button type="button" class="btn btn-outline-light btn-sm matter-overview-cancel" data-matter-overview-cancel @if (! $matterOverviewHasErrors) hidden @endif>
                                <i class="bi bi-x-lg me-1"></i> Cancel
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <fieldset class="matter-overview-fields" data-matter-overview-fields @disabled(! $matterOverviewHasErrors)>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="data-block matter-overview-field">
                                    <label class="data-label" for="matter_brand_name">Trademark</label>
                                    <input id="matter_brand_name" name="brand_name" class="form-control @error('brand_name') is-invalid @enderror" value="{{ old('brand_name', $application->brand_name) }}" maxlength="255" required>
                                    @error('brand_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="data-block matter-overview-field">
                                    <label class="data-label" for="matter_applicant_name">Applicant</label>
                                    <input id="matter_applicant_name" name="applicant_name" class="form-control @error('applicant_name') is-invalid @enderror" value="{{ old('applicant_name', $application->applicant_name) }}" maxlength="255" required>
                                    @error('applicant_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="data-block matter-overview-field">
                                    <label class="data-label" for="matter_applicant_type">Applicant Type</label>
                                    <select id="matter_applicant_type" name="applicant_type" class="form-select @error('applicant_type') is-invalid @enderror" required>
                                        <option value="">Select applicant type</option>
                                        @foreach (config('uk_site.applicant_types', []) as $value => $label)
                                            <option value="{{ $value }}" @selected(old('applicant_type', data_get($applicant, 'applicant_type', $application->entity_type)) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('applicant_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="data-block matter-overview-field">
                                    <label class="data-label" for="matter_email">Email</label>
                                    <input type="email" id="matter_email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $application->email ?? $application->user->email) }}" maxlength="255" required>
                                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="data-block matter-overview-field">
                                    <label class="data-label" for="matter_phone">Phone</label>
                                    <input type="tel" id="matter_phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $application->phone) }}" maxlength="16" placeholder="07123 456789" required>
                                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Application Status</span><div class="data-value">{{ $adminStatusLabel }}</div></div></div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Submitted Date</span><div class="data-value">{{ $formatDateTime($application->created_at, 'd M Y, h:i A') }}</div></div></div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Requested Service</span><div class="data-value">UK Trade Mark Application</div></div></div>
                            <div class="col-12">
                                <div class="data-block matter-overview-field">
                                    <label class="data-label" for="matter_business_activities">Business Activities</label>
                                    <textarea id="matter_business_activities" name="business_activities" class="form-control @error('business_activities') is-invalid @enderror" rows="4" maxlength="3000" required>{{ old('business_activities', data_get($tradeMark, 'business_activities', $application->goods_services)) }}</textarea>
                                    @error('business_activities')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            @php
                                $classValues = data_get($tradeMark, 'final_approved_classes', $application->classes) ?: data_get($tradeMark, 'proposed_classes');
                                $classList = is_array($classValues) ? array_filter($classValues) : array_filter(preg_split('/\s*,\s*/', (string) $classValues));
                            @endphp
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Number of Classes</span><div class="data-value">{{ count($classList) ?: 'Not finalised' }}</div></div></div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Estimated Official Fee</span><div class="data-value">{{ filled(data_get($billing, 'ukipo_official_fee')) ? '£'.number_format((float) data_get($billing, 'ukipo_official_fee'), 2) : 'To be confirmed' }}</div></div></div>
                        </div>
                        </fieldset>
                    </div>
                </form>

                @if (false && ($oppositionApplication || $oppositionDefenceCase))
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header admin-card-header">
                            <h5 class="mb-0">Opposition Cases</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                @if ($oppositionApplication)
                                    <div class="col-md-6">
                                        <div class="data-block">
                                            <span class="data-label">Opposition Application</span>
                                            <div class="data-value">{{ $oppositionApplication->case_number }}</div>
                                        </div>
                                        <div class="data-block mt-2">
                                            <span class="data-label">Status</span>
                                            <div class="data-value">{{ $oppositionApplication->current_admin_status }}</div>
                                        </div>
                                        <div class="data-block mt-2">
                                            <span class="data-label">Filed By</span>
                                            <div class="data-value">{{ $oppositionApplication->user_business_name ?: ($oppositionApplication->applicant_name ?: 'N/A') }}</div>
                                        </div>
                                        <div class="data-block mt-2">
                                            <span class="data-label">Link</span>
                                            <div class="data-value">
                                                <a href="{{ $oppositionApplicationRoute($oppositionApplication) }}" target="_blank">Open Opposition Application</a>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @if ($oppositionDefenceCase)
                                    <div class="col-md-6">
                                        <div class="data-block">
                                            <span class="data-label">Defence Case</span>
                                            <div class="data-value">{{ $oppositionDefenceCase->case_number }}</div>
                                        </div>
                                        <div class="data-block mt-2">
                                            <span class="data-label">Status</span>
                                            <div class="data-value">{{ $oppositionDefenceCase->current_admin_status }}</div>
                                        </div>
                                        <div class="data-block mt-2">
                                            <span class="data-label">Filed By</span>
                                            <div class="data-value">{{ $oppositionDefenceCase->user_business_name ?: ($oppositionDefenceCase->applicant_name ?: 'N/A') }}</div>
                                        </div>
                                        <div class="data-block mt-2">
                                            <span class="data-label">Link</span>
                                            <div class="data-value">
                                                <a href="{{ route('admin.trademark-opposition.show', $oppositionDefenceCase) }}" target="_blank">Open Defence Case</a>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @foreach ($sections as $title => $sectionData)
                    @if (!empty(array_filter($sectionData, fn ($value) => $value !== null && $value !== '' && $value !== [])))
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header admin-card-header">
                                <h5 class="mb-0">{{ $title }}</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    @foreach ($sectionData as $field => $value)
                                        <div class="col-md-6">
                                            <div class="data-block h-100">
                                                <span class="data-label">{{ ucwords(str_replace('_', ' ', $field)) }}</span>
                                                @if (in_array($field, ['image_of_trademark', 'logo_mark_file'], true) && $value)
                                                    <div class="data-value">
                                                        <a href="{{ route('admin.trademark.image.view', ['id' => $application->id, 'file' => base64_encode((string) $value)]) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                                            <i class="fas fa-eye"></i> View Trademark Image
                                                        </a>
                                                    </div>
                                                @elseif (in_array($field, ['proof_of_use_of_trademark', 'supporting_evidence', 'priority_document'], true) && $value)
                                                    <div class="data-value">
                                                        <a href="{{ route('admin.trademark.proof-of-use.view', ['id' => $application->id, 'file' => base64_encode((string) $value)]) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                                            <i class="fas fa-eye"></i> View Proof of Use
                                                        </a>
                                                    </div>
                                                @else
                                                    <div class="data-value">{{ $formatValue($value) }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach

                <div class="card shadow-sm border-0 mb-4" id="application-payments">
                    <div class="card-header admin-card-header d-flex justify-content-between align-items-center gap-3">
                        <h5 class="mb-0">Payment Proof &amp; Invoices</h5>
                        <span class="payment-total-badge">Total paid: {{ $formatMoney($paidTotal) }}</span>
                    </div>
                    <div class="card-body">
                        @if ($payments->isEmpty())
                            <div class="payment-empty-state">
                                <i class="bi bi-receipt"></i>
                                <div>
                                    <strong>No payment submitted yet.</strong>
                                    <p class="mb-0">Payment references and invoices will appear here for this application.</p>
                                </div>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table align-middle admin-payment-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Payment</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Payment Proof</th>
                                            <th>Date</th>
                                            <th class="text-end">Invoice</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($payments as $payment)
                                            @php
                                                $normalizedPaymentStatus = strtolower((string) $payment->status);
                                                $hasInvoice = in_array($normalizedPaymentStatus, ['completed', 'approved'], true);
                                                $paymentStatusClass = match ($normalizedPaymentStatus) {
                                                    'completed', 'approved' => 'success',
                                                    'rejected', 'failed' => 'danger',
                                                    default => 'warning text-dark',
                                                };
                                            @endphp
                                            <tr>
                                                <td>
                                                    <strong>{{ $paymentTypeLabel($payment) }}</strong>
                                                    <div class="small text-muted">{{ ucfirst((string) ($payment->payment_method ?: 'Online')) }}</div>
                                                </td>
                                                <td>
                                                    <strong>{{ $formatMoney($payment->amount) }}</strong>
                                                    <div class="small text-muted">of {{ $formatMoney($payment->total_amount) }}</div>
                                                </td>
                                                <td><span class="badge bg-{{ $paymentStatusClass }}">{{ ucwords(str_replace('_', ' ', $normalizedPaymentStatus)) }}</span></td>
                                                <td>
                                                    <div class="payment-proof-line"><span>Transaction</span><code>{{ $payment->transaction_id ?: 'Awaiting payment' }}</code></div>
                                                    <div class="payment-proof-line"><span>Order / Reference</span><code>{{ $payment->reference_number ?: 'N/A' }}</code></div>
                                                </td>
                                                <td>{{ $formatDateTime($payment->paid_at ?: $payment->created_at, 'd M Y, h:i A') }}</td>
                                                <td class="text-end">
                                                    @if ($hasInvoice)
                                                        <a href="{{ route('admin.payment.invoice', $payment) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                                            <i class="bi bi-file-earmark-pdf"></i> View Invoice
                                                        </a>
                                                    @else
                                                        <span class="small text-muted">Available after payment</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4" id="submitted-documents" data-filing-documents-card>
                    <div class="card-header admin-card-header d-flex justify-content-between align-items-center gap-3">
                        <h5 class="mb-0">Documents</h5>
                        @if ($bulkReviewableDocuments->isNotEmpty())
                            <button class="btn btn-outline-light btn-sm fw-semibold" type="button" data-filing-doc-select-toggle>Select</button>
                        @endif
                    </div>
                    <div class="card-body">
                        @if ($application->documents->isEmpty())
                            <p class="text-muted mb-0">No documents available yet.</p>
                        @else
                            <form id="filingDocReviewForm" method="POST" action="{{ route('admin.documents.review', $application->id) }}" data-filing-doc-review-form>
                                @csrf
                                <input type="hidden" name="action" data-filing-doc-review-action>
                                <input type="hidden" name="note" data-filing-doc-review-note>
                            </form>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>File</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($application->documents as $doc)
                                            @php
                                                $isAlreadyVerified = $doc->status === 'verified' || (bool) $doc->verified_at;
                                                $isAdminSentPostFilingDocument = $isPostFilingAdminDocument($doc);
                                                $isAdminSentOnboardingDocument = $isAdminOnboardingDocument($doc);
                                                $normalizedVerificationNote = strtolower((string) $doc->verification_notes);
                                                $isReuploadedSubmission = in_array((string) $doc->document_type, ['engagement_letter (Signed)', 'filing_authority (Signed)'], true)
                                                    && in_array((string) $doc->status, ['uploaded', 'reuploaded'], true)
                                                    && (
                                                        str_contains($normalizedVerificationNote, 'reuploaded')
                                                        || str_contains($normalizedVerificationNote, 'reapplied')
                                                    );
                                                $displayStatus = $isAlreadyVerified
                                                    ? 'verified'
                                                    : ($isReuploadedSubmission ? 'reuploaded' : $doc->status);
                                                $isReviewable = !$isAlreadyVerified && in_array($doc->status, ['uploaded', 'reuploaded'], true);
                                                $requiresVerification = in_array($doc->document_type, ['engagement_letter (Signed)', 'filing_authority (Signed)'], true);
                                                $isReuploadRequested = $displayStatus === 'reupload_requested';
                                                $statusLabel = $isAdminSentOnboardingDocument
                                                    ? 'Admin Sent'
                                                    : ($isReuploadRequested
                                                        ? 'Asked for Reupload'
                                                        : ucwords(str_replace('_', ' ', $displayStatus)));
                                                $statusClass = $isAdminSentOnboardingDocument ? 'primary' : match ($displayStatus) {
                                                    'verified', 'approved' => 'success',
                                                    'reuploaded' => 'info',
                                                    'reupload_requested' => 'danger',
                                                    'uploaded' => 'warning text-dark',
                                                    default => 'light text-dark border',
                                                };
                                            @endphp
                                            <tr>
                                                <td>
                                                    @if ($isReviewable && $requiresVerification)
                                                        <input class="form-check-input me-2 filing-doc-select d-none" type="checkbox" name="document_ids[]" value="{{ $doc->id }}" form="filingDocReviewForm" data-filing-doc-checkbox data-document-type="{{ $doc->document_type }}">
                                                    @endif
                                                    {{ ucwords(str_replace(['_', '(signed)'], [' ', ' (Signed)'], $doc->document_type)) }}
                                                    @if ($isAdminSentPostFilingDocument || $isAdminSentOnboardingDocument)
                                                        <div class="small text-primary fw-semibold mt-1">Admin sent to client</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="badge bg-{{ $statusClass }}">{{ $statusLabel }}</span>
                                                    @if ($doc->verification_notes && !$isGenericAdminOnboardingNote($doc->verification_notes))
                                                        <div class="small text-muted mt-1">{{ $doc->verification_notes }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="small fw-semibold text-dark" title="{{ $doc->file_name }}">{{ $compactFileName($doc->file_name) }}</div>
                                                    @if ($doc->file_name && $compactFileName($doc->file_name) !== $doc->file_name)
                                                        <div class="small text-muted">Full name on hover</div>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-flex justify-content-end gap-2 flex-wrap">
                                                        @if (!$isReuploadRequested)
                                                            <a href="{{ route('admin.document.view', $doc->id) }}" target="_blank" class="btn btn-outline-primary btn-sm">View</a>
                                                        @endif
                                                        @if ($doc->status === 'pending')
                                                            <form action="{{ route('admin.approve-document', $doc->id) }}" method="POST" data-swal-confirm data-swal-title="Approve document?" data-swal-text="This will mark the document as approved and notify the applicant." data-swal-icon="question" data-swal-confirm-text="Yes, approve">@csrf<button type="submit" class="btn btn-success btn-sm">Approve</button></form>
                                                        @endif
                                                        @if ($isReviewable && !$requiresVerification)
                                                            <span class="small text-muted">No admin verification required</span>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if ($bulkReviewableDocuments->isNotEmpty())
                                <div class="d-none justify-content-end gap-2 flex-wrap mt-3" data-filing-doc-bulk-actions>
                                    <button class="btn btn-success" type="button" data-filing-doc-open-modal="verified">
                                        <i class="bi bi-check-circle"></i> Mark as Approved
                                    </button>
                                    <button class="btn btn-outline-danger" type="button" data-filing-doc-open-modal="reupload_requested">
                                        <i class="bi bi-arrow-repeat"></i> Ask for Reupload
                                    </button>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                <div class="modal fade admin-bulk-review-modal" id="bulkDocumentReviewModal" tabindex="-1" aria-labelledby="bulkDocumentReviewModalLabel" aria-hidden="true" data-filing-doc-review-modal>
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div>
                                    <h5 class="modal-title" id="bulkDocumentReviewModalLabel" data-filing-doc-review-modal-title>Review Selected Documents</h5>
                                    <small class="text-muted" data-filing-doc-review-modal-subtitle></small>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-danger d-none" data-filing-doc-review-error></div>
                                <div data-filing-doc-review-note-wrap>
                                    <label class="form-label fw-semibold" for="bulkDocumentReviewNote">Reason for re-upload request</label>
                                    <textarea id="bulkDocumentReviewNote" class="form-control" rows="4" minlength="10" maxlength="1000" placeholder="Explain what the applicant must correct before uploading again..." data-filing-doc-review-note-textarea></textarea>
                                    <div class="form-text">This note will be sent to the applicant.</div>
                                </div>
                                <p class="mb-0 text-muted" data-filing-doc-review-approve-copy hidden>Selected signed approval documents will be accepted and verified.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" data-filing-doc-review-confirm>Continue</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header admin-card-header">
                        <h5 class="mb-0">Workflow Activity</h5>
                    </div>
                    <div class="card-body">
                        @forelse ($statusLogs as $log)
                            <div class="{{ !$loop->last ? 'pb-3 mb-3 border-bottom' : '' }}">
                                <div class="fw-semibold">{{ data_get($log->metadata, 'title', $workflow::label($log->to_status)) }}</div>
                                <div class="small text-muted">{{ $formatDateTime($log->created_at) }}</div>
                                @if ($log->reason)
                                    <div class="mt-1">{{ $log->reason }}</div>
                                @endif
                            </div>
                        @empty
                            <p class="text-muted mb-0">No workflow activity recorded yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
                    <div class="card-header admin-card-header">
                        <h5 class="mb-0">Stage Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="client-preview-actions mb-4">
                            <div class="small text-muted fw-semibold text-uppercase mb-2">Client view</div>
                            <div class="d-grid gap-2">
                                <a href="{{ route('admin.application.client-dashboard', $application->id) }}" class="btn btn-outline-primary" target="_blank" rel="noopener">
                                    <i class="bi bi-grid me-2"></i>View Client Dashboard
                                </a>
                                <a href="{{ route('admin.application.client-action-center', ['id' => $application->id, 'stage_action' => 1]) }}#stage-action" class="btn btn-outline-primary" target="_blank" rel="noopener">
                                    <i class="bi bi-eye me-2"></i>View Client Action Center
                                </a>
                            </div>
                            <div class="form-text mt-2">Read-only preview. Client data and workflow status cannot be changed here.</div>
                        </div>
                        <hr class="mb-4">

                        @if ($application->current_status === $workflow::APPLICATION_SUBMITTED)
                            <div class="alert alert-info">
                                <div class="fw-semibold mb-1">Application received and waiting for admin review.</div>
                                <small>Start the review to unlock the UK search, specification and information-request actions.</small>
                            </div>
                            <form action="{{ route('admin.start-review', $application->id) }}" method="POST" data-swal-confirm data-swal-title="Start reviewing this application?" data-swal-text="The client will be notified that their application is under review." data-swal-icon="question" data-swal-confirm-text="Yes, start review">
                                @csrf
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-play-circle me-2"></i>Start Admin Review
                                </button>
                            </form>
                        @elseif ($application->current_status === $workflow::UNDER_REVIEW)
                            <form action="{{ route('admin.approve', $application->id) }}" method="POST" class="mb-4" enctype="multipart/form-data" data-swal-confirm data-swal-title="Complete the initial review?" data-swal-text="This will send the Engagement Letter to the client for electronic signing." data-swal-icon="question" data-swal-confirm-text="Yes, send letter">
                                @csrf
                                <label class="form-label fw-semibold">Review Note</label>
                                <textarea name="notes" class="form-control" rows="4" placeholder="Share signing or review instructions with the client...">{{ old('notes') }}</textarea>
                                @include('admin.partials.signing-field-placement', [
                                    'placementDocuments' => $placementDocuments,
                                    'plainUploadDocuments' => $plainUploadDocuments,
                                    'signatureRequiredDocuments' => $signatureRequiredDocuments,
                                    'placementValue' => $placementValue,
                                ])
                                <button type="submit" class="btn btn-success w-100 mt-3">Send Engagement Letter for Signing</button>
                            </form>

                            <form action="{{ route('admin.request-changes', $application->id) }}" method="POST" data-swal-confirm data-swal-title="Request application changes?" data-swal-text="The applicant will be notified and can recheck/edit the application before submitting again." data-swal-icon="warning" data-swal-confirm-text="Yes, request changes">
                                @csrf
                                <label class="form-label fw-semibold">Change Request Note</label>
                                <textarea name="change_request" class="form-control" rows="4" required placeholder="Explain what the applicant must correct...">{{ old('change_request') }}</textarea>
                                <button type="submit" class="btn btn-warning w-100 mt-3">Request Changes</button>
                            </form>
                        @elseif ($application->current_status === $workflow::ONBOARDING_PENDING)
                            @if ($engagementLetterAwaitingAdminReview)
                                <div class="engagement-review-ready">
                                    <div class="engagement-review-ready-icon" aria-hidden="true">
                                        <i class="bi bi-file-earmark-check"></i>
                                    </div>
                                    <div class="engagement-review-ready-copy">
                                        <span>Awaiting admin verification</span>
                                        <h6>Signed Engagement Letter received</h6>
                                        <p>The client has completed the signature. Review and approve the signed document to continue.</p>
                                    </div>
                                    <a href="#submitted-documents" class="btn btn-success w-100" data-scroll-to-documents data-document-type="engagement_letter (Signed)">
                                        <i class="bi bi-arrow-down-circle me-2"></i>Review signed document
                                    </a>
                                </div>

                                <details class="engagement-resend-options mt-3">
                                    <summary>Replace or resend the Engagement Letter</summary>
                                    <div class="pt-3">
                                        <form action="{{ route('admin.resend-onboarding-package', $application->id) }}" method="POST" enctype="multipart/form-data" data-swal-confirm data-swal-title="Resend Engagement Letter?" data-swal-text="This replaces the current letter and resets the client's latest signature submission." data-swal-icon="warning" data-swal-confirm-text="Yes, resend letter">
                                            @csrf
                                            <label class="form-label fw-semibold">Resend Note <span class="text-muted fw-normal">(optional)</span></label>
                                            <textarea name="notes" class="form-control" rows="3" placeholder="Optional note for the client">{{ old('notes') }}</textarea>
                                            @include('admin.partials.signing-field-placement', [
                                                'placementDocuments' => $placementDocuments,
                                                'plainUploadDocuments' => $plainUploadDocuments,
                                                'signatureRequiredDocuments' => $signatureRequiredDocuments,
                                                'placementValue' => $placementValue,
                                            ])
                                            <button type="submit" class="btn btn-warning w-100 mt-3">Resend Engagement Letter</button>
                                        </form>
                                    </div>
                                </details>
                            @else
                                <div class="alert alert-info">
                                    <div class="fw-semibold">Engagement Letter signing pending</div>
                                    <small>Resend the letter if the client needs a corrected copy. POA and Affidavit are not required for UK applications.</small>
                                </div>
                                <form action="{{ route('admin.resend-onboarding-package', $application->id) }}" method="POST" enctype="multipart/form-data" data-swal-confirm data-swal-title="Resend Engagement Letter?" data-swal-text="This replaces the current letter and resets the client's latest signature submission." data-swal-icon="warning" data-swal-confirm-text="Yes, resend letter">
                                    @csrf
                                    <label class="form-label fw-semibold">Resend Note <span class="text-muted fw-normal">(optional)</span></label>
                                    <textarea name="notes" class="form-control" rows="3" placeholder="Optional note for the client">{{ old('notes') }}</textarea>
                                    @include('admin.partials.signing-field-placement', [
                                        'placementDocuments' => $placementDocuments,
                                        'plainUploadDocuments' => $plainUploadDocuments,
                                        'signatureRequiredDocuments' => $signatureRequiredDocuments,
                                        'placementValue' => $placementValue,
                                    ])
                                    <button type="submit" class="btn btn-warning w-100 mt-3">Resend Engagement Letter</button>
                                </form>
                                <button type="button" class="btn btn-outline-success w-100 mt-3" data-bs-toggle="modal" data-bs-target="#manualOnboardingUploadModal">
                                    <i class="bi bi-upload me-2"></i>Upload Signed Letter Manually
                                </button>
                            @endif
                        @elseif ($application->current_status === $workflow::STRATEGY_IN_PROGRESS)
                            <form action="{{ route('admin.strategy-complete', $application->id) }}" method="POST" class="mb-4" data-swal-confirm data-swal-title="Complete search and specification?" data-swal-text="This moves the matter to application preparation." data-swal-icon="question" data-swal-confirm-text="Yes, complete">
                                @csrf
                                <input type="hidden" name="quick_complete" value="1">
                                <button type="submit" class="btn btn-success w-100">Mark Search and Specification Complete</button>
                            </form>
                            <!-- <hr> -->
                            <p style="text-align:center;">or</p>
                            <form action="{{ route('admin.upload-search-report', $application->id) }}" method="POST" enctype="multipart/form-data" class="mb-4" data-swal-confirm data-swal-title="Send search report?" data-swal-text="The search report will be shared with the applicant and strategy will be completed." data-swal-icon="question" data-swal-confirm-text="Yes, send report">
                                @csrf
                                <label class="form-label fw-semibold">Search Report PDF</label>
                                <input type="file" name="search_report" class="form-control" accept=".pdf" required>
                                <label class="form-label fw-semibold mt-3">Report Note</label>
                                <textarea name="search_report_note" class="form-control" rows="3" placeholder="Optional note to send with the manual search report">{{ old('search_report_note') }}</textarea>
                                <button type="submit" class="btn btn-outline-primary w-100 mt-3">Send Search Report and Complete Review</button>
                            </form>
                        @elseif (in_array($application->current_status, [$workflow::STRATEGY_COMPLETED, $workflow::CHANGES_REQUESTED]))
                            <form action="{{ route('admin.publish-draft', $application->id) }}" method="POST" enctype="multipart/form-data" data-swal-confirm data-swal-title="Send application for approval?" data-swal-text="The applicant will be asked to approve the applicant, mark, classes, specification and filing authority." data-swal-icon="question" data-swal-confirm-text="Yes, send for approval">
                                @csrf
                                <label class="form-label fw-semibold">Application Summary PDF</label>
                                <input type="file" name="draft_file" class="form-control" accept=".pdf" required>
                                <div class="form-text">Include the final classes and goods and services specification for client approval.</div>
                                <label class="form-label fw-semibold mt-3">Final Specification PDF</label>
                                <input type="file" name="final_specification_file" class="form-control" accept=".pdf" required>
                                <label class="form-label fw-semibold mt-3">Filing Authority PDF</label>
                                <input type="file" name="filing_authority_file" class="form-control" accept=".pdf" required>
                                <label class="form-label fw-semibold mt-3">Final Classes</label>
                                <input type="text" name="final_classes" class="form-control" value="{{ old('final_classes', is_array($application->classes) ? implode(', ', $application->classes) : '') }}" placeholder="e.g. 9, 35, 42" required>
                                <label class="form-label fw-semibold mt-3">Final Goods and Services Specification</label>
                                <textarea name="final_specification" class="form-control" rows="6" maxlength="10000" required placeholder="Enter the complete UKIPO-ready specification...">{{ old('final_specification', data_get($tradeMark, 'final_goods_and_services_specification')) }}</textarea>
                                @if (data_get($priority, 'priority_claim_required'))
                                    <label class="form-label fw-semibold mt-3">Priority Deadline <span class="text-muted fw-normal">(optional)</span></label>
                                    <input type="date" name="priority_deadline" class="form-control" value="{{ old('priority_deadline', data_get($priority, 'priority_deadline')) }}">
                                    <label class="form-check mt-3">
                                        <input type="checkbox" name="priority_approved" value="1" class="form-check-input" required @checked(old('priority_approved', data_get($priority, 'approved_by_admin')))>
                                        <span class="form-check-label">I have checked and approved the priority claim.</span>
                                    </label>
                                    @error('priority_approved')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                @endif
                                <label class="form-label fw-semibold mt-3">Approval Note</label>
                                <textarea name="draft_note" class="form-control" rows="4" placeholder="Optional note to send with the draft">{{ old('draft_note', $draft?->goods_services) }}</textarea>
                                <button type="submit" class="btn btn-success w-100 mt-3">Send Application for Approval</button>
                            </form>
                        @elseif ($application->current_status === $workflow::PAYMENT_COMPLETED)
                            <form action="{{ route('admin.file', $application->id) }}" method="POST" enctype="multipart/form-data" data-swal-confirm data-swal-title="Record UKIPO filing?" data-swal-text="This records the filing details and receipt." data-swal-icon="question" data-swal-confirm-text="Yes, record filing">
                                @csrf
                                <label class="form-label fw-semibold">UKIPO Application Number</label>
                                <input type="text" name="application_number" class="form-control" value="{{ old('application_number', $application->application_number) }}" placeholder="UK0000..." required>
                                <label class="form-label fw-semibold mt-3">UKIPO Filing Date</label>
                                <input type="date" name="filing_date" class="form-control" value="{{ old('filing_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                                <label class="form-label fw-semibold mt-3">UKIPO Filing Receipt</label>
                                <input type="file" name="filing_receipt" class="form-control" accept=".pdf" required>
                                <label class="form-label fw-semibold mt-3">Examination Deadline <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="date" name="examination_deadline" class="form-control" value="{{ old('examination_deadline') }}">
                                <label class="form-label fw-semibold mt-3">Publication Date <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="date" name="publication_date" class="form-control" value="{{ old('publication_date') }}">
                                <label class="form-label fw-semibold mt-3">Opposition Deadline <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="date" name="opposition_deadline" class="form-control" value="{{ old('opposition_deadline') }}">
                                <label class="form-label fw-semibold mt-3">Registration Number <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="text" name="registration_number" class="form-control" value="{{ old('registration_number') }}">
                                <label class="form-label fw-semibold mt-3">Renewal Date <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="date" name="renewal_date" class="form-control" value="{{ old('renewal_date') }}">
                                <label class="form-label fw-semibold mt-3">Admin Note <span class="text-muted fw-normal">(optional)</span></label>
                                <textarea name="filing_note" class="form-control" rows="3" maxlength="1000" placeholder="Optional note to show to the applicant and include in the filing email">{{ old('filing_note') }}</textarea>
                                <button type="submit" class="btn btn-success w-100 mt-3">Record UKIPO Filing</button>
                            </form>
                        @elseif ($application->current_status === $workflow::FILED)
                            <div class="alert alert-info">
                                <small>
                                    This matter is currently in <strong>Filed</strong>. Mark this stage complete when filing confirmation is done and post-filing care should begin.
                                </small>
                            </div>
                            <form action="{{ route('admin.file-complete', $application->id) }}" method="POST" enctype="multipart/form-data" data-swal-confirm data-swal-title="Complete filed stage?" data-swal-text="This will start post-filing care for the application." data-swal-icon="question" data-swal-confirm-text="Yes, complete stage">
                                @csrf
                                <label class="form-label fw-semibold">Completion Note <span class="text-muted fw-normal">(optional)</span></label>
                                <textarea name="completion_note" class="form-control" rows="3" placeholder="Optional note for the workflow activity">{{ old('completion_note') }}</textarea>
                                <label class="form-label fw-semibold mt-3">Documents for Client Review <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="file" name="admin_stage_documents[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                <div class="form-text">Attach any filed-stage documents the client should be able to review.</div>
                                <button type="submit" class="btn btn-success w-100 mt-3">Mark Filed Stage Complete</button>
                            </form>
                        @elseif ($application->current_status === $workflow::POST_FILING)
                            <div class="post-filing-admin-panel">
                                @if ($postFilingIsFullyCompleted)
                                    <div class="alert alert-success mb-0">
                                        <div class="fw-semibold">Trademark registered successfully.</div>
                                        <small>
                                            The post-filing journey is complete for
                                            <strong>{{ $application->brand_name ?: 'this trademark' }}</strong>.
                                        </small>
                                    </div>

                                    @if ($registeredAdminDocuments->isNotEmpty())
                                        <div class="post-filing-admin-stage active">
                                            <div class="d-flex justify-content-between align-items-start gap-2">
                                                <div>
                                                    <div class="small text-primary fw-bold">Registration Documents</div>
                                                    <div class="fw-semibold">Documents sent to client</div>
                                                </div>
                                                <span class="badge bg-success">Completed</span>
                                            </div>
                                            <div class="mt-2 small">
                                                @foreach ($registeredAdminDocuments as $registeredDocument)
                                                    <a href="{{ route('admin.document.view', $registeredDocument->id) }}" target="_blank" class="d-block text-decoration-none">
                                                        <i class="bi bi-send"></i> {{ $registeredDocument->file_name }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @else
                                    <div class="alert alert-info">
                                        <small>Update each Trademark Journey Tracker stage. Every update emails the applicant with a link to their application tracking page.</small>
                                    </div>
                                @endif
                                @php
                                    $renderedPostFilingAction = false;
                                @endphp

                                @foreach ($postFilingStages as $postFilingStage)
                                    @php
                                        $postStageKey = $postFilingStage['key'];
                                        $postStageStatus = $postFilingJourney::statusFor($application, $postStageKey);
                                        $postStageDocs = $postFilingDocumentsByType->get($postFilingJourney::documentType($postStageKey), collect());
                                        $postStageAdminDocs = $postStageDocs->filter(fn ($doc) => $isPostFilingAdminDocument($doc));
                                        $postStageApplicantDocs = $postStageDocs->reject(fn ($doc) => $postStageAdminDocs->contains('id', $doc->id));
                                        $postStageIsActive = $postStageKey === $postFilingActiveStageKey;
                                        $previousIncompletePostStage = $postFilingJourney::previousIncompleteStage($application, $postStageKey);
                                        $postStageActionsLocked = filled($previousIncompletePostStage);
                                        $postStageMeta = data_get($application->workflow_meta, "post_filing_journey.stages.$postStageKey", []);
                                        $postStageApplicantNote = data_get($postStageMeta, 'applicant_note');
                                    @endphp
                                    @continue($postStageStatus === $postFilingJourney::COMPLETED || !$postStageIsActive || $postStageActionsLocked)

                                    @php
                                        $renderedPostFilingAction = true;
                                        $isAcceptedAdvertisedStage = $postStageKey === 'accepted_advertised';
                                        $isOpposedStage = $postStageStatus === $postFilingJourney::OPPOSED;
                                        $oppositionReceivedOn = data_get($postStageMeta, 'opposition_received_on');
                                        $counterStatementDueOn = data_get($postStageMeta, 'counter_statement_due_on');
                                    @endphp

                                    <div class="post-filing-admin-stage {{ $postStageIsActive ? 'active' : '' }}">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div>
                                                <div class="small text-primary fw-bold">Stage {{ $postFilingStage['number'] }}</div>
                                                <div class="fw-semibold">{{ $postFilingStage['title'] }}</div>
                                            </div>
                                            <span class="badge bg-{{ $postStageStatus === 'completed' ? 'success' : ($postStageStatus === 'processing' ? 'primary' : 'warning text-dark') }}">
                                                {{ ucwords($postStageStatus) }}
                                            </span>
                                        </div>

                                        @if ($postStageApplicantDocs->isNotEmpty())
                                            <div class="mt-2 small">
                                                <div class="fw-semibold mb-1">Applicant uploads</div>
                                                @foreach ($postStageApplicantDocs as $postStageDoc)
                                                    <a href="{{ route('admin.document.view', $postStageDoc->id) }}" target="_blank" class="d-block text-decoration-none">
                                                        <i class="bi bi-paperclip"></i> {{ $postStageDoc->file_name }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if ($postStageAdminDocs->isNotEmpty())
                                            <div class="mt-2 small">
                                                <div class="fw-semibold mb-1">Documents sent to client</div>
                                                @foreach ($postStageAdminDocs as $postStageDoc)
                                                    <a href="{{ route('admin.document.view', $postStageDoc->id) }}" target="_blank" class="d-block text-decoration-none">
                                                        <i class="bi bi-send"></i> {{ $postStageDoc->file_name }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if (filled($postStageApplicantNote))
                                            <div class="alert alert-warning py-2 mt-3 mb-0">
                                                <div class="fw-semibold">Client Note</div>
                                                <div class="small mt-1">{!! nl2br(e($postStageApplicantNote)) !!}</div>
                                            </div>
                                        @endif

                                        @if ($isAcceptedAdvertisedStage && $isOpposedStage)
                                            <div class="alert alert-danger mt-3 mb-0">
                                                <div class="fw-bold mb-2">Opposition Notice Received</div>
                                                <div><strong>Trademark:</strong> {{ $application->brand_name ?: 'N/A' }}</div>
                                                <div><strong>Application No.:</strong> {{ $application->application_number ?: 'Awaiting assignment' }}</div>
                                                <div><strong>Opposition received on:</strong> {{ $oppositionReceivedOn ? \Illuminate\Support\Carbon::parse($oppositionReceivedOn)->format('d M Y') : 'Not recorded' }}</div>
                                                <div><strong>Counter Statement Due:</strong> {{ $counterStatementDueOn ? \Illuminate\Support\Carbon::parse($counterStatementDueOn)->format('d M Y') : 'Not recorded' }}</div>
                                            </div>

                                            <div class="mt-3">
                                                <div class="fw-semibold mb-2">Related Opposition Links</div>
                                                <div class="d-flex flex-wrap gap-2">
                                                    @if ($oppositionApplication)
                                                        <a href="{{ $oppositionApplicationRoute($oppositionApplication) }}" target="_blank" class="btn btn-sm btn-outline-primary">View Opposition Application</a>
                                                    @endif

                                                    @if ($oppositionDefenceCase)
                                                        <a href="{{ route('admin.trademark-opposition.show', $oppositionDefenceCase) }}" target="_blank" class="btn btn-sm btn-outline-secondary">View Defence Case</a>
                                                    @endif

                                                    @if (! $oppositionApplication && ! $oppositionDefenceCase)
                                                        <span class="text-muted">No linked opposition or defence case is available yet.</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif

                                        <form action="{{ route('admin.post-filing.update-stage', [$application->id, $postStageKey]) }}" method="POST" enctype="multipart/form-data" class="mt-3" data-post-filing-stage-key="{{ $postStageKey }}" data-swal-confirm data-swal-title="Update journey stage?" data-swal-text="The applicant will receive an email notification for this stage update." data-swal-icon="question" data-swal-confirm-text="Yes, update">
                                            @csrf
                                            <label class="form-label fw-semibold">Stage Status</label>
                                            <select name="stage_status" class="form-select js-post-filing-stage-status" required>
                                                <option value="pending" @selected($postStageStatus === 'pending')>Pending</option>
                                                <option value="processing" @selected($postStageStatus === 'processing')>Processing</option>
                                                @if ($isAcceptedAdvertisedStage)
                                                    <option value="opposed" @selected($isOpposedStage)>Opposed</option>
                                                @endif
                                                <option value="completed" @selected($postStageStatus === 'completed')>Completed</option>
                                            </select>
                                            <div class="js-post-filing-documents-wrap mt-2">
                                                <label class="form-label fw-semibold">Documents for Client Review <span class="text-muted fw-normal">(optional)</span></label>
                                                <input type="file" name="admin_stage_documents[]" class="form-control js-post-filing-documents-input" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                <div class="form-text">Attach documents to send to the client for this stage.</div>
                                            </div>
                                            @if ($isAcceptedAdvertisedStage)
                                                <div class="js-post-filing-opposed-fields mt-2">
                                                    <label class="form-label fw-semibold">Opposition received on</label>
                                                    <input type="date" name="opposition_received_on" class="form-control mb-2 js-post-filing-opposition-date" value="{{ old('opposition_received_on', $oppositionReceivedOn ? \Illuminate\Support\Carbon::parse($oppositionReceivedOn)->format('Y-m-d') : '') }}">
                                                    <label class="form-label fw-semibold">Counter Statement Due</label>
                                                    <input type="date" name="counter_statement_due_on" class="form-control js-post-filing-counter-due-date" value="{{ old('counter_statement_due_on', $counterStatementDueOn ? \Illuminate\Support\Carbon::parse($counterStatementDueOn)->format('Y-m-d') : '') }}">
                                                </div>
                                            @endif
                                            <div class="js-post-filing-request-fields">
                                                <label class="form-label fw-semibold mt-2">Client Note <span class="text-muted fw-normal">(required when requesting documents or marking opposed)</span></label>
                                                <textarea name="stage_note" class="form-control js-post-filing-stage-note" rows="3" maxlength="1000" placeholder="Specify which supporting documents the applicant should upload...">{{ old('stage_note') }}</textarea>
                                                <div class="form-check mt-2">
                                                    <input class="form-check-input js-post-filing-request-documents" type="checkbox" name="request_documents" value="1" id="request-documents-{{ $postStageKey }}">
                                                    <label class="form-check-label" for="request-documents-{{ $postStageKey }}">
                                                        Ask applicant to upload supporting documents
                                                    </label>
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-primary w-100 mt-3 js-post-filing-submit">Update Stage & Email Client</button>
                                        </form>
                                    </div>
                                @endforeach

                                @unless ($renderedPostFilingAction || $postFilingIsFullyCompleted)
                                    <div class="alert alert-success mb-0">
                                        <small>All post-filing stages are completed.</small>
                                    </div>
                                @endunless
                            </div>
                        @else
                            <div class="alert alert-info mb-0">
                                <small>
                                    This matter is currently in <strong>{{ $application->status_label }}</strong>.
                                    Use the document tools and workflow forms above as the case progresses.
                                </small>
                            </div>
                        @endif

                        @if ($draft && !$postFilingIsFullyCompleted)
                            <div class="mt-4 p-3 bg-light rounded">
                                <div class="fw-semibold">Latest Draft</div>
                                <small class="text-muted d-block">Version {{ $draft->version_no }} • {{ ucfirst($draft->status) }}</small>
                                @if ($draft->client_comments)
                                    <div class="mt-2"><strong>Client Comments:</strong> {{ $draft->client_comments }}</div>
                                @endif
                            </div>
                        @endif

                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .admin-card-header {
            background: linear-gradient(135deg, #1d3557 0%, #2a9d8f 100%);
            color: #fff;
        }

        .admin-card-header h1,
        .admin-card-header h2,
        .admin-card-header h3,
        .admin-card-header h4,
        .admin-card-header h5 {
            color: #fff !important;
        }

        .admin-card-header h6,
        .admin-card-header small,
        .admin-card-header span,
        .admin-card-header div,
        .admin-card-header p {
            color: #fff !important;
        }

        .data-block {
            background: #f8fafc;
            border: 1px solid #e9eef5;
            border-radius: 14px;
            padding: 0.9rem 1rem;
        }

        .data-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: #6c757d;
            text-transform: uppercase;
            margin-bottom: 0.35rem;
        }

        .data-value {
            color: #1d3557;
            font-weight: 600;
            word-break: break-word;
        }

        .matter-overview-save {
            flex: 0 0 auto;
            border-color: #159b8d !important;
            background: #159b8d !important;
            color: #ffffff !important;
            font-weight: 800;
            box-shadow: 0 6px 16px rgba(21, 155, 141, 0.24);
        }

        .matter-overview-save:hover,
        .matter-overview-save:focus {
            border-color: #0d7f74 !important;
            background: #0d7f74 !important;
            color: #ffffff !important;
        }

        .matter-overview-field .form-control,
        .matter-overview-field .form-select {
            border-color: #cfdae7;
            color: #1d3557;
            font-weight: 600;
        }

        .matter-overview-field textarea.form-control {
            min-height: 112px;
            resize: vertical;
        }

        .matter-overview-field .form-control:focus,
        .matter-overview-field .form-select:focus {
            border-color: #2a9d8f;
            box-shadow: 0 0 0 0.2rem rgba(42, 157, 143, 0.14);
        }

        .matter-overview-fields {
            min-width: 0;
            margin: 0;
            padding: 0;
            border: 0;
        }

        .matter-overview-fields:disabled .form-control,
        .matter-overview-fields:disabled .form-select {
            border-color: transparent;
            background-color: transparent;
            box-shadow: none;
            color: #1d3557;
            cursor: default;
            opacity: 1;
        }

        .admin-card-header .payment-total-badge {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: 0.35rem 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.45);
            border-radius: 999px;
            background: #ffffff !important;
            color: #123d66 !important;
            font-size: 0.78rem;
            font-weight: 800;
            line-height: 1.2;
            white-space: nowrap;
            box-shadow: 0 5px 14px rgba(7, 31, 72, 0.15);
        }

        .payment-empty-state {
            align-items: center;
            background: #f8fafc;
            border: 1px dashed #cbd8e5;
            border-radius: 14px;
            color: #64748b;
            display: flex;
            gap: 0.9rem;
            padding: 1rem;
        }

        .payment-empty-state > i {
            color: #2a9d8f;
            font-size: 1.5rem;
        }

        .payment-proof-line {
            display: grid;
            gap: 0.15rem;
            margin-bottom: 0.35rem;
            min-width: 190px;
        }

        .payment-proof-line span {
            color: #6c757d;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .payment-proof-line code {
            color: #1d3557;
            font-size: 0.78rem;
            overflow-wrap: anywhere;
            white-space: normal;
        }

        @media (max-width: 767.98px) {
            .admin-payment-table {
                min-width: 850px;
            }
        }

        body.modal-open .admin-manual-upload-modal {
            z-index: 2147483000 !important;
            pointer-events: auto;
        }

        body.modal-open .admin-reupload-modal {
            z-index: 2147483000 !important;
            pointer-events: auto;
        }

        body.modal-open .admin-bulk-review-modal {
            z-index: 2147483000 !important;
            pointer-events: auto;
        }

        .admin-manual-upload-modal .modal-dialog {
            position: relative;
            z-index: 2147483001 !important;
            pointer-events: auto;
        }

        .admin-reupload-modal .modal-dialog {
            position: relative;
            z-index: 2147483001 !important;
            pointer-events: auto;
        }

        .admin-bulk-review-modal .modal-dialog {
            position: relative;
            z-index: 2147483001 !important;
            pointer-events: auto;
            margin-top: 2rem;
            margin-bottom: 2rem;
        }

        .admin-manual-upload-modal .modal-content {
            pointer-events: auto;
        }

        .admin-reupload-modal .modal-content {
            pointer-events: auto;
            border: 0;
            border-radius: 12px;
            box-shadow: 0 22px 60px rgba(15, 35, 70, 0.25);
        }

        .admin-bulk-review-modal .modal-content {
            pointer-events: auto;
            border: 0;
            border-radius: 12px;
            box-shadow: 0 22px 60px rgba(15, 35, 70, 0.25);
        }

        .admin-reupload-modal .modal-title {
            color: #1d3557;
            font-weight: 800;
        }

        .admin-bulk-review-modal .modal-title {
            color: #1d3557;
            font-weight: 800;
        }

        .post-filing-admin-panel {
            display: grid;
            gap: 0.9rem;
        }

        .post-filing-admin-stage {
            border: 1px solid #dbe6f3;
            border-radius: 10px;
            padding: 0.9rem;
            background: #fff;
        }

        .post-filing-admin-stage.active {
            border-color: #9cc2ff;
            box-shadow: 0 10px 24px rgba(20, 100, 246, 0.1);
        }

        #submitted-documents {
            scroll-margin-top: 1.5rem;
            transition: box-shadow 0.3s ease, transform 0.3s ease;
        }

        #submitted-documents.document-review-focus {
            box-shadow: 0 0 0 4px rgba(20, 157, 139, 0.18), 0 18px 42px rgba(12, 46, 82, 0.14) !important;
            transform: translateY(-2px);
        }

        .engagement-review-ready {
            display: grid;
            grid-template-columns: 3rem 1fr;
            gap: 0.9rem;
            padding: 1rem;
            border: 1px solid #a9ded5;
            border-left: 4px solid #149d8b;
            border-radius: 0.9rem;
            background: linear-gradient(145deg, #f3fbf9 0%, #e9f7f5 100%);
        }

        .engagement-review-ready-icon {
            display: grid;
            place-items: center;
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            background: #d8f2ed;
            color: #087f70;
            font-size: 1.3rem;
        }

        .engagement-review-ready-copy span {
            display: inline-flex;
            margin-bottom: 0.45rem;
            padding: 0.24rem 0.55rem;
            border-radius: 999px;
            background: #d8f2ed;
            color: #076b60;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.035em;
            text-transform: uppercase;
        }

        .engagement-review-ready-copy h6 {
            margin-bottom: 0.35rem;
            color: #17375f;
            font-weight: 700;
        }

        .engagement-review-ready-copy p {
            margin-bottom: 0;
            color: #5c6f82;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .engagement-review-ready > .btn {
            grid-column: 1 / -1;
        }

        .engagement-resend-options {
            padding: 0.85rem 1rem;
            border: 1px solid #dce4ec;
            border-radius: 0.8rem;
            background: #fff;
        }

        .engagement-resend-options summary {
            color: #40566f;
            cursor: pointer;
            font-weight: 600;
        }

        @media (max-width: 767.98px) {
            .admin-application-review-page {
                font-size: 18px;
            }

            .admin-application-review-page .form-control,
            .admin-application-review-page .form-select,
            .admin-application-review-page .btn,
            .admin-application-review-page .table,
            .admin-application-review-page .form-label,
            .admin-application-review-page .data-value {
                font-size: calc(1rem + 2px);
            }

            .admin-application-review-page .small,
            .admin-application-review-page small,
            .admin-application-review-page .data-label,
            .admin-application-review-page .form-text {
                font-size: calc(0.875rem + 2px);
            }

            .admin-application-review-page .badge {
                font-size: calc(0.75rem + 2px);
            }

            .admin-application-review-page h1.h3,
            .admin-application-review-page .h3 {
                font-size: calc(1.75rem + 2px);
            }

            .admin-application-review-page h5,
            .admin-application-review-page .h5 {
                font-size: calc(1.25rem + 2px);
            }

            .admin-application-review-page h6,
            .admin-application-review-page .h6 {
                font-size: calc(1rem + 2px);
            }

            .engagement-review-ready-copy span {
                font-size: calc(0.72rem + 2px);
            }

            .engagement-review-ready-copy h6 {
                font-size: calc(1rem + 2px);
            }

            .engagement-review-ready-copy p {
                font-size: calc(0.9rem + 2px);
            }

            .engagement-review-ready > .btn,
            .engagement-resend-options summary {
                font-size: calc(1rem + 2px);
            }
        }

        body.modal-open .modal-backdrop {
            z-index: 2147482990 !important;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const matterForm = document.querySelector('[data-matter-overview-form]');
            const matterFields = matterForm?.querySelector('[data-matter-overview-fields]');
            const matterToggle = matterForm?.querySelector('[data-matter-overview-toggle]');
            const matterCancel = matterForm?.querySelector('[data-matter-overview-cancel]');
            const matterLabel = matterForm?.querySelector('[data-matter-overview-label]');
            const matterIcon = matterForm?.querySelector('[data-matter-overview-icon]');

            if (matterForm && matterFields && matterToggle && matterCancel && matterLabel && matterIcon) {
                const setMatterEditing = (editing, focusFirst = false) => {
                    matterFields.disabled = !editing;
                    matterForm.classList.toggle('is-editing', editing);
                    matterCancel.hidden = !editing;
                    matterLabel.textContent = editing ? 'Save Changes' : 'Edit';
                    matterIcon.classList.toggle('bi-pencil-square', !editing);
                    matterIcon.classList.toggle('bi-check2-circle', editing);

                    if (editing && focusFirst) {
                        matterFields.querySelector('input, select, textarea')?.focus();
                    }
                };

                setMatterEditing(matterForm.dataset.startEditing === 'true');

                matterToggle.addEventListener('click', () => {
                    if (matterFields.disabled) {
                        setMatterEditing(true, true);
                        return;
                    }

                    matterForm.requestSubmit();
                });

                matterCancel.addEventListener('click', () => {
                    window.location.reload();
                });

                matterForm.addEventListener('submit', (event) => {
                    if (matterFields.disabled) {
                        event.preventDefault();
                        return;
                    }

                    if (!window.confirm('Save these changes to the Matter Overview?')) {
                        event.preventDefault();
                        return;
                    }

                    matterToggle.disabled = true;
                    matterLabel.textContent = 'Saving…';
                });
            }

            const filingDocCard = document.querySelector('[data-filing-documents-card]');
            const filingDocForm = document.querySelector('[data-filing-doc-review-form]');
            const filingDocSelectToggle = document.querySelector('[data-filing-doc-select-toggle]');
            const filingDocBulkActions = document.querySelector('[data-filing-doc-bulk-actions]');
            const filingDocModalElement = document.querySelector('[data-filing-doc-review-modal]');

            if (filingDocCard && filingDocForm && filingDocSelectToggle && filingDocBulkActions && filingDocModalElement) {
                if (filingDocModalElement.parentElement !== document.body) {
                    document.body.appendChild(filingDocModalElement);
                }

                const modal = new bootstrap.Modal(filingDocModalElement);
                const actionInput = filingDocForm.querySelector('[data-filing-doc-review-action]');
                const noteInput = filingDocForm.querySelector('[data-filing-doc-review-note]');
                const noteWrap = filingDocModalElement.querySelector('[data-filing-doc-review-note-wrap]');
                const noteTextarea = filingDocModalElement.querySelector('[data-filing-doc-review-note-textarea]');
                const approveCopy = filingDocModalElement.querySelector('[data-filing-doc-review-approve-copy]');
                const modalTitle = filingDocModalElement.querySelector('[data-filing-doc-review-modal-title]');
                const modalSubtitle = filingDocModalElement.querySelector('[data-filing-doc-review-modal-subtitle]');
                const modalError = filingDocModalElement.querySelector('[data-filing-doc-review-error]');
                const confirmButton = filingDocModalElement.querySelector('[data-filing-doc-review-confirm]');
                const checkboxes = Array.from(filingDocCard.querySelectorAll('[data-filing-doc-checkbox]'));
                let pendingAction = null;

                const selectedCheckboxes = () => checkboxes.filter((checkbox) => checkbox.checked);
                const setSelecting = (enabled) => {
                    filingDocCard.classList.toggle('is-selecting-documents', enabled);
                    filingDocBulkActions.classList.toggle('d-none', !enabled);
                    filingDocBulkActions.classList.toggle('d-flex', enabled);
                    checkboxes.forEach((checkbox) => {
                        checkbox.classList.toggle('d-none', !enabled);
                        if (!enabled) {
                            checkbox.checked = false;
                        }
                    });
                    filingDocSelectToggle.textContent = enabled ? 'Cancel' : 'Select';
                };

                document.querySelectorAll('[data-scroll-to-documents]').forEach((button) => {
                    button.addEventListener('click', (event) => {
                        event.preventDefault();
                        setSelecting(true);

                        const targetType = button.dataset.documentType;
                        const targetCheckbox = checkboxes.find((checkbox) => checkbox.dataset.documentType === targetType);
                        if (targetCheckbox) {
                            targetCheckbox.checked = true;
                        }

                        filingDocCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        filingDocCard.classList.add('document-review-focus');
                        window.setTimeout(() => filingDocCard.classList.remove('document-review-focus'), 1800);
                    });
                });

                filingDocSelectToggle.addEventListener('click', () => {
                    setSelecting(!filingDocCard.classList.contains('is-selecting-documents'));
                });

                filingDocBulkActions.querySelectorAll('[data-filing-doc-open-modal]').forEach((button) => {
                    button.addEventListener('click', () => {
                        modalError.classList.add('d-none');
                        modalError.textContent = '';
                        const selected = selectedCheckboxes();

                        if (!selected.length) {
                            modalError.textContent = 'Please select at least one document.';
                            modalError.classList.remove('d-none');
                            pendingAction = null;
                            modalTitle.textContent = 'Review Selected Documents';
                            modalSubtitle.textContent = '';
                            noteWrap.hidden = true;
                            approveCopy.hidden = true;
                            confirmButton.className = 'btn btn-primary';
                            confirmButton.textContent = 'Continue';
                            modal.show();
                            return;
                        }

                        pendingAction = button.dataset.filingDocOpenModal;
                        const isReupload = pendingAction === 'reupload_requested';
                        modalTitle.textContent = isReupload ? 'Ask for Reupload' : 'Approve Selected Documents';
                        modalSubtitle.textContent = `${selected.length} document${selected.length === 1 ? '' : 's'} selected`;
                        noteWrap.hidden = !isReupload;
                        approveCopy.hidden = isReupload;
                        noteTextarea.value = '';
                        confirmButton.className = isReupload ? 'btn btn-danger' : 'btn btn-success';
                        confirmButton.textContent = isReupload ? 'Send Reupload Request' : 'Mark as Approved';
                        modal.show();
                    });
                });

                confirmButton.addEventListener('click', () => {
                    modalError.classList.add('d-none');
                    modalError.textContent = '';

                    if (!pendingAction || !selectedCheckboxes().length) {
                        modalError.textContent = 'Please select at least one document.';
                        modalError.classList.remove('d-none');
                        return;
                    }

                    const note = noteTextarea.value.trim();
                    if (pendingAction === 'reupload_requested' && note.length < 10) {
                        modalError.textContent = 'Please enter at least 10 characters explaining the reupload request.';
                        modalError.classList.remove('d-none');
                        return;
                    }

                    actionInput.value = pendingAction;
                    noteInput.value = note;
                    filingDocForm.submit();
                });

                filingDocModalElement.addEventListener('shown.bs.modal', () => {
                    filingDocModalElement.style.zIndex = '2147483000';
                    document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
                        backdrop.style.zIndex = '2147482990';
                    });
                });
            }

            document.querySelectorAll('.admin-manual-upload-modal, .admin-reupload-modal').forEach((modal) => {
                document.body.appendChild(modal);

                modal.addEventListener('shown.bs.modal', () => {
                    modal.style.zIndex = '2147483000';
                    document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
                        backdrop.style.zIndex = '2147482990';
                    });
                });

                modal.querySelector('form')?.addEventListener('submit', (event) => {
                    if (!event.currentTarget.checkValidity()) {
                        return;
                    }

                    const button = event.currentTarget.querySelector('[data-manual-upload-submit]');
                    if (button) {
                        window.LegalBruzButtonLoading?.set(button, 'Uploading...');
                    }
                });
            });

            document.querySelectorAll('.post-filing-admin-stage form').forEach((form) => {
                const statusSelect = form.querySelector('.js-post-filing-stage-status');
                const stageDocumentsWrap = form.querySelector('.js-post-filing-documents-wrap');
                const stageDocumentsInput = form.querySelector('.js-post-filing-documents-input');
                const requestFields = form.querySelector('.js-post-filing-request-fields');
                const requestDocuments = form.querySelector('.js-post-filing-request-documents');
                const stageNote = form.querySelector('.js-post-filing-stage-note');
                const opposedFields = form.querySelector('.js-post-filing-opposed-fields');
                const oppositionDate = form.querySelector('.js-post-filing-opposition-date');
                const counterDueDate = form.querySelector('.js-post-filing-counter-due-date');
                const submitButton = form.querySelector('.js-post-filing-submit');

                if (!statusSelect || !requestFields) {
                    return;
                }

                const syncRequestFields = () => {
                    const isPending = statusSelect.value === 'pending';
                    const isProcessing = statusSelect.value === 'processing';
                    const isOpposed = statusSelect.value === 'opposed';
                    const isCompleted = statusSelect.value === 'completed';
                    const isRegisteredStage = form.dataset.postFilingStageKey === 'registered';
                    const allowClientNote = isProcessing || isOpposed;
                    const allowStageDocuments = isProcessing || isOpposed || (isRegisteredStage && isCompleted);

                    requestFields.classList.toggle('opacity-50', !allowClientNote);
                    stageDocumentsWrap?.classList.toggle('opacity-50', !allowStageDocuments);

                    if (!isProcessing && requestDocuments) {
                        requestDocuments.checked = false;
                    }

                    if (stageDocumentsInput) {
                        stageDocumentsInput.disabled = !allowStageDocuments;
                        if (!allowStageDocuments) {
                            stageDocumentsInput.value = '';
                        }
                    }

                    if (stageNote) {
                        stageNote.disabled = !allowClientNote;

                        if (!allowClientNote) {
                            stageNote.value = '';
                        }
                    }

                    if (requestDocuments) {
                        requestDocuments.disabled = !isProcessing;
                    }

                    if (submitButton) {
                        submitButton.disabled = isPending;
                    }

                    if (opposedFields) {
                        opposedFields.classList.toggle('d-none', !isOpposed);
                    }

                    if (oppositionDate) {
                        oppositionDate.required = isOpposed;
                        oppositionDate.disabled = !isOpposed;
                        if (!isOpposed) {
                            oppositionDate.value = '';
                        }
                    }

                    if (counterDueDate) {
                        counterDueDate.required = isOpposed;
                        counterDueDate.disabled = !isOpposed;
                        if (!isOpposed) {
                            counterDueDate.value = '';
                        }
                    }
                };

                statusSelect.addEventListener('change', syncRequestFields);
                syncRequestFields();
            });
        });
    </script>
@endsection
