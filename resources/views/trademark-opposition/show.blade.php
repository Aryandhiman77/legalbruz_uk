@extends('layouts.app')

@section('content')
    @php
        $workflow = \App\Support\TrademarkOppositionWorkflow::class;
        $displayTimezone = config('app.timezone', 'Europe/London');
        $formatDateTime = fn ($timestamp, string $format = 'd M Y, h:i A T') => $timestamp
            ? \Illuminate\Support\Carbon::parse($timestamp)->timezone($displayTimezone)->format($format)
            : null;
        $allDocumentTypes = $requiredDocuments + $optionalDocuments;
        $latestDocuments = $case->documents->sortByDesc('id')->unique('document_type')->keyBy('document_type');
        $rejectedDocuments = $latestDocuments->filter(fn ($document) => $document->review_status === 'rejected');
        $hasRejectedDocuments = $rejectedDocuments->isNotEmpty();
        $uploadedRequired = $latestDocuments
            ->filter(fn ($document) => $document->is_required && $document->review_status !== 'rejected')
            ->pluck('document_type')
            ->all();
        $requiredComplete = $case->hasRequiredDocuments();
        $deadlineClass = 'deadline-' . ($case->deadline_status ?: 'red');
        $actionOnly = $actionOnly ?? false;
        $oppositionGroundDescriptions = [
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
        $riskOptions = [
            'Low Risk' => [
                'subheading' => 'Weak opposition',
                'content' => 'Strong defence available.',
            ],
            'Medium Risk' => [
                'subheading' => 'Requires substantial evidence',
                'content' => 'Possible hearing.',
            ],
            'High Risk' => [
                'subheading' => 'Strong prior rights claimed by opponent',
                'content' => 'Settlement may be considered.',
            ],
        ];
        $defenceOutcomeReady = $case->current_admin_status === $workflow::ADMIN_MATTER_CLOSED
            && $case->filing_acknowledgment_path
            && in_array($case->defence_case_status, [
            \App\Support\TrademarkOppositionWorkflow::DEFENCE_CASE_SUCCEEDED,
            \App\Support\TrademarkOppositionWorkflow::DEFENCE_CASE_FAILED_TO_SUCCEED,
            \App\Support\TrademarkOppositionWorkflow::DEFENCE_CASE_CLOSED,
        ], true);
        $defenceOutcomeLabel = $defenceOutcomeReady
            ? \App\Support\TrademarkOppositionWorkflow::defenceOutcomeLabel($case->defence_case_status)
            : null;
        $finalClientMessage = $defenceOutcomeReady ? trim((string) $case->final_client_message) : '';
        $defenceOutcomeCopy = $finalClientMessage !== '' ? $finalClientMessage : match ($defenceOutcomeReady ? $case->defence_case_status : null) {
            \App\Support\TrademarkOppositionWorkflow::DEFENCE_CASE_SUCCEEDED => 'Our legal team has marked this defence matter as successful.',
            \App\Support\TrademarkOppositionWorkflow::DEFENCE_CASE_FAILED_TO_SUCCEED => 'Our legal team has marked this defence matter as unsuccessful.',
            \App\Support\TrademarkOppositionWorkflow::DEFENCE_CASE_CLOSED => 'The registry matter has been closed.',
            default => null,
        };
        $finalOutcomeContent = $defenceOutcomeReady ? match ($case->final_outcome) {
            \App\Support\TrademarkOppositionWorkflow::DEFENCE_CASE_SUCCEEDED => [
                'class' => 'is-success',
                'icon' => 'bi-check-circle-fill',
                'status' => 'Defence Case Succeeded',
                'headline' => 'Your trademark application has successfully overcome the opposition proceedings.',
                'information' => 'Our legal team has successfully defended your trademark application. The opposition matter has been concluded in your favour. If the Registry has issued the final order, you can download it below.',
                'footer' => 'No action is required from you. This matter has been successfully completed.',
            ],
            \App\Support\TrademarkOppositionWorkflow::DEFENCE_CASE_FAILED_TO_SUCCEED => [
                'class' => 'is-danger',
                'icon' => 'bi-x-circle-fill',
                'status' => 'Defence Case Unsuccessful',
                'headline' => 'The Registry has concluded the opposition proceedings against your trademark.',
                'information' => 'The opposition proceedings have concluded and the outcome was not in favour of your trademark application. Please review the Registry order for complete details.',
                'footer' => 'This matter has been closed. If you wish to explore further legal remedies, please contact Legal Bruz.',
            ],
            \App\Support\TrademarkOppositionWorkflow::ADMIN_SETTLEMENT_CLOSED => [
                'class' => 'is-warning',
                'icon' => 'bi-check-circle-fill',
                'status' => 'Matter Settled',
                'headline' => 'The opposition proceedings have been concluded through settlement between the parties.',
                'information' => 'This matter has been closed following a settlement. Please review the settlement documents shared by our legal team.',
                'footer' => 'No further action is required. This matter has been successfully closed.',
            ],
            \App\Support\TrademarkOppositionWorkflow::ADMIN_WITHDRAWN => [
                'class' => 'is-neutral',
                'icon' => 'bi-dash-circle-fill',
                'status' => 'Matter Withdrawn',
                'headline' => 'This opposition matter has been withdrawn and is now closed.',
                'information' => 'The opposition proceedings have been withdrawn. Please review the attached documents for the final record.',
                'footer' => 'No further action is required.',
            ],
            default => [
                'class' => 'is-info',
                'icon' => 'bi-info-circle-fill',
                'status' => 'Matter Closed',
                'headline' => 'This opposition matter has been concluded.',
                'information' => 'The matter has been closed. Please review the final notes and attached documents shared by our legal team.',
                'footer' => 'No further action is required.',
            ],
        } : null;
        $requestedEvidenceLabels = collect($case->requested_evidence_types ?? []);
        $requestedEvidenceSlugs = $requestedEvidenceLabels
            ->map(fn ($label) => \Illuminate\Support\Str::slug($label, '_'))
            ->values()
            ->all();
        $latestEvidenceByType = $case->evidence
            ->where('file_path', '!=', 'metadata')
            ->sortByDesc('id')
            ->unique('evidence_type')
            ->keyBy('evidence_type');
        $latestEvidenceMeta = $case->evidence
            ->where('evidence_type', 'evidence_intake_details')
            ->where('file_path', 'metadata')
            ->sortByDesc('id')
            ->first();
        $evidenceDraftMeta = $latestEvidenceMeta ? json_decode($latestEvidenceMeta->file_name, true) : [];
        $evidenceDraftMeta = is_array($evidenceDraftMeta) ? $evidenceDraftMeta : [];
        $firstUseDateValue = old('first_use_date', $case->first_use_date?->format('Y-m-d') ?: ($evidenceDraftMeta['First Use Date'] ?? ''));
        $currentlyInUseValue = old('currently_in_use', $evidenceDraftMeta['Currently In Use'] ?? 'yes');
        $usedContinuouslyValue = old('used_continuously', $evidenceDraftMeta['Used Continuously'] ?? 'yes');
        $annualSalesValue = old('annual_sales', $evidenceDraftMeta['Annual Sales'] ?? '');
        $marketingSpendValue = old('marketing_spend', $evidenceDraftMeta['Marketing Spend'] ?? '');
        $otherRelevantDetailsValue = old('other_relevant_details', $evidenceDraftMeta['Other Relevant Details'] ?? '');
        $evidenceUploadItems = collect([
            ['label' => 'Invoices', 'request' => 'Invoices', 'key' => 'invoices'],
            ['label' => 'GST Certificate', 'request' => 'GST Certificate', 'key' => 'gst_certificate'],
            ['label' => 'Packaging', 'request' => 'Packaging', 'key' => 'packaging'],
            ['label' => 'Product Photos', 'request' => 'Photos of Product', 'key' => 'photos_of_product'],
            ['label' => 'Catalogue', 'request' => 'Catalogue', 'key' => 'catalogue'],
            ['label' => 'Website Screenshots', 'request' => 'Website Screenshots', 'key' => 'website_screenshots'],
            ['label' => 'Domain Registration', 'request' => 'Domain Registration', 'key' => 'domain_registration'],
            ['label' => 'Social Media Evidence', 'request' => 'Social Media Evidence', 'key' => 'social_media_evidence'],
            ['label' => 'Amazon Listings', 'request' => 'Amazon Listings', 'key' => 'amazon_listings'],
            ['label' => 'Flipkart Listings', 'request' => 'Flipkart Listings', 'key' => 'flipkart_listings'],
            ['label' => 'Advertising Material', 'request' => 'Advertising Material', 'key' => 'advertising_material'],
            ['label' => 'Client Purchase Orders', 'request' => 'Client Purchase Orders', 'key' => 'client_purchase_orders'],
            ['label' => 'Sales Figures', 'request' => 'Sales Figures', 'key' => 'sales_figures'],
            ['label' => 'Marketing Spend Proof', 'request' => 'Marketing Spend Proof', 'key' => 'marketing_spend_proof'],
        ]);
        $requestedEvidenceItems = $evidenceUploadItems
            ->filter(fn ($item) => in_array(\Illuminate\Support\Str::slug($item['request'], '_'), $requestedEvidenceSlugs, true))
            ->values();
        $rejectedRequestedEvidenceItems = $requestedEvidenceItems
            ->filter(function ($item) use ($latestEvidenceByType) {
                $requestSlug = \Illuminate\Support\Str::slug($item['request'], '_');
                $existingEvidence = $latestEvidenceByType->get($requestSlug) ?? $latestEvidenceByType->get($item['key']);

                return $existingEvidence?->review_status === 'rejected';
            })
            ->values();
        $visibleRequestedEvidenceItems = $rejectedRequestedEvidenceItems->isNotEmpty()
            ? $rejectedRequestedEvidenceItems
            : $requestedEvidenceItems;
        $hasEvidenceStageData = $case->grounds->isNotEmpty()
            || $requestedEvidenceLabels->isNotEmpty()
            || filled($case->evidence_request_note)
            || $evidenceDraftMeta !== [];
        $registryStageRequestLabels = collect($case->third_party_evidence_requests ?? [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values();
        $latestRegistryStageDocuments = $case->evidence
            ->where('file_path', '!=', 'metadata')
            ->where('uploaded_by', 'client')
            ->filter(fn ($document) => \Illuminate\Support\Str::startsWith($document->evidence_type, 'third_party_requested_evidence_'))
            ->sortByDesc('id')
            ->unique('evidence_type')
            ->values();
        $allLatestRegistryStageDocumentsReviewed = $latestRegistryStageDocuments->isNotEmpty()
            && $latestRegistryStageDocuments->every(fn ($document) => $document->review_status === 'reviewed');
        $rejectedRegistryStageDocuments = $latestRegistryStageDocuments
            ->filter(fn ($document) => $document->review_status === 'rejected')
            ->values();
        $hasRegistryStageReupload = $rejectedRegistryStageDocuments->isNotEmpty();
        $defendHearingStatuses = [
            $workflow::ADMIN_HEARING_PREPARATION,
            $workflow::ADMIN_HEARING_SCHEDULED,
            $workflow::ADMIN_HEARING_ADJOURNED,
            $workflow::ADMIN_HEARING_COMPLETED,
        ];
        $latestHearingHistory = $case->statusHistories
            ->filter(fn ($history) => in_array($history->new_status, $defendHearingStatuses, true) && $history->changed_by === 'admin')
            ->sortByDesc('id')
            ->first();
        $latestHearingNote = (string) ($latestHearingHistory?->note ?? '');
        $hearingAdjournmentReason = preg_match('/(?:^|\\s)Adjournment reason:\\s*(.*?)(?=\\sHearing outcome:|$)/', $latestHearingNote, $matches)
            ? trim($matches[1])
            : '';
        $oppositionPaymentCoupons = auth()->check()
            ? \App\Models\DiscountCoupon::availableForPayment('opposition_defence_package', auth()->id())
            : collect();
        $oppositionAutoCoupon = auth()->check()
            ? \App\Models\DiscountCoupon::autoApplyForPayment('opposition_defence_package', auth()->id())
            : null;
        $defendedApplication = auth()->check()
            ? \App\Models\Application::query()
                ->where('user_id', auth()->id())
                ->where(function ($query) use ($case) {
                    $query->where('opposition_defence_case_id', $case->id)
                        ->orWhere('application_number', $case->application_number);
                })
                ->first()
            : null;
        $oppositionOriginalAmount = (float) ($case->package_price ?: 0);
        $oppositionPayableAmount = $oppositionAutoCoupon
            ? $oppositionAutoCoupon->discountedAmountFor($oppositionOriginalAmount)
            : $oppositionOriginalAmount;
        $oppositionDiscountAmount = max($oppositionOriginalAmount - $oppositionPayableAmount, 0);
        $hasVerifiedOppositionPayment = $case->payment_status === 'paid'
            && filled($case->payment_reference)
            && filled($case->transaction_id)
            && filled($case->paid_at);

        $statusOrder = [
            ['Case Opened', 'Your opposition defence case has been created.', 'bi-file-earmark-check', $workflow::ADMIN_APPLICATION_RECEIVED],
            ['Legal Review', 'Our team reviews the opposition notice, grounds, and risk.', 'bi-search', $workflow::ADMIN_OPPOSITION_REVIEW],
            ['Evidence Collection', 'Upload proof of use, invoices, sales, marketing, and marketplace evidence.', 'bi-cloud-upload', $workflow::ADMIN_EVIDENCE_COLLECTION],
            ['Pricing & Payment', 'Review the defence package, accept the disclaimer, and complete payment.', 'bi-credit-card', $workflow::CLIENT_PRICING_PAYMENT],
            ['Draft Approval', 'Review the counter statement draft, approve it, or request changes.', 'bi-file-earmark-text', $workflow::ADMIN_CLIENT_APPROVAL],
            ['Document Filed', 'Your counter statement has been filed with the Trademark Registry.', 'bi-send', $workflow::ADMIN_COUNTER_STATEMENT_FILED],
            ['Awaiting Evidence Stage', 'The matter is waiting for the next evidence stage update from the Registry.', 'bi-hourglass-split', $workflow::ADMIN_AWAITING_EVIDENCE_STAGE],
            ['Evidence Stage', 'Evidence may be filed by the opponent, applicant, or in reply.', 'bi-folder2-open', $workflow::ADMIN_EVIDENCE_BY_OPPONENT],
            ['Hearing Stage', 'A hearing may be scheduled or prepared before the Trademark Registry.', 'bi-megaphone', $workflow::ADMIN_HEARING_PREPARATION],
            ['Decision Awaited', 'The matter is waiting for the final decision from the Trademark Registry.', 'bi-clock-history', $workflow::ADMIN_DECISION_AWAITED],
            ['Matter Closed', 'The matter has been closed with a final outcome.', 'bi-check-circle', $workflow::ADMIN_MATTER_CLOSED],
        ];

        $stageDocumentItem = static fn ($record, string $kind, string $label, string $sender) => [
            'record' => $record,
            'kind' => $kind,
            'label' => $label,
            'sender' => $sender,
            'file_name' => $record->file_name,
            'file_type' => strtoupper($record->file_type ?: pathinfo($record->file_name, PATHINFO_EXTENSION) ?: 'FILE'),
            'file_size' => (int) ($record->file_size ?? 0),
            'created_at' => $record->created_at,
        ];
        $evidenceDocumentName = static function ($document, string $fallback = 'Additional Document') use ($evidenceTypes): string {
            $savedName = trim((string) ($document->review_note ?? ''));
            if ($savedName !== '') {
                return $savedName;
            }

            $evidenceType = trim((string) ($document->evidence_type ?? ''));
            if (\Illuminate\Support\Str::startsWith($evidenceType, 'third_party_requested_evidence_')) {
                return \Illuminate\Support\Str::headline(
                    \Illuminate\Support\Str::after($evidenceType, 'third_party_requested_evidence_')
                );
            }

            $stageLabels = [
                'legal_review_optional_document' => 'Legal Review Supporting Document',
                'counter_statement_additional_document' => 'Draft Supporting Document',
                'counter_statement_filing_additional_document' => 'Filing Supporting Document',
                'counter_statement_tracking_additional_document' => 'Registry Supporting Document',
                'hearing_notice_document' => 'Hearing Notice',
                'decision_additional_document' => 'Decision Supporting Document',
                'final_order_document' => 'Final Order',
            ];

            return $stageLabels[$evidenceType]
                ?? ($evidenceTypes[$evidenceType] ?? $fallback);
        };
        $formatStageFileSize = static function (?int $bytes): string {
            $bytes = max(0, (int) $bytes);
            return $bytes >= 1048576
                ? number_format($bytes / 1048576, 1) . ' MB'
                : number_format(max(1, (int) ceil($bytes / 1024))) . ' KB';
        };
        $stageDocuments = collect([
            0 => $latestDocuments->values()->map(fn ($document) => $stageDocumentItem(
                $document,
                'document',
                $allDocumentTypes[$document->document_type] ?? $document->file_name,
                $document->uploaded_by === 'admin' ? 'admin' : 'client'
            )),
            2 => $case->evidence
                ->where('file_path', '!=', 'metadata')
                ->reject(fn ($document) => in_array($document->evidence_type, [
                    'legal_review_optional_document',
                    'counter_statement_additional_document',
                    'counter_statement_filing_additional_document',
                    'counter_statement_tracking_additional_document',
                    'counter_statement_tracking_internal_document',
                    'final_order_document',
                    'final_order_internal_document',
                ], true) || \Illuminate\Support\Str::startsWith($document->evidence_type, 'third_party_requested_evidence_'))
                ->sortByDesc('id')
                ->unique('evidence_type')
                ->map(fn ($document) => $stageDocumentItem(
                    $document,
                    'evidence',
                    $evidenceTypes[$document->evidence_type] ?? $document->file_name,
                    $document->uploaded_by === 'admin' ? 'admin' : 'client'
                )),
            1 => $case->evidence
                ->where('evidence_type', 'legal_review_optional_document')
                ->map(fn ($document) => $stageDocumentItem($document, 'evidence', $evidenceDocumentName($document), 'admin')),
            4 => collect($case->draft_path ? [[
                'record' => null,
                'kind' => 'draft',
                'label' => $case->draft_display_name,
                'sender' => 'admin',
                'file_name' => $case->draft_display_name,
                'file_type' => strtoupper(pathinfo($case->draft_display_name, PATHINFO_EXTENSION) ?: 'FILE'),
                'file_size' => Storage::disk('public')->exists($case->draft_path) ? Storage::disk('public')->size($case->draft_path) : 0,
                'created_at' => $case->updated_at,
            ]] : [])->merge(
                $case->evidence
                    ->where('evidence_type', 'counter_statement_additional_document')
                    ->map(fn ($document) => $stageDocumentItem($document, 'evidence', $evidenceDocumentName($document), 'admin'))
            ),
            5 => collect($case->filing_acknowledgment_path ? [[
                'record' => null,
                'kind' => 'filing-acknowledgment',
                'label' => 'Filing Acknowledgment',
                'sender' => 'admin',
                'file_name' => $case->filing_acknowledgment_name ?: 'Filing Acknowledgment',
                'file_type' => strtoupper(pathinfo((string) $case->filing_acknowledgment_name, PATHINFO_EXTENSION) ?: 'FILE'),
                'file_size' => Storage::disk('public')->exists($case->filing_acknowledgment_path) ? Storage::disk('public')->size($case->filing_acknowledgment_path) : 0,
                'created_at' => $case->updated_at,
            ]] : [])->merge(
                $case->evidence
                    ->where('evidence_type', 'counter_statement_filing_additional_document')
                    ->map(fn ($document) => $stageDocumentItem($document, 'evidence', $evidenceDocumentName($document), 'admin'))
            ),
            7 => $case->evidence
                ->where('evidence_type', 'counter_statement_tracking_additional_document')
                ->map(fn ($document) => $stageDocumentItem($document, 'evidence', $evidenceDocumentName($document), 'admin'))
                ->merge(
                    $case->evidence
                        ->where('file_path', '!=', 'metadata')
                        ->where('uploaded_by', 'client')
                        ->filter(fn ($document) => \Illuminate\Support\Str::startsWith($document->evidence_type, 'third_party_requested_evidence_'))
                        ->map(fn ($document) => $stageDocumentItem($document, 'evidence', $evidenceDocumentName($document), 'client'))
                ),
            8 => $case->evidence
                ->where('evidence_type', 'hearing_notice_document')
                ->map(fn ($document) => $stageDocumentItem($document, 'evidence', $evidenceDocumentName($document), 'admin')),
            10 => $case->evidence
                ->where('evidence_type', 'final_order_document')
                ->map(fn ($document) => $stageDocumentItem($document, 'evidence', $document->review_note ?: 'Final Order', 'admin'))
                ->merge(
                    $case->evidence
                        ->where('evidence_type', 'decision_additional_document')
                        ->map(fn ($document) => $stageDocumentItem($document, 'evidence', $evidenceDocumentName($document), 'admin'))
                ),
        ]);
        $statusRank = [
            $workflow::ADMIN_APPLICATION_RECEIVED => 0,
            $workflow::ADMIN_DOCUMENTS_PENDING => 0,
            $workflow::ADMIN_OPPOSITION_REVIEW => 1,
            $workflow::ADMIN_LEGAL_ANALYSIS => 1,
            $workflow::ADMIN_EVIDENCE_COLLECTION => 2,
            $workflow::ADMIN_LEGAL_ANALYSIS_COMPLETED => 1,
            $workflow::CLIENT_PRICING_PAYMENT => 3,
            'Payment Completed' => 3,
            $workflow::ADMIN_COUNTER_STATEMENT_DRAFTING => 4,
            $workflow::ADMIN_DRAFT_UNDER_LEGAL_REVIEW => 4,
            $workflow::ADMIN_CLIENT_APPROVAL_PENDING => 4,
            $workflow::ADMIN_CLIENT_APPROVAL => 4,
            $workflow::ADMIN_READY_FOR_FILING => 4,
            $workflow::ADMIN_COUNTER_STATEMENT_FILED => 5,
            $workflow::ADMIN_AWAITING_EVIDENCE_STAGE => 6,
            $workflow::ADMIN_EVIDENCE_BY_OPPONENT => 7,
            $workflow::ADMIN_EVIDENCE_BY_APPLICANT => 7,
            $workflow::ADMIN_EVIDENCE_IN_REPLY => 7,
            $workflow::ADMIN_EVIDENCE_FILED => 7,
            $workflow::ADMIN_HEARING_PREPARATION => 8,
            $workflow::ADMIN_HEARING_SCHEDULED => 8,
            $workflow::ADMIN_HEARING_ADJOURNED => 8,
            $workflow::ADMIN_HEARING_COMPLETED => 8,
            $workflow::ADMIN_DECISION_AWAITED => 9,
            $workflow::ADMIN_FINAL_OUTCOME => 9,
            $workflow::ADMIN_MATTER_CLOSED => 10,
            $workflow::ADMIN_OPPOSITION_ALLOWED => 10,
            $workflow::ADMIN_OPPOSITION_DISMISSED => 10,
            $workflow::ADMIN_SETTLEMENT_CLOSED => 10,
        ];
        $activeRank = $statusRank[$case->current_admin_status] ?? 0;
        if (!$requiredComplete) {
            $activeRank = 0;
        } elseif ($case->package_name && !$hasVerifiedOppositionPayment && $activeRank < 3) {
            $activeRank = 3;
        } elseif ($hasVerifiedOppositionPayment && $activeRank < 4) {
            $activeRank = 4;
        } elseif ($case->payment_status === 'paid' && $activeRank < 3) {
            $activeRank = 3;
        }
        $displayClientStage = $case->package_name && !$hasVerifiedOppositionPayment
            ? $workflow::CLIENT_PRICING_PAYMENT
            : $workflow::clientStageForAdminStatus($case->current_admin_status);
        $actionCenterDocumentStage = match (true) {
            in_array($case->current_admin_status, [
                $workflow::ADMIN_EVIDENCE_BY_OPPONENT,
                $workflow::ADMIN_EVIDENCE_BY_APPLICANT,
                $workflow::ADMIN_EVIDENCE_IN_REPLY,
                $workflow::ADMIN_EVIDENCE_FILED,
            ], true) => 7,
            in_array($case->current_admin_status, [
                $workflow::ADMIN_HEARING_PREPARATION,
                $workflow::ADMIN_HEARING_SCHEDULED,
                $workflow::ADMIN_HEARING_ADJOURNED,
                $workflow::ADMIN_HEARING_COMPLETED,
            ], true) => 8,
            $case->current_admin_status === $workflow::ADMIN_DECISION_AWAITED => null,
            $case->current_admin_status === $workflow::ADMIN_FINAL_OUTCOME => null,
            $case->current_admin_status === $workflow::ADMIN_MATTER_CLOSED => 10,
            filled($case->filing_acknowledgment_path) && $case->current_admin_status === $workflow::ADMIN_COUNTER_STATEMENT_FILED => 5,
            filled($case->filing_acknowledgment_path) && !in_array($case->current_admin_status, [
                $workflow::ADMIN_DECISION_AWAITED,
                $workflow::ADMIN_FINAL_OUTCOME,
                $workflow::ADMIN_MATTER_CLOSED,
            ], true) => 5,
            default => null,
        };
        $actionCenterAdminDocuments = $actionCenterDocumentStage === null
            ? collect()
            : $stageDocuments->get($actionCenterDocumentStage, collect())
                ->where('sender', 'admin')
                ->values();
        if ($hasRejectedDocuments) {
            $actionType = 'documents';
            $actionTitle = 'Reupload Rejected Documents';
            $actionCopy = 'Some documents need corrections. Review the admin notes below and reupload the highlighted files.';
        } elseif (!$requiredComplete) {
            $actionType = 'documents';
            $actionTitle = 'Upload Required Documents';
            $actionCopy = 'Upload the Notice of Opposition, TM Application Acknowledgment, and Logo / Trademark Copy so our team can begin review.';
        } elseif ($case->current_admin_status === $workflow::ADMIN_EVIDENCE_COLLECTION && $hasEvidenceStageData) {
            $actionType = 'evidence';
            $actionTitle = 'Submit Evidence';
            $actionCopy = 'Add use proof, sales, invoices, marketplace, social, and marketing evidence requested by the legal team.';
        } elseif ($case->current_admin_status === $workflow::ADMIN_EVIDENCE_COLLECTION) {
            $actionType = 'empty-stage';
            $actionTitle = 'No action is required right now.';
            $actionCopy = 'Our legal team is preparing the evidence requirements for this stage. You will be notified when documents or information are required.';
        } elseif ($case->package_name && !$hasVerifiedOppositionPayment) {
            $actionType = 'payment';
            $actionTitle = 'Complete Payment';
            $actionCopy = 'Review the opposition defence package, accept the disclaimer, and complete payment.';
        } elseif ($hasVerifiedOppositionPayment && !$case->draft_path) {
            $actionType = 'payment-complete';
            $actionTitle = 'No Action Required';
            $actionCopy = 'Your payment has been received. Our team will notify you through email when the counter statement draft is ready.';
        } elseif ($case->risk_level && $case->current_admin_status === $workflow::ADMIN_LEGAL_ANALYSIS_COMPLETED) {
            $actionType = 'risk';
            $actionTitle = 'Risk Assessment';
            $actionCopy = 'Your legal review is ready. Please review the assessment shared by our legal team.';
        } elseif ($case->draft_path && $case->client_approval_status === 'changes_requested') {
            $actionType = 'changes-requested';
            $actionTitle = 'Changes Requested';
            $actionCopy = 'Your comments have been sent to the legal team. A revised draft package will appear here when it is ready.';
        } elseif ($case->draft_path && $case->client_approval_status !== 'approved') {
            $actionType = 'draft';
            $actionTitle = 'Review Draft';
            $actionCopy = 'Review the counter statement draft. You can approve it or request changes with comments.';
        } elseif ($defenceOutcomeReady && $case->defence_case_status === $workflow::DEFENCE_CASE_SUCCEEDED) {
            $actionType = 'waiting';
            $actionTitle = 'Defence Case Succeeded';
            $actionCopy = 'Your defence case has succeeded. Please review the latest filing and stage documents shared below for the final record.';
        } elseif ($defenceOutcomeReady && $case->defence_case_status === $workflow::DEFENCE_CASE_FAILED_TO_SUCCEED) {
            $actionType = 'waiting';
            $actionTitle = 'Defence Case Failed to Succeed';
            $actionCopy = 'Your defence case did not succeed. Please review the latest case documents and updates shared by our team.';
        } elseif ($case->current_admin_status === $workflow::ADMIN_DECISION_AWAITED) {
            $actionType = 'waiting';
            $actionTitle = 'Decision Awaited';
            $actionCopy = 'The hearing has been completed. Your trademark opposition defence matter is now waiting for the final decision from the Trademark Registry.';
        } elseif (in_array($case->current_admin_status, [
            $workflow::ADMIN_FINAL_OUTCOME,
            $workflow::ADMIN_MATTER_CLOSED,
            $workflow::ADMIN_OPPOSITION_ALLOWED,
            $workflow::ADMIN_OPPOSITION_DISMISSED,
            $workflow::ADMIN_SETTLEMENT_CLOSED,
        ], true)) {
            $actionType = 'waiting';
            $actionTitle = 'Matter Closed';
            $actionCopy = $finalClientMessage !== '' ? $finalClientMessage : 'The matter has been closed with a final outcome.';
        } elseif (($case->third_party_evidence_pending && $registryStageRequestLabels->isNotEmpty()) || $hasRegistryStageReupload) {
            $actionType = 'registry-documents';
            $actionTitle = $hasRegistryStageReupload ? 'Reupload Requested Documents' : 'Upload Requested Documents';
            $actionCopy = $case->third_party_evidence_message ?: 'Upload the documents requested by the legal team for the current registry stage.';
        } elseif ($latestRegistryStageDocuments->isNotEmpty() && !$allLatestRegistryStageDocumentsReviewed) {
            $actionType = 'waiting';
            $actionTitle = 'Evidence Submitted for Review';
            $actionCopy = 'Your requested Evidence Stage documents have been uploaded. Our legal team is reviewing them and will notify you when the matter moves to Hearing Stage.';
        } elseif (in_array($case->current_admin_status, [
            $workflow::ADMIN_HEARING_PREPARATION,
            $workflow::ADMIN_HEARING_SCHEDULED,
            $workflow::ADMIN_HEARING_ADJOURNED,
            $workflow::ADMIN_HEARING_COMPLETED,
        ], true)) {
            $actionType = 'waiting';
            $actionTitle = 'Hearing Stage';
            $actionCopy = 'The matter is now in hearing stage. Add hearing details, upload hearing notice, request required documents, and notify the client.';
        } elseif ($case->filing_acknowledgment_path) {
            $actionType = 'filed';
            $actionTitle = 'Counter Statement Filed';
            $actionCopy = 'Your Counter Statement has been filed successfully. The matter is now active and awaiting the next procedural stage from the Trademark Registry.';
        } else {
            $actionType = 'waiting';
            $actionTitle = 'Legal Team Working';
            $actionCopy = 'Your case is active. The next action will appear here as soon as it is required.';
        }
        $showCounterStatementDeadline = in_array($actionType, ['documents', 'payment', 'payment-complete', 'risk', 'draft', 'changes-requested'], true);
    @endphp

    <style>
        .opp-outcome-banner{display:flex;gap:12px;align-items:flex-start;margin:0 0 16px;padding:14px 16px;border:1px solid #dbe7f7;border-radius:14px;background:#f7fbff}
        .opp-outcome-banner.is-success{border-color:#b8e6cb;background:#f2fbf5}
        .opp-outcome-banner.is-danger{border-color:#f3c2c7;background:#fff6f6}
        .opp-outcome-banner.is-warning{border-color:#f6d99a;background:#fff9e8}
        .opp-outcome-banner.is-neutral{border-color:#d7dee9;background:#f8fafc}
        .opp-outcome-banner.is-info{border-color:#bfdbfe;background:#eff6ff}
        .opp-outcome-banner-icon{position:relative;width:40px;height:40px;border-radius:50%;display:block;background:#e7eef8;color:#1e4c8f;font-size:1rem;line-height:1;flex:0 0 auto}
        .opp-outcome-banner.is-success .opp-outcome-banner-icon{background:#dff6e8;color:#177245}
        .opp-outcome-banner.is-danger .opp-outcome-banner-icon{background:#fde6e8;color:#b42318}
        .opp-outcome-banner.is-warning .opp-outcome-banner-icon{background:#fff3c4;color:#a16207}
        .opp-outcome-banner.is-neutral .opp-outcome-banner-icon{background:#e5e7eb;color:#64748b}
        .opp-outcome-banner.is-info .opp-outcome-banner-icon{background:#dbeafe;color:#1d4ed8}
        .opp-outcome-banner-icon i{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);line-height:1}
        .opp-outcome-banner span{display:block;color:#66758b;font-size:.76rem;font-weight:900;text-transform:uppercase;letter-spacing:.04em}
        .opp-outcome-banner strong{display:block;color:#1d2b41;font-size:1rem;line-height:1.35;margin-top:2px}
        .opp-outcome-banner p{margin:5px 0 0;color:#55657d;line-height:1.5}
        .opp-final-info{margin:0 0 16px;padding:16px;border:1px solid #dbe7f7;border-radius:14px;background:#fbfdff}
        .opp-final-info h3{margin:0 0 8px;color:#14294b;font-weight:900;font-size:1.05rem}
        .opp-final-info p{margin:0;color:#56677f;line-height:1.55}
        .opp-final-links{display:flex;flex-wrap:wrap;gap:10px;margin:12px 0 0}
        .opp-final-links a,.opp-final-links button{border:1px solid #b8cdfd;background:#fff;color:#075be0;border-radius:9px;padding:.5rem .75rem;font-weight:900;text-decoration:none}
        .opp-final-links button{cursor:pointer}
        .opp-final-footer{margin-top:14px;padding:12px 14px;border-radius:12px;background:#eef6ff;color:#244469;font-weight:800}
        .opp-page {
            max-width: 1180px;
            margin: -18px auto 34px;
            padding: 0 18px;
            color: #26364f;
            font-size: 0.9rem;
        }

        .opp-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 340px;
            gap: 0.75rem;
            align-items: start;
        }

        .opp-layout.is-action-only {
            display: block;
        }

        .opp-layout.is-action-only .opp-action-card {
            max-width: none;
        }

        .opp-layout.is-action-only .opp-action-card.is-evidence-upload {
            box-shadow: 0 14px 34px rgba(15, 36, 68, 0.08);
        }

        .opp-status-card {
            grid-column: 1;
            grid-row: 1;
        }

        .opp-below-tracker {
            display: contents;
        }

        .opp-status-card,
        .opp-action-card,
        .opp-payment-summary-card,
        .opp-files-card {
            background: #fff;
            border: 1px solid #e6ebf2;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(15, 36, 68, 0.07);
            overflow: hidden;
            padding: 0;
        }

        .opp-status-head,
        .opp-action-head {
            background: linear-gradient(135deg, #294d78 0%, #2d4a73 100%);
            color: #fff;
            padding: 0.75rem 0.9rem;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.9rem;
            align-items: center;
        }

        .opp-title-wrap {
            display: flex;
            align-items: center;
            gap: 0.9rem;
        }

        .opp-title-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(255,255,255,.14);
            display: grid;
            place-items: center;
            font-size: 1.25rem;
            flex: 0 0 44px;
        }

        .opp-status-head h1,
        .opp-action-head h2 {
            margin: 0;
            color: #fff;
            font-size: 1.35rem;
            font-weight: 900;
            line-height: 1.2;
        }

        .opp-action-head h2 {
            font-size: 1.1rem;
        }

        .opp-status-sub {
            display: inline-flex;
            gap: 0.42rem;
            align-items: center;
            color: #fff;
            text-decoration: none;
            font-weight: 800;
            font-size: 0.88rem;
            border-bottom: 1px solid rgba(255,255,255,.52);
            padding-bottom: 0.06rem;
            margin-top: 0.2rem;
        }

        .opp-head-badges {
            display: grid;
            justify-items: end;
            gap: 0.45rem;
        }

        .opp-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 7px;
            padding: 0.25rem 0.55rem;
            background: #fff;
            color: #0b67df;
            font-weight: 800;
            font-size: 0.82rem;
            line-height: 1.3;
        }

        .opp-pill-muted {
            background: rgba(255,255,255,.18);
            color: #fff;
        }

        .opp-status-body {
            padding: 0.75rem 1.05rem;
        }

        .opp-timeline {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .opp-step-row {
            position: relative;
            display: grid;
            grid-template-columns: 42px 1fr auto;
            gap: 0.75rem;
            align-items: center;
            min-height: 58px;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e5eaf2;
        }

        .opp-step-row:last-child {
            border-bottom: 0;
        }

        .opp-step-row::before {
            content: "";
            position: absolute;
            left: 20px;
            top: 44px;
            bottom: -16px;
            border-left: 2px dashed #d8e1ee;
        }

        .opp-step-row:last-child::before {
            display: none;
        }

        .opp-step-icon {
            position: relative;
            z-index: 1;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: #f1f4f8;
            color: #7b8794;
            font-size: 1rem;
            box-shadow: 0 8px 22px rgba(15, 36, 68, 0.08);
        }

        .opp-step-icon .mini-check {
            position: absolute;
            right: -4px;
            top: 2px;
            width: 18px;
            height: 18px;
            border-radius: 999px;
            background: #13a268;
            color: #fff;
            font-size: 0.72rem;
            display: grid;
            place-items: center;
            border: 2px solid #fff;
        }

        .opp-step-row.is-complete .opp-step-icon {
            background: #eafaf2;
            color: #11915c;
        }

        .opp-step-row.is-active .opp-step-icon {
            background: #eaf1ff;
            color: #1367ff;
        }

        .opp-step-copy h3 {
            margin: 0 0 0.16rem;
            color: #14294b;
            font-size: 1rem;
            font-weight: 900;
            line-height: 1.2;
        }

        .opp-step-title-row { display:flex; align-items:center; gap:.8rem; flex-wrap:wrap; }
        .opp-step-title-row h3 { margin:0; }

        .opp-step-copy p {
            margin: 0;
            color: #4d5b70;
            font-size: 0.84rem;
            line-height: 1.35;
            max-width: 620px;
        }

        .opp-step-state {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-items: end;
            gap: 0.3rem;
            min-width: 210px;
            text-align: right;
        }

        .opp-status-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 7px;
            padding: 0.3rem 0.58rem;
            font-weight: 800;
            font-size: 0.78rem;
            line-height: 1.2;
        }

        .badge-complete {
            background: #ddf8e9;
            color: #137747;
            border: 1px solid #97e2bd;
        }

        .badge-active {
            background: #eaf1ff;
            color: #1367ff;
            border: 1px solid #9dc0ff;
        }

        .badge-pending {
            background: #fff4dc;
            color: #8a560e;
            border: 1px solid #ffc66d;
        }

        .opp-step-date {
            display: block;
            color: #6c7487;
            font-weight: 700;
            font-size: 0.78rem;
        }

        .opp-stage-documents-link { display:inline-flex; align-items:center; justify-content:flex-end; gap:.35rem; border:0; padding:.1rem 0; background:transparent; color:#149b7e; font-size:.8rem; line-height:1.2; font-weight:900; white-space:nowrap; cursor:pointer; }
        .opp-stage-documents-link:hover { color:#0d765f; text-decoration:underline; }
        .opp-stage-doc-modal { position:fixed; inset:0; z-index:3050; display:none; align-items:center; justify-content:center; padding:18px; background:rgba(15,35,60,.5); }
        .opp-stage-doc-modal.is-visible { display:flex; }
        .opp-stage-doc-dialog { width:min(720px,100%); max-height:min(760px,90vh); overflow:auto; border-radius:12px; background:#fff; box-shadow:0 24px 70px rgba(15,35,60,.3); }
        .opp-stage-doc-head { position:sticky; top:0; z-index:1; display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; padding:1rem 1.2rem; border-bottom:1px solid #e3e9f2; background:#fff; }
        .opp-stage-doc-head h3 { margin:0; color:#14294b; font-size:1.08rem; }
        .opp-stage-doc-head p { margin:.2rem 0 0; color:#66758b; font-size:.82rem; }
        .opp-stage-doc-close { border:0; background:transparent; color:#607089; font-size:1.4rem; cursor:pointer; }
        .opp-stage-doc-body { display:grid; gap:1.2rem; padding:1.2rem; }
        .opp-stage-doc-group { padding:0; }
        .opp-stage-doc-group h4 { margin:0 0 .6rem; color:#536176; font-size:.78rem; font-weight:900; letter-spacing:.03em; text-transform:uppercase; }
        .opp-stage-doc-list { overflow:hidden; border:1px solid #dfe6ef; border-radius:9px; }
        .opp-stage-doc-row { display:flex; align-items:center; gap:.8rem; padding:.85rem; border-bottom:1px solid #e7ecf3; }
        .opp-stage-doc-row:last-child { border-bottom:0; }
        .opp-stage-doc-icon { width:38px; height:38px; flex:0 0 38px; display:grid; place-items:center; border-radius:8px; background:#edf9f1; color:#16814a; }
        .opp-stage-doc-copy { min-width:0; flex:1; }
        .opp-stage-doc-copy strong { display:block; color:#14294b; font-size:.88rem; overflow-wrap:anywhere; }
        .opp-stage-doc-copy span { display:block; margin-top:.15rem; color:#66758b; font-size:.76rem; }
        .opp-stage-doc-actions { display:flex; gap:.45rem; }
        .opp-stage-doc-actions a { display:inline-flex; align-items:center; gap:.35rem; min-height:34px; padding:0 .7rem; border:1px solid #a9c1ff; border-radius:6px; color:#0755d9; font-size:.78rem; font-weight:800; text-decoration:none; }
        .opp-action-admin-docs { margin: 0 0 16px; }
        .opp-action-admin-docs h3 { margin: 0 0 .65rem; color: #203e68; font-size: .95rem; font-weight: 900; }
        .opp-action-admin-docs .opp-stage-doc-list { background: #fff; }
        .opp-action-admin-docs .opp-stage-doc-row { padding: .75rem .85rem; }
        .opp-admin-request-note { display:flex; gap:.75rem; align-items:flex-start; margin:0 0 16px; padding:13px 14px; border-left:4px solid #2a9d8f; border-radius:10px; background:#f2fbf8; color:#203e68; }
        .opp-admin-request-note i { color:#2a9d8f; font-size:1.1rem; margin-top:.1rem; }
        .opp-admin-request-note strong { display:block; margin-bottom:3px; font-weight:900; }
        .opp-admin-request-note p { margin:0; color:#52627a; line-height:1.5; font-weight:650; }
        .opp-note-to-admin-box { margin-top:16px; padding:14px; border:1px solid #dfe8f4; border-radius:12px; background:#fbfdff; }
        .opp-note-to-admin-box .opp-label { margin-bottom:8px; }
        .opp-note-to-admin-box .opp-textarea { width:100%; min-height:96px; resize:vertical; border:1px solid #d6e0ee; border-radius:9px; padding:12px 13px; color:#1f2937; background:#fff; font:inherit; line-height:1.45; }
        .opp-note-to-admin-box .opp-textarea:focus { outline:2px solid rgba(42,157,143,.18); border-color:#2a9d8f; }
        @media(max-width:640px){.opp-stage-doc-row{align-items:flex-start;flex-wrap:wrap}.opp-stage-doc-actions{width:100%;padding-left:46px}}

        .opp-action-card,
        .opp-files-card {
            margin-top: 0;
        }

        .opp-action-card {
            grid-column: 1;
            grid-row: 2;
        }

        .opp-payment-summary-card {
            grid-column: 1;
            grid-row: 2;
        }

        .opp-payment-summary-card + .opp-action-card {
            grid-row: 3;
            margin-top: 0.75rem;
        }

        .opp-files-card {
            grid-column: 2;
            grid-row: 1 / span 2;
        }

        .opp-action-body {
            padding: 0.85rem 1rem;
        }

        .opp-action-card.is-document-upload .opp-action-body {
            padding: 1.35rem 1.45rem 1.45rem;
        }

        .opp-action-card.is-evidence-upload .opp-action-body {
            padding: 1.35rem 1.45rem 1.45rem;
        }

        .opp-action-title {
            display: flex;
            align-items: center;
            gap: 0.9rem;
        }

        .opp-action-title-icon {
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: #fff;
            color: #1464f6;
            font-size: 1rem;
            box-shadow: 0 8px 22px rgba(15, 36, 68, 0.12);
        }

        .opp-action-status-pill {
            gap: 0.4rem;
            padding: 0.42rem 0.7rem;
            font-size: 0.84rem;
            font-weight: 800;
        }

        .opp-action-status-pill i {
            width: 20px;
            height: 20px;
            border-radius: 999px;
            display: inline-grid;
            place-items: center;
            background: #1464f6;
            color: #fff;
            font-size: 0.72rem;
        }

        .opp-upload-intro {
            display: grid;
            grid-template-columns: 42px minmax(0, 1fr);
            gap: 1rem;
            align-items: start;
            margin-bottom: 1.35rem;
        }

        .opp-upload-intro-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: #eaf2ff;
            color: #1464f6;
            font-size: 1.25rem;
        }

        .opp-upload-intro h3 {
            margin: 0 0 0.35rem;
            color: #14294b;
            font-size: 1.12rem;
            font-weight: 800;
            line-height: 1.25;
        }

        .opp-upload-intro p {
            max-width: 780px;
            margin: 0;
            color: #536176;
            font-size: 0.95rem;
            font-weight: 500;
            line-height: 1.45;
        }

        .opp-action-body h3 {
            font-size: 1rem;
            margin: 0 0 0.35rem;
            color: #14294b;
            font-weight: 900;
        }

        .opp-draft-review-intro { margin-bottom: 1.2rem; }
        .opp-draft-review-intro h3 { font-size: 1.18rem; margin-bottom: .35rem; }
        .opp-draft-section { margin-top: 1.25rem; }
        .opp-draft-section-title { display:block; margin:0 0 .65rem; color:#536176; font-size:.78rem; font-weight:900; letter-spacing:.025em; text-transform:uppercase; }
        .opp-sent-documents { overflow:hidden; border:1px solid #dfe6ef; border-radius:9px; background:#fff; }
        .opp-sent-document { display:flex; align-items:center; gap:1rem; padding:1rem 1.1rem; border-bottom:1px solid #e7ecf3; }
        .opp-sent-document:last-child { border-bottom:0; }
        .opp-sent-document-icon { width:46px; height:46px; flex:0 0 46px; display:grid; place-items:center; border-radius:9px; background:#edf9f1; color:#17813b; font-size:1.35rem; }
        .opp-sent-document-copy { min-width:0; flex:1; }
        .opp-sent-document-copy strong { display:block; color:#14294b; font-size:.92rem; font-weight:900; overflow-wrap:anywhere; }
        .opp-sent-document-copy span { display:block; margin-top:.2rem; color:#566780; font-size:.82rem; }
        .opp-sent-document-actions { display:flex; gap:.65rem; flex-wrap:wrap; }
        .opp-doc-action { min-height:40px; display:inline-flex; align-items:center; justify-content:center; gap:.45rem; padding:0 1rem; border:1px solid #9ebcff; border-radius:7px; color:#0755d9; background:#fff; font-weight:800; text-decoration:none; }
        .opp-doc-action:hover { color:#0647b4; background:#f4f7ff; }
        .opp-draft-decisions { display:flex; flex-wrap:wrap; gap:.8rem; margin-top:1.1rem; }
        .opp-decision-button { width:min(340px,100%); min-height:56px; display:flex; align-items:center; gap:.7rem; border-radius:8px; padding:.65rem .8rem; text-align:left; cursor:pointer; }
        .opp-decision-button i { width:30px; height:30px; flex:0 0 30px; display:grid; place-items:center; border-radius:999px; font-size:1rem; }
        .opp-decision-button strong,.opp-decision-button span { display:block; }
        .opp-decision-button strong { font-size:.9rem; }
        .opp-decision-button span { margin-top:.1rem; font-size:.75rem; }
        .opp-decision-approve { border:1px solid #159447; background:#159447; color:#fff; }
        .opp-decision-approve i { background:rgba(255,255,255,.14); }
        .opp-decision-change { border:1px solid #f26a2e; background:#fff; color:#e85217; }
        .opp-decision-change i { background:#fff0e9; }
        .opp-draft-modal { position:fixed; inset:0; z-index:3100; display:none; align-items:center; justify-content:center; padding:18px; background:rgba(15,35,60,.5); }
        .opp-draft-modal.is-visible { display:flex; }
        .opp-draft-modal-dialog { width:min(540px,100%); overflow:hidden; border-radius:12px; background:#fff; box-shadow:0 24px 70px rgba(15,35,60,.3); }
        .opp-draft-modal-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1rem 1.2rem; border-bottom:1px solid #e6ebf2; }
        .opp-draft-modal-head h3 { margin:0; color:#14294b; font-size:1.08rem; }
        .opp-draft-modal-close { border:0; background:transparent; color:#607089; font-size:1.35rem; }
        .opp-draft-modal-body { padding:1.2rem; }
        .opp-draft-modal-body p { margin:0 0 1rem; color:#536176; line-height:1.5; }
        .opp-draft-modal-actions { display:flex; justify-content:flex-end; gap:.7rem; margin-top:1rem; }
        @media(max-width:720px){.opp-sent-document{align-items:flex-start;flex-wrap:wrap}.opp-sent-document-actions{width:100%;padding-left:62px}.opp-decision-button{width:100%}}

        .opp-action-body p {
            font-size: 0.9rem;
            line-height: 1.4;
            margin-bottom: 0.85rem;
        }

        .opp-muted {
            color: #536176;
            font-weight: 600;
        }

        .opp-field {
            padding: 0.7rem 0.8rem;
            border: 1px solid #edf1f7;
            border-radius: 8px;
            background: #fbfdff;
            margin-bottom: 0.7rem;
        }

        .opp-field span {
            display: block;
            color: #6b7890;
            font-size: 0.72rem;
            font-weight: 900;
            text-transform: uppercase;
            margin-bottom: 0.15rem;
        }

        .opp-risk-assessment {
            border: 1px solid #dfe8f4;
            border-radius: 10px;
            background: #fbfdff;
            padding: 1rem;
            margin-top: 0.8rem;
        }

        .opp-risk-assessment h3 {
            margin: 0 0 0.35rem;
            font-size: 1.05rem;
            font-weight: 800;
            color: #14294b;
        }

        .opp-risk-assessment strong {
            display: block;
            margin-bottom: 0.2rem;
            font-size: 1rem;
            color: #14294b;
        }

        .opp-risk-assessment p {
            margin: 0;
            color: #536176;
            font-weight: 500;
        }

        .opp-risk-note {
            margin-top: 0.9rem;
            padding-top: 0.9rem;
            border-top: 1px solid #e7edf5;
        }

        .opp-legal-note-panel {
            border: 1px solid #dfe8f4;
            border-radius: 10px;
            background: #fbfdff;
            padding: 1rem;
            margin: 1rem 0 1.15rem;
        }

        .opp-legal-note-panel span {
            display: block;
            color: #6b7890;
            font-size: 0.72rem;
            font-weight: 900;
            text-transform: uppercase;
            margin-bottom: 0.35rem;
        }

        .opp-legal-note-panel p {
            margin: 0;
            color: #26364f;
            font-weight: 500;
            line-height: 1.5;
        }

        .opp-package-card {
            overflow: hidden;
            margin: 1.15rem 0 .85rem;
            border: 1px solid #cdddf2;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 8px 20px rgba(24, 58, 104, .06);
        }

        .opp-package-card-head {
            display: grid;
            grid-template-columns: 46px minmax(0, 1fr) auto;
            gap: .85rem;
            align-items: center;
            padding: 1rem;
            background: linear-gradient(135deg, #f7fbff, #edf5ff);
            border-bottom: 1px solid #dce8f6;
        }

        .opp-package-card-icon {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            background: #dfeeff;
            color: #145cc4;
            font-size: 1.25rem;
        }

        .opp-package-card-title > span,
        .opp-package-includes {
            display: block;
            color: #65758c;
            font-size: .72rem;
            font-weight: 900;
            letter-spacing: .035em;
            text-transform: uppercase;
        }

        .opp-package-card-title h3 {
            margin: .2rem 0 0;
            color: #14294b;
            font-size: 1.02rem;
            line-height: 1.3;
        }

        .opp-package-card-price {
            min-width: 115px;
            text-align: right;
        }

        .opp-package-card-price .opp-price-original {
            display: block;
            margin: 0 0 .1rem;
            color: #8995a8;
            font-size: .78rem;
        }

        .opp-package-card-price strong {
            display: block;
            color: #13834c;
            font-size: 1.35rem;
            line-height: 1.2;
        }

        .opp-package-card-body { padding: .9rem 1rem 1rem; }
        .opp-package-card-body ul { display: grid; gap: .55rem; margin: .7rem 0 0; padding: 0; list-style: none; }
        .opp-package-card-body li { display: flex; align-items: flex-start; gap: .55rem; color: #34445d; line-height: 1.4; }
        .opp-package-card-body li i { margin-top: .12rem; color: #159447; }

        .opp-payment-consent {
            display: flex;
            align-items: flex-start;
            gap: .6rem;
            margin: .85rem 0 0;
            color: #34445d;
            font-weight: 650;
            line-height: 1.45;
        }

        .opp-payment-consent input { margin-top: .22rem; flex: 0 0 auto; }

        @media (max-width: 640px) {
            .opp-package-card-head { grid-template-columns: 42px minmax(0, 1fr); }
            .opp-package-card-icon { width: 42px; height: 42px; }
            .opp-package-card-price { grid-column: 1 / -1; padding-top: .75rem; border-top: 1px solid #dce8f6; text-align: left; }
        }

        .opp-coupon-box {
            border: 1px solid #dbeafe;
            border-radius: 10px;
            background: #f8fbff;
            padding: 0.85rem;
            margin-bottom: 0.85rem;
        }

        .opp-coupon-box label {
            display: flex;
            align-items: flex-start;
            gap: 0.55rem;
            margin: 0.45rem 0 0;
            color: #26364f;
            font-weight: 500;
        }

        .opp-coupon-code {
            display: inline-flex;
            border-radius: 999px;
            background: #eaf1ff;
            color: #174ea6;
            padding: 0.25rem 0.55rem;
            font-weight: 700;
            font-size: 0.78rem;
        }

        .opp-price-line {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            align-items: center;
        }

        .opp-price-original {
            color: #8b95a7;
            text-decoration: line-through;
        }

        .deadline-green { border-color: #8ed8a3; background: #effaf2; color: #166534; }
        .deadline-yellow { border-color: #f6d365; background: #fff8db; color: #854d0e; }
        .deadline-red { border-color: #f5a5a5; background: #fff0f0; color: #991b1b; }

        .opp-deadline-panel {
            position: relative;
            overflow: hidden;
            display: grid;
            grid-template-columns: 48px minmax(0, 1fr) auto;
            gap: 0.85rem;
            align-items: center;
            padding: 0.8rem 1rem;
            border: 1px solid;
            border-radius: 8px;
            margin-bottom: 0.9rem;
        }

        .opp-deadline-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: rgba(18, 129, 74, 0.11);
            color: #12814a;
            font-size: 1.2rem;
        }

        .opp-deadline-panel > div > span {
            display: block;
            color: #166534;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }

        .opp-deadline-panel strong {
            color: #166534;
            font-size: 1rem;
            font-weight: 800;
            line-height: 1.25;
        }

        .opp-deadline-ghost {
            color: rgba(18, 129, 74, 0.22);
            font-size: 2rem;
        }

        .opp-warning {
            padding: 0.7rem 0.8rem;
            border: 1px solid #f3d28a;
            border-radius: 8px;
            background: #fff8e5;
            color: #62430c;
            font-size: 0.86rem;
            line-height: 1.45;
            margin-bottom: 1rem;
        }

        .opp-action-empty-state {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            padding: 1.05rem 1.15rem;
            border: 1px solid #d8e7ff;
            border-radius: 14px;
            background: linear-gradient(135deg, #f7fbff, #eef6ff);
            color: #536176;
            margin-top: 1rem;
        }

        .opp-action-empty-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            flex: 0 0 42px;
            background: #e8f1ff;
            color: #1554c0;
            font-size: 1.25rem;
        }

        .opp-action-empty-state strong {
            display: block;
            color: #14294b;
            font-size: 1rem;
            font-weight: 900;
            margin-bottom: .2rem;
        }

        .opp-action-empty-state p {
            margin: 0;
            line-height: 1.5;
            font-weight: 650;
        }

        .opp-admin-note {
            border-color: #fdba74;
            background: #fff7ed;
            color: #7c2d12;
        }

        .opp-admin-note span,
        .opp-admin-note strong {
            color: #7c2d12;
        }

        .opp-note-warning {
            display: grid;
            grid-template-columns: 42px minmax(0, 1fr);
            gap: 0.85rem;
            align-items: center;
            padding: 0.85rem 1rem;
            border: 1px solid #f4c66b;
            border-radius: 8px;
            background: #fff8e7;
            color: #62430c;
            font-size: 0.92rem;
            font-weight: 400;
            line-height: 1.45;
            margin-bottom: 1rem;
        }

        .opp-note-warning strong {
            font-weight: 800;
        }

        .opp-note-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: #fff0c7;
            color: #e0a10a;
            font-size: 1.15rem;
        }

        .opp-form-grid {
            display: grid;
            grid-template-columns: repeat(3,minmax(0,1fr));
            gap: 0.75rem;
            margin-top: 0.85rem;
        }

        .opp-upload-grid {
            gap: 0.9rem;
            margin-top: 0;
        }

        .opp-upload-card {
            border: 1px solid #dbe4f0;
            border-radius: 8px;
            background: #fff;
            padding: 0.9rem;
            box-shadow: 0 8px 18px rgba(15, 36, 68, 0.04);
            transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
        }

        .opp-upload-card.has-file {
            border-color: #8ed8a3;
            background: #f0fbf4;
            box-shadow: 0 10px 22px rgba(22, 101, 52, 0.08);
        }

        .opp-upload-card.is-too-large,
        .opp-upload-card.is-rejected {
            border-color: #f5a5a5;
            background: #fff0f0;
            box-shadow: 0 10px 22px rgba(153, 27, 27, 0.08);
        }

        .opp-label {
            display: block;
            margin-bottom: 0.3rem;
            color: #14294b;
            font-size: 0.8rem;
            font-weight: 900;
            line-height: 1.25;
        }

        .opp-upload-card .opp-label {
            font-size: 0.88rem;
            font-weight: 800;
            margin-bottom: 0.7rem;
        }

        .opp-input {
            box-sizing: border-box;
            width: 100%;
            min-height: 38px;
            border: 1px solid #cad6e8;
            border-radius: 7px;
            padding: 0.42rem 0.55rem;
            font-size: 0.9rem;
        }

        .opp-file-native {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
            pointer-events: none;
        }

        .opp-file-picker {
            min-height: 42px;
            width: 100%;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid #d4deec;
            border-radius: 7px;
            background: #fbfdff;
            padding: 0.45rem 0.8rem;
            cursor: pointer;
            color: #26364f;
            font-weight: 400;
            transition: border-color 0.18s ease, background 0.18s ease;
        }

        .opp-upload-card.has-file .opp-file-picker {
            border-color: #8ed8a3;
            background: #f7fef9;
        }

        .opp-upload-card.is-too-large .opp-file-picker,
        .opp-upload-card.is-rejected .opp-file-picker {
            border-color: #f08f8f;
            background: #fffafa;
        }

        .opp-upload-card.has-file .opp-file-picker i,
        .opp-upload-card.has-file .opp-file-choose {
            color: #12814a;
        }

        .opp-upload-card.is-too-large .opp-file-picker i,
        .opp-upload-card.is-too-large .opp-file-choose,
        .opp-upload-card.is-rejected .opp-file-picker i,
        .opp-upload-card.is-rejected .opp-file-choose {
            color: #b91c1c;
        }

        .opp-file-picker i {
            color: #0757c7;
            font-size: 1.05rem;
            flex: 0 0 auto;
        }

        .opp-file-choose {
            color: #0757c7;
            font-size: 0.9rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .opp-file-name {
            min-width: 0;
            overflow: hidden;
            color: #26364f;
            font-size: 0.9rem;
            font-weight: 400;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .opp-file-size-error {
            display: none;
            margin-top: 0.45rem;
            color: #b91c1c;
            font-size: 0.78rem;
            font-weight: 700;
            line-height: 1.35;
        }

        .opp-upload-card.is-too-large .opp-file-size-error {
            display: block;
        }

        .opp-btn {
            min-height: 38px;
            border: 0;
            border-radius: 8px;
            padding: 0 0.9rem;
            background: #2563eb;
            color: #fff;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            font-size: 0.88rem;
        }

        .opp-upload-submit {
            min-height: 44px;
            padding: 0 1.25rem;
            font-size: 0.96rem;
            font-weight: 500;
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.16);
        }

        .opp-btn:hover {
            color: #fff;
            background: #1d4ed8;
        }

        .opp-btn:disabled {
            cursor: not-allowed;
            opacity: 0.72;
        }

        .opp-btn-green { background: #24963d; }
        .opp-btn-gray { background: #6b7280; }

        .opp-actions {
            display: flex;
            gap: 0.65rem;
            flex-wrap: wrap;
            margin-top: 0.85rem;
        }

        .opp-upload-actions {
            border-top: 1px solid #e5eaf2;
            padding-top: 1rem;
            align-items: center;
        }

        .opp-secure-note {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            color: #536176;
            font-size: 0.84rem;
            font-weight: 500;
        }

        .opp-secure-note i {
            color: #1464f6;
            font-size: 1rem;
        }

        .opp-upload-error {
            display: none;
            margin-top: 0.85rem;
            padding: 0.7rem 0.85rem;
            border: 1px solid #f5a5a5;
            border-radius: 8px;
            background: #fff0f0;
            color: #991b1b;
            font-size: 0.86rem;
            font-weight: 500;
            line-height: 1.4;
        }

        .opp-upload-error.is-visible {
            display: block;
        }

        .opp-upload-error.is-info {
            border-color: #93c5fd;
            background: #eff6ff;
            color: #1d4ed8;
        }

        .opp-evidence-shell {
            color: #162b4d;
        }

        .opp-evidence-top {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: flex-start;
            margin-bottom: 1.1rem;
        }

        .opp-evidence-top h2 {
            margin: 0 0 0.35rem;
            color: #14294b;
            font-size: 1.2rem;
            font-weight: 900;
            line-height: 1.15;
        }

        .opp-evidence-top p {
            margin: 0;
            color: #52617a;
            font-size: 0.94rem;
            font-weight: 600;
        }

        .opp-stage-card {
            min-width: 180px;
            border: 1px solid #bfd4fb;
            border-radius: 8px;
            background: #f8fbff;
            padding: 0.65rem 0.85rem;
        }

        .opp-stage-card span {
            display: block;
            color: #4e6079;
            font-size: 0.74rem;
            font-weight: 900;
            margin-bottom: 0.2rem;
        }

        .opp-stage-card strong {
            color: #1367ff;
            font-size: 0.92rem;
            font-weight: 900;
        }

        .opp-evidence-deadline {
            margin-bottom: 1rem;
            padding: 0.7rem 0.9rem;
            grid-template-columns: 46px minmax(0, 1fr) auto;
            border-radius: 8px;
            background: linear-gradient(90deg, #edf9f1 0%, #f8fffb 100%);
        }

        .opp-evidence-deadline .opp-deadline-icon {
            width: 40px;
            height: 40px;
            border-radius: 999px;
            font-size: 1.1rem;
        }

        .opp-evidence-deadline strong {
            display: block;
            margin-bottom: 0.25rem;
            font-size: 1.15rem;
        }

        .opp-deadline-helper {
            color: #177044;
            font-size: 0.86rem;
            font-weight: 700;
        }

        .opp-evidence-deadline .opp-deadline-ghost {
            font-size: 3.6rem;
        }

        .opp-evidence-alert {
            display: flex;
            gap: 0.8rem;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.8rem 1rem;
            border: 1px solid #f4c66b;
            border-radius: 8px;
            background: #fff8e7;
            color: #7a4f0c;
            font-size: 0.88rem;
            font-weight: 600;
        }

        .opp-evidence-alert i {
            color: #f0a70b;
            font-size: 1.35rem;
        }

        .opp-evidence-panels {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .opp-evidence-panel,
        .opp-use-panel {
            border: 1px solid #cfddf2;
            border-radius: 8px;
            background: #fbfdff;
            padding: 1rem;
        }

        .opp-evidence-panel.is-note {
            min-height: 0;
            padding: 0.9rem 1rem;
            background:
                linear-gradient(110deg, rgba(255,255,255,.92), rgba(255,255,255,.76)),
                linear-gradient(135deg, #f7fbff 0%, #eef5ff 100%);
        }

        .opp-evidence-panel.is-note .opp-panel-title {
            margin-bottom: 0.55rem;
        }

        .opp-panel-title {
            display: flex;
            gap: 0.7rem;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .opp-panel-title i {
            color: #1464f6;
            font-size: 1.2rem;
        }

        .opp-panel-title h3 {
            margin: 0;
            color: #14294b;
            font-size: 1rem;
            font-weight: 900;
        }

        .opp-evidence-panel p {
            margin: 0;
            max-width: none;
            color: #536176;
            font-size: 0.9rem;
            line-height: 1.55;
        }

        .opp-ground-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.8rem;
        }

        .opp-ground-card {
            min-height: 86px;
            border: 1px solid #c9d6e9;
            border-radius: 8px;
            background: #fff;
            padding: 0.85rem;
        }

        .opp-ground-card strong {
            display: block;
            margin-bottom: 0.45rem;
            color: #17653b;
            font-size: 0.9rem;
            font-weight: 900;
        }

        .opp-ground-card p {
            margin: 0;
            color: #405170;
            font-size: 0.84rem;
            line-height: 1.45;
        }

        .opp-use-panel {
            margin-bottom: 1.15rem;
        }

        .opp-use-panel h3,
        .opp-evidence-upload-title h3 {
            margin: 0 0 0.25rem;
            color: #14294b;
            font-size: 1rem;
            font-weight: 900;
        }

        .opp-use-panel p,
        .opp-evidence-upload-title p {
            margin: 0 0 0.9rem;
            color: #536176;
            font-size: 0.86rem;
            font-weight: 600;
        }

        .opp-use-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem 1.15rem;
            align-items: start;
        }

        .opp-use-field {
            display: grid;
            grid-template-rows: 48px 48px;
            min-width: 0;
        }

        .opp-use-field.is-textarea {
            grid-template-rows: 48px 88px;
        }

        .opp-use-field .opp-label {
            display: flex;
            align-items: flex-end;
            margin-bottom: 0.45rem;
            min-height: 42px;
            line-height: 1.25;
        }

        .opp-input-group {
            display: flex;
            align-items: center;
            min-height: 48px;
            border: 1px solid #cad6e8;
            border-radius: 7px;
            background: #fff;
            overflow: hidden;
        }

        .opp-input-prefix {
            width: 38px;
            align-self: stretch;
            display: grid;
            place-items: center;
            border-right: 1px solid #e3e9f3;
            color: #52617a;
            font-weight: 900;
        }

        .opp-input-group .opp-input {
            border: 0;
            border-radius: 0;
            min-height: 46px;
        }

        .opp-radio-row {
            display: flex;
            gap: 1.35rem;
            align-items: center;
            min-height: 48px;
            border: 1px solid #cad6e8;
            border-radius: 7px;
            background: #fff;
            padding: 0.35rem 0.75rem;
        }

        .opp-radio-row label {
            display: inline-flex;
            gap: 0.45rem;
            align-items: center;
            margin: 0;
            color: #26364f;
            font-size: 0.86rem;
            font-weight: 600;
        }

        .opp-use-field > .opp-input {
            height: 48px;
            min-height: 48px;
        }

        .opp-use-field textarea.opp-input {
            height: 88px;
            min-height: 88px;
            max-height: 88px;
            resize: none;
        }

        .opp-evidence-upload-panel {
            border: 1px solid #cfddf2;
            border-radius: 8px;
            background: #fff;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .opp-evidence-upload-head {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: flex-start;
            margin-bottom: 0.8rem;
        }

        .opp-required-legend {
            display: flex;
            gap: 1.15rem;
            color: #52617a;
            font-size: 0.84rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .opp-required-legend span {
            display: inline-flex;
            gap: 0.35rem;
            align-items: center;
        }

        .opp-required-dot {
            color: #ef4444;
            font-weight: 900;
        }

        .opp-optional-ring {
            width: 12px;
            height: 12px;
            border: 1px solid #9fb1cc;
            border-radius: 999px;
        }

        .opp-evidence-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            column-gap: 1rem;
            row-gap: 1.45rem;
            margin-bottom: 0;
        }

        .opp-evidence-card {
            min-width: 0;
        }

        .opp-evidence-card .opp-label {
            min-height: 32px;
            margin-bottom: 0.4rem;
        }

        .opp-evidence-drop {
            min-height: 92px;
            display: grid;
            place-items: center;
            text-align: center;
            border: 1px dashed #bfd0e7;
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
            padding: 0.7rem;
        }

        .opp-evidence-drop i {
            color: #1d62d8;
            font-size: 1.5rem;
            line-height: 1;
        }

        .opp-evidence-drop strong {
            display: block;
            margin-top: 0.35rem;
            color: #14294b;
            font-size: 0.78rem;
            font-weight: 900;
        }

        .opp-evidence-drop span {
            display: block;
            color: #52617a;
            font-size: 0.72rem;
            line-height: 1.35;
        }

        .opp-evidence-state {
            display: block;
            margin-top: 0.35rem;
            color: #536176;
            font-size: 0.76rem;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .opp-evidence-card.has-file .opp-evidence-drop {
            border-color: #8ed8a3;
            background: #f7fef9;
        }

        .opp-evidence-card.is-drag-over .opp-evidence-drop {
            border-color: #2563eb;
            background: #eff6ff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .opp-evidence-card.has-file:not(.is-too-large) .opp-evidence-drop {
            border-color: #82d59f;
            background: #ecfdf3;
            box-shadow: 0 10px 22px rgba(22, 101, 52, 0.08);
        }

        .opp-evidence-card.has-file .opp-evidence-state {
            color: #16894f;
        }

        .opp-evidence-card.is-too-large .opp-evidence-drop {
            border-color: #f08f8f;
            background: #fffafa;
        }

        .opp-evidence-bottom {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
            border: 1px solid #d7e5fb;
            border-radius: 8px;
            background: #f6fbff;
            padding: 0.85rem 1rem;
        }

        .opp-evidence-bottom .opp-secure-note {
            max-width: none;
            flex: 1 1 auto;
        }

        .opp-evidence-actions {
            flex: 0 0 auto;
            display: flex;
            flex-wrap: nowrap;
            gap: 0.65rem;
            align-items: center;
            justify-content: flex-end;
            margin-top: 0;
        }

        .opp-evidence-actions .opp-btn {
            white-space: nowrap;
        }

        .opp-evidence-actions .opp-upload-submit {
            min-width: 210px;
        }

        .opp-btn-outline {
            background: #fff;
            color: #1d62d8;
            border: 1px solid #8db7ff;
            box-shadow: none;
        }

        .opp-btn-outline:hover {
            color: #1d62d8;
            background: #f4f8ff;
        }

        .opp-files-card h2 {
            font-size: 1.1rem;
            margin: 0;
            padding: 0.75rem 0.9rem;
            background: linear-gradient(135deg, #294d78 0%, #2d4a73 100%);
            color: #fff;
            font-weight: 900;
        }

        .opp-file-list {
            padding: 0.85rem 1rem;
        }

        .opp-doc-row {
            display: flex;
            justify-content: space-between;
            gap: 0.7rem;
            padding: 0.55rem 0;
            border-bottom: 1px solid #eef2f7;
            font-size: 0.86rem;
        }

        .opp-doc-row:last-child {
            border-bottom: 0;
        }

        .risk-Low { background: #effaf2; color: #166534; }
        .risk-Medium { background: #fff8db; color: #854d0e; }
        .risk-High { background: #fff0f0; color: #991b1b; }
        .opp-sidebar-payment { margin-top:.85rem; padding-top:.85rem; border-top:1px solid #e4eaf2; }
        .opp-sidebar-payment h3 { margin:0 0 .25rem; color:#14294b; font-size:.95rem; font-weight:900; }
        .opp-sidebar-payment > p { margin:0 0 .65rem; color:#66758b; font-size:.8rem; line-height:1.4; }
        .opp-sidebar-payment .opp-field { margin-bottom:.65rem; }
        .opp-sidebar-payment .opp-btn { min-height:36px; padding:0 .75rem; font-size:.8rem; }

        @media (max-width: 1050px) {
            .opp-layout {
                display: block;
            }

            .opp-below-tracker {
                display: block;
            }

            .opp-action-card,
            .opp-files-card {
                margin-top: 0.75rem;
            }

            .opp-form-grid {
                grid-template-columns: repeat(2,minmax(0,1fr));
            }

            .opp-evidence-panels,
            .opp-use-grid {
                grid-template-columns: 1fr;
            }

            .opp-evidence-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .opp-ground-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .opp-step-row {
                grid-template-columns: 42px 1fr;
                align-items: start;
                row-gap: 0.5rem;
            }

            .opp-step-state {
                grid-column: 2;
                flex-direction: row;
                align-items: flex-start;
                gap: 0.65rem;
                flex-wrap: wrap;
                min-width: 0;
                text-align: left;
            }

            .opp-head-badges {
                justify-items: start;
            }
        }

        @media (max-width: 640px) {
            .opp-page {
                padding: 0 12px;
            }

            .opp-status-head,
            .opp-action-head {
                grid-template-columns: 1fr;
            }

            .opp-action-status-pill {
                justify-self: start;
            }

            .opp-title-wrap {
                align-items: flex-start;
            }

            .opp-status-body,
            .opp-action-body,
            .opp-file-list {
                padding: 0.9rem;
            }

            .opp-step-row {
                grid-template-columns: 40px minmax(0, 1fr);
                gap: 0.7rem;
                align-items: start;
                padding: 0.9rem 0;
            }

            .opp-step-icon {
                width: 40px;
                height: 40px;
            }

            .opp-step-row::before {
                left: 20px;
                top: 48px;
                bottom: -10px;
            }

            .opp-step-title-row {
                align-items: flex-start;
                flex-direction: column;
                gap: 0.35rem;
            }

            .opp-step-copy h3 {
                font-size: 1rem;
                line-height: 1.2;
            }

            .opp-step-copy p {
                font-size: 0.84rem;
                line-height: 1.4;
            }

            .opp-stage-documents-link {
                justify-content: flex-start;
                font-size: 0.82rem;
                line-height: 1.25;
                text-align: left;
            }

            .opp-status-badge {
                padding: 0.22rem 0.48rem;
                font-size: 0.72rem;
            }

            .opp-step-date {
                font-size: 0.72rem;
            }

            .opp-form-grid {
                grid-template-columns: 1fr;
            }

            .opp-evidence-top,
            .opp-evidence-upload-head,
            .opp-evidence-bottom {
                display: grid;
                grid-template-columns: 1fr;
            }

            .opp-evidence-actions {
                justify-content: start;
            }

            .opp-stage-card {
                min-width: 0;
            }

            .opp-ground-grid,
            .opp-evidence-grid {
                grid-template-columns: 1fr;
            }

            .opp-upload-intro,
            .opp-note-warning,
            .opp-deadline-panel {
                grid-template-columns: 1fr;
            }

            .opp-deadline-ghost {
                display: none;
            }
        }
    </style>

    <div class="opp-page">
        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

        <div class="opp-layout {{ $actionOnly ? 'is-action-only' : '' }}">
            @unless ($actionOnly)
            <section class="opp-status-card">
                <header class="opp-status-head">
                    <div class="opp-title-wrap">
                        <div class="opp-title-icon"><i class="bi bi-shield-check"></i></div>
                        <div>
                            <h1>Opposition Status Tracking</h1>
                            <a class="opp-status-sub" href="#case-files"><i class="bi bi-card-checklist"></i> View Case Details</a>
                        </div>
                    </div>
                    <div class="opp-head-badges">
                        <span class="opp-pill">{{ $displayClientStage }}</span>
                        <span class="opp-pill opp-pill-muted">Deadline: {{ optional($case->counter_statement_deadline)->format('d M Y') ?: 'Pending' }}</span>
                    </div>
                </header>

                <div class="opp-status-body">
                    <ol class="opp-timeline">
                        @foreach ($statusOrder as $index => [$title, $copy, $icon, $anchorStatus])
                            @php
                                $state = $case->current_admin_status === $workflow::ADMIN_MATTER_CLOSED && $index <= $activeRank
                                    ? 'complete'
                                    : ($index < $activeRank ? 'complete' : ($index === $activeRank ? 'active' : 'pending'));
                                $badgeClass = $state === 'complete' ? 'badge-complete' : ($state === 'active' ? 'badge-active' : 'badge-pending');
                                $timestampStatuses = match ($index) {
                                    0 => [$workflow::ADMIN_APPLICATION_RECEIVED, $workflow::ADMIN_DOCUMENTS_PENDING],
                                    1 => [$workflow::ADMIN_OPPOSITION_REVIEW, $workflow::ADMIN_LEGAL_ANALYSIS, $workflow::ADMIN_LEGAL_ANALYSIS_COMPLETED],
                                    3 => [$workflow::CLIENT_PRICING_PAYMENT, 'Payment Completed'],
                                    4 => [$workflow::ADMIN_COUNTER_STATEMENT_DRAFTING, $workflow::ADMIN_DRAFT_UNDER_LEGAL_REVIEW, $workflow::ADMIN_CLIENT_APPROVAL_PENDING, $workflow::ADMIN_CLIENT_APPROVAL, $workflow::ADMIN_READY_FOR_FILING],
                                    7 => [$workflow::ADMIN_EVIDENCE_BY_OPPONENT, $workflow::ADMIN_EVIDENCE_BY_APPLICANT, $workflow::ADMIN_EVIDENCE_IN_REPLY, $workflow::ADMIN_EVIDENCE_FILED],
                                    8 => [$workflow::ADMIN_HEARING_PREPARATION, $workflow::ADMIN_HEARING_SCHEDULED, $workflow::ADMIN_HEARING_ADJOURNED, $workflow::ADMIN_HEARING_COMPLETED],
                                    10 => [$workflow::ADMIN_MATTER_CLOSED, $workflow::ADMIN_OPPOSITION_ALLOWED, $workflow::ADMIN_OPPOSITION_DISMISSED, $workflow::ADMIN_SETTLEMENT_CLOSED],
                                    default => [$anchorStatus],
                                };
                                $latestForStatus = in_array($state, ['complete', 'active'], true)
                                    ? $case->statusHistories->first(fn ($history) => in_array($history->new_status, $timestampStatuses, true))
                                    : null;
                            @endphp
                            <li class="opp-step-row is-{{ $state }}">
                                <div class="opp-step-icon">
                                    <i class="bi {{ $icon }}"></i>
                                    @if ($state === 'complete')<span class="mini-check"><i class="bi bi-check-lg"></i></span>@endif
                                </div>
                                <div class="opp-step-copy">
                                    <div class="opp-step-title-row">
                                        <h3>{{ $title }}</h3>
                                        @if (($stageDocuments->get($index, collect()))->isNotEmpty())
                                            <button class="opp-stage-documents-link" type="button" data-open-stage-documents="{{ $index }}"><i class="bi bi-folder2-open"></i> View Documents</button>
                                        @endif
                                    </div>
                                    <p>{{ $copy }}</p>
                                </div>
                                <div class="opp-step-state">
                                    <span class="opp-status-badge {{ $badgeClass }}">{{ ucfirst($state === 'complete' ? 'Completed' : $state) }}</span>
                                    @if ($latestForStatus)<span class="opp-step-date">{{ $formatDateTime($latestForStatus->created_at) }}</span>@endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>
            @endunless

            <div class="opp-below-tracker">
                <section class="opp-action-card {{ $actionType === 'documents' ? 'is-document-upload' : '' }} {{ $actionType === 'evidence' ? 'is-evidence-upload' : '' }}">
                    <header class="opp-action-head">
                        <div class="opp-action-title">
                            <span class="opp-action-title-icon"><i class="bi bi-file-earmark-text"></i></span>
                            <h2>Action Center</h2>
                        </div>
                        <span class="opp-pill opp-action-status-pill"><i class="bi bi-check-lg"></i>{{ $case->current_admin_status }}</span>
                    </header>
                    <div class="opp-action-body">
                        @if ($defenceOutcomeLabel)
                            <div class="opp-outcome-banner {{ $finalOutcomeContent['class'] ?? ($case->defence_case_status === $workflow::DEFENCE_CASE_SUCCEEDED ? 'is-success' : 'is-danger') }}">
                                <span class="opp-outcome-banner-icon">
                                    <i class="bi {{ $finalOutcomeContent['icon'] ?? ($case->defence_case_status === $workflow::DEFENCE_CASE_SUCCEEDED ? 'bi-check-circle-fill' : 'bi-x-circle-fill') }}"></i>
                                </span>
                                <div>
                                    <span>Defence Result</span>
                                    <strong>{{ $finalOutcomeContent['status'] ?? $defenceOutcomeLabel }}</strong>
                                    <p>{{ $finalOutcomeContent['headline'] ?? $defenceOutcomeCopy }}</p>
                                </div>
                            </div>
                        @endif
                        @if ($actionCenterAdminDocuments->isNotEmpty())
                            <div class="opp-action-admin-docs">
                                <h3>Documents Sent by Admin</h3>
                                <div class="opp-stage-doc-list">
                                    @foreach ($actionCenterAdminDocuments as $document)
                                        @php
                                            $viewUrl = match ($document['kind']) {
                                                'draft' => route('trademark-opposition.file.view', [$case, 'draft']),
                                                'filing-acknowledgment' => route('trademark-opposition.file.view', [$case, 'filing-acknowledgment']),
                                                default => route('trademark-opposition.document.view', [$case, $document['kind'], $document['record']->id]),
                                            };
                                        @endphp
                                        <div class="opp-stage-doc-row">
                                            <span class="opp-stage-doc-icon"><i class="bi bi-file-earmark-arrow-down"></i></span>
                                            <div class="opp-stage-doc-copy">
                                                <strong>{{ $document['label'] }}</strong>
                                                <span>{{ $document['file_type'] }} · {{ $formatStageFileSize($document['file_size']) }} · Sent {{ $formatDateTime($document['created_at']) }}</span>
                                            </div>
                                            <div class="opp-stage-doc-actions">
                                                <a href="{{ $viewUrl }}" target="_blank"><i class="bi bi-eye"></i> View</a>
                                                <a href="{{ $viewUrl }}?download=1"><i class="bi bi-download"></i> Download</a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        @if ($finalOutcomeContent)
                            <div class="opp-final-info">
                                <h3>Information</h3>
                                <p>{{ $finalOutcomeContent['information'] }}</p>
                                @if ($finalClientMessage !== '')
                                    <div class="opp-admin-request-note mt-3">
                                        <i class="bi bi-chat-left-text"></i>
                                        <div>
                                            <strong>Client Note</strong>
                                            <p>{{ $finalClientMessage }}</p>
                                        </div>
                                    </div>
                                @endif
                                <div class="opp-final-links">
                                    @if ($actionCenterAdminDocuments->isNotEmpty())
                                        <button type="button" data-open-stage-documents="10"><i class="bi bi-folder2-open"></i> View Final Documents</button>
                                    @endif
                                    @if (($stageDocuments->get(4, collect()))->isNotEmpty())
                                        <button type="button" data-open-stage-documents="4"><i class="bi bi-file-earmark-text"></i> View Counter Statement</button>
                                    @endif
                                    @if (($stageDocuments->get(5, collect()))->isNotEmpty())
                                        <button type="button" data-open-stage-documents="5"><i class="bi bi-send-check"></i> View Filed Documents</button>
                                    @endif
                                    @if ($defendedApplication)
                                        <a href="{{ route('trademark.status', $defendedApplication->id) }}"><i class="bi bi-arrow-left-circle"></i> Back to Application</a>
                                    @endif
                                </div>
                                <div class="opp-final-footer">{{ $finalOutcomeContent['footer'] }}</div>
                            </div>
                        @endif
                        @if ($actionType === 'documents')
                            <div class="opp-upload-intro">
                                <span class="opp-upload-intro-icon"><i class="bi bi-cloud-arrow-up"></i></span>
                                <div>
                                    <h3>{{ $actionTitle }}</h3>
                                    <p>{{ $actionCopy }}</p>
                                </div>
                            </div>
                            @if ($showCounterStatementDeadline)
                                <div class="opp-deadline-panel {{ $deadlineClass }}">
                                    <span class="opp-deadline-icon"><i class="bi bi-calendar3"></i></span>
                                    <div>
                                        <span>Counter Statement Deadline</span>
                                        <strong>{{ optional($case->counter_statement_deadline)->format('d M Y') ?: 'Pending' }} · {{ $case->deadline_status_label }}</strong>
                                    </div>
                                    <i class="bi bi-calendar3 opp-deadline-ghost"></i>
                                </div>
                                <div class="opp-note-warning">
                                    <span class="opp-note-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
                                    <div><strong>NOTE:</strong> Failure to file a Counter Statement within the prescribed period may result in abandonment of the application.</div>
                                </div>
                            @endif
                        @elseif (!in_array($actionType, ['evidence', 'registry-documents', 'filed', 'empty-stage'], true) && !$defenceOutcomeReady)
                            <h3>{{ $actionTitle }}</h3>
                            <p class="opp-muted">{{ $actionCopy }}</p>
                            @if ($showCounterStatementDeadline)
                                <div class="opp-field {{ $deadlineClass }}">
                                    <span>Counter Statement Deadline</span>
                                    <strong>{{ optional($case->counter_statement_deadline)->format('d M Y') ?: 'Pending' }} · {{ $case->deadline_status_label }}</strong>
                                </div>
                                <div class="opp-warning"><strong>NOTE:</strong> Failure to file a Counter Statement within the prescribed period may result in abandonment of the application.</div>
                            @endif
                        @endif

                        @if ($actionType === 'documents')
                            <form class="js-opposition-upload-form" method="POST" action="{{ route('trademark-opposition.documents', $case) }}" enctype="multipart/form-data" data-max-file-bytes="7864320">
                                @csrf
                                <div class="opp-form-grid opp-upload-grid">
                                    @foreach ($requiredDocuments as $key => $label)
                                        @php
                                            $document = $latestDocuments->get($key);
                                            $isRejected = $document?->review_status === 'rejected';
                                            $isUploaded = in_array($key, $uploadedRequired, true);
                                        @endphp
                                        @continue($hasRejectedDocuments && !$isRejected)
                                        <div class="opp-upload-card {{ $isUploaded ? 'has-file' : '' }} {{ $isRejected ? 'is-rejected' : '' }}">
                                            <label class="opp-label">{{ $label }} <span class="text-danger">*</span></label>
                                            <label class="opp-file-picker">
                                                <input class="opp-file-native" type="file" name="{{ $key }}" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                <i class="bi bi-cloud-arrow-up"></i>
                                                <span class="opp-file-choose">Choose file</span>
                                                <span class="opp-file-name">No file chosen</span>
                                            </label>
                                            <small class="opp-file-size-error" data-file-size-error></small>
                                            <small class="opp-upload-state {{ $isUploaded ? 'text-success' : 'text-danger' }}">
                                                @if ($isRejected)
                                                    Reupload required: {{ $document->review_note ?: 'Please reupload this document.' }}
                                                @else
                                                    {{ $isUploaded ? 'Uploaded' : 'Required' }}
                                                @endif
                                            </small>
                                        </div>
                                    @endforeach
                                    @foreach ($optionalDocuments as $key => $label)
                                        @php
                                            $document = $latestDocuments->get($key);
                                            $isRejected = $document?->review_status === 'rejected';
                                            $isUploaded = $document && !$isRejected;
                                        @endphp
                                        @continue($hasRejectedDocuments && !$isRejected)
                                        <div class="opp-upload-card {{ $isUploaded ? 'has-file' : '' }} {{ $isRejected ? 'is-rejected' : '' }}">
                                            <label class="opp-label">{{ $label }}</label>
                                            <label class="opp-file-picker">
                                                <input class="opp-file-native" type="file" name="{{ $key }}" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                <i class="bi bi-cloud-arrow-up"></i>
                                                <span class="opp-file-choose">Choose file</span>
                                                <span class="opp-file-name">No file chosen</span>
                                            </label>
                                            <small class="opp-file-size-error" data-file-size-error></small>
                                            @if ($isRejected)
                                                <small class="opp-upload-state text-danger">Reupload required: {{ $document->review_note ?: 'Please reupload this document.' }}</small>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                                <div class="opp-actions opp-upload-actions">
                                    <button class="opp-btn opp-upload-submit" type="submit"><i class="bi bi-cloud-upload"></i> Upload Documents</button>
                                    <span class="opp-secure-note"><i class="bi bi-shield-check"></i> Your documents are secure and encrypted. Max 7.5 MB per file.</span>
                                </div>
                                <div class="opp-upload-error js-upload-size-error {{ $errors->has('documents') ? 'is-visible' : '' }}">{{ $errors->first('documents') }}</div>
                            </form>
                        @elseif ($actionType === 'registry-documents')
                            @php
                                $registryUploadLabels = $hasRegistryStageReupload
                                    ? $rejectedRegistryStageDocuments->map(fn ($document) => \Illuminate\Support\Str::headline(\Illuminate\Support\Str::after($document->evidence_type, 'third_party_requested_evidence_')))->values()
                                    : $registryStageRequestLabels;
                            @endphp
                            <form class="js-opposition-upload-form" method="POST" action="{{ route('trademark-opposition.stage-documents', $case) }}" enctype="multipart/form-data" data-max-file-bytes="10485760">
                                @csrf
                                <div class="opp-upload-intro">
                                    <span class="opp-upload-intro-icon"><i class="bi bi-cloud-arrow-up"></i></span>
                                    <div>
                                        <h3>{{ $actionTitle }}</h3>
                                        <p>{{ $actionCopy }}</p>
                                    </div>
                                </div>
                                @if (in_array($case->current_admin_status, [
                                    $workflow::ADMIN_HEARING_PREPARATION,
                                    $workflow::ADMIN_HEARING_SCHEDULED,
                                    $workflow::ADMIN_HEARING_ADJOURNED,
                                    $workflow::ADMIN_HEARING_COMPLETED,
                                ], true))
                                    <div class="opp-admin-request-note">
                                        <i class="bi bi-megaphone"></i>
                                        <div>
                                            <strong>Hearing Details</strong>
                                            <p><strong>Status:</strong> {{ $case->current_admin_status }}</p>
                                            @if ($case->hearing_date)
                                                <p><strong>Hearing Date:</strong> {{ $case->hearing_date->format('d M Y') }}</p>
                                            @endif
                                            @if ($hearingAdjournmentReason !== '')
                                                <p><strong>Reason for Adjournment:</strong> {{ $hearingAdjournmentReason }}</p>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                                @if ($case->third_party_evidence_message)
                                    <div class="opp-admin-request-note">
                                        <i class="bi bi-chat-left-text"></i>
                                        <div>
                                            <strong>Note from admin</strong>
                                            <p>{{ $case->third_party_evidence_message }}</p>
                                        </div>
                                    </div>
                                @endif
                                <div class="opp-form-grid opp-upload-grid">
                                    @foreach ($registryUploadLabels as $requestIndex => $label)
                                        <div class="opp-upload-card">
                                            <label class="opp-label">{{ $label }} <span class="text-danger">*</span></label>
                                            <label class="opp-file-picker">
                                                <input class="opp-file-native" type="file" name="requested_documents[{{ $requestIndex }}][]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                                                <i class="bi bi-cloud-arrow-up"></i>
                                                <span class="opp-file-choose">Choose file</span>
                                                <span class="opp-file-name">No file chosen</span>
                                            </label>
                                            <small class="opp-file-size-error" data-file-size-error></small>
                                            @if ($hasRegistryStageReupload)
                                                <small class="opp-upload-state text-danger">Reupload required</small>
                                            @else
                                                <small class="opp-upload-state text-danger">Required</small>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                                <div class="opp-note-to-admin-box">
                                    <label class="opp-label">Note to admin <span class="text-muted">(optional)</span></label>
                                    <textarea class="opp-textarea" name="note_to_admin" rows="3" placeholder="Add any context for the uploaded documents.">{{ old('note_to_admin') }}</textarea>
                                </div>
                                <div class="opp-actions opp-upload-actions">
                                    <button class="opp-btn opp-upload-submit" type="submit"><i class="bi bi-cloud-upload"></i> Upload Requested Documents</button>
                                    <span class="opp-secure-note"><i class="bi bi-shield-check"></i> Max 10 MB per file.</span>
                                </div>
                                <div class="opp-upload-error js-upload-size-error {{ $errors->has('requested_documents') ? 'is-visible' : '' }}">{{ $errors->first('requested_documents') }}</div>
                            </form>
                        @elseif ($actionType === 'evidence')
                            <form class="js-evidence-upload-form" method="POST" action="{{ route('trademark-opposition.evidence', $case) }}" enctype="multipart/form-data" data-max-file-bytes="10485760">
                                @csrf
                                <input type="hidden" name="save_draft" value="0" data-evidence-draft-input>
                                <div class="opp-evidence-shell">
                                    <div class="opp-evidence-top">
                                        <div>
                                            <h2>Submit Evidence</h2>
                                            <p>Upload the documents requested by our legal team to support your trademark defence.</p>
                                        </div>
                                    </div>

                                    <div class="opp-deadline-panel opp-evidence-deadline {{ $deadlineClass }}">
                                        <span class="opp-deadline-icon"><i class="bi bi-calendar3"></i></span>
                                        <div>
                                            <span>Counter Statement Deadline</span>
                                            <strong>{{ optional($case->counter_statement_deadline)->format('d M Y') ?: 'Pending' }} · {{ $case->deadline_status_label }}</strong>
                                            <div class="opp-deadline-helper">We will ensure timely filing before the deadline.</div>
                                        </div>
                                        <i class="bi bi-shield-check opp-deadline-ghost"></i>
                                    </div>

                                    <div class="opp-evidence-alert">
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        <div><strong>Important:</strong> Failure to file a Counter Statement within the prescribed period may result in abandonment of the application.</div>
                                    </div>

                                    <div class="opp-evidence-panels">
                                        <div class="opp-evidence-panel is-note">
                                            <div class="opp-panel-title">
                                                <i class="bi bi-info-circle-fill"></i>
                                                <h3>Legal Admin Note</h3>
                                            </div>
                                            <p>{{ $case->evidence_request_note ?: 'Our team has reviewed the Notice of Opposition. Please upload the requested proof documents so we can prepare a strong defence for your trademark application.' }}</p>
                                        </div>

                                        <div class="opp-evidence-panel">
                                            <div class="opp-panel-title">
                                                <i class="bi bi-gavel"></i>
                                                <h3>Opposition Grounds Identified</h3>
                                            </div>
                                            <div class="opp-ground-grid">
                                                @forelse ($case->grounds as $ground)
                                                    <div class="opp-ground-card">
                                                        <strong>{{ $ground->ground_name }}</strong>
                                                        <p>{{ $oppositionGroundDescriptions[$ground->ground_name] ?? $ground->admin_note ?? 'Ground identified by the legal team.' }}</p>
                                                    </div>
                                                @empty
                                                    <p class="opp-muted">Opposition grounds will appear here after admin review.</p>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>

                                    <div class="opp-use-panel">
                                        <h3>Tell us about your trademark use</h3>
                                        <p>Please provide your first use details and other relevant information.</p>
                                        <div class="opp-use-grid">
                                            <div class="opp-use-field">
                                                <label class="opp-label">First Use Date <span class="text-danger">*</span></label>
                                                <input class="opp-input" type="date" name="first_use_date" value="{{ $firstUseDateValue }}" required>
                                            </div>
                                            <div class="opp-use-field">
                                                <label class="opp-label">Is the trademark currently in use? <span class="text-danger">*</span></label>
                                                <div class="opp-radio-row">
                                                    <label><input type="radio" name="currently_in_use" value="yes" @checked($currentlyInUseValue !== 'no')> Yes</label>
                                                    <label><input type="radio" name="currently_in_use" value="no" @checked($currentlyInUseValue === 'no')> No</label>
                                                </div>
                                            </div>
                                            <div class="opp-use-field">
                                                <label class="opp-label">Has the mark been used continuously? <span class="text-danger">*</span></label>
                                                <div class="opp-radio-row">
                                                    <label><input type="radio" name="used_continuously" value="yes" @checked($usedContinuouslyValue !== 'no')> Yes</label>
                                                    <label><input type="radio" name="used_continuously" value="no" @checked($usedContinuouslyValue === 'no')> No</label>
                                                </div>
                                            </div>
                                            <div class="opp-use-field">
                                                <label class="opp-label">Annual Sales (if any)</label>
                                                <div class="opp-input-group">
                                                    <span class="opp-input-prefix">₹</span>
                                                    <input class="opp-input" type="text" name="annual_sales" value="{{ $annualSalesValue }}" placeholder="Enter amount">
                                                </div>
                                            </div>
                                            <div class="opp-use-field">
                                                <label class="opp-label">Marketing Spend (if any)</label>
                                                <div class="opp-input-group">
                                                    <span class="opp-input-prefix">₹</span>
                                                    <input class="opp-input" type="text" name="marketing_spend" value="{{ $marketingSpendValue }}" placeholder="Enter amount">
                                                </div>
                                            </div>
                                            <div class="opp-use-field is-textarea">
                                                <label class="opp-label">Any other relevant details</label>
                                                <textarea class="opp-input" name="other_relevant_details" rows="2" placeholder="Write here...">{{ $otherRelevantDetailsValue }}</textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="opp-evidence-upload-panel">
                                        <div class="opp-evidence-upload-head">
                                            <div class="opp-evidence-upload-title">
                                                <h3>Upload Evidence</h3>
                                                <p>Please upload clear and legible documents. Accepted formats: PDF, JPG, PNG, DOC, DOCX (Max 10MB per file)</p>
                                            </div>
                                            <div class="opp-required-legend">
                                                <span><span class="opp-required-dot">*</span> Required</span>
                                            </div>
                                        </div>

                                        <div class="opp-evidence-grid">
                                            @forelse ($visibleRequestedEvidenceItems as $item)
                                                @php
                                                    $requestSlug = \Illuminate\Support\Str::slug($item['request'], '_');
                                                    $existingEvidence = $latestEvidenceByType->get($requestSlug) ?? $latestEvidenceByType->get($item['key']);
                                                    $evidenceRejected = $existingEvidence?->review_status === 'rejected';
                                                    $inputName = 'requested_evidence[' . $requestSlug . '][]';
                                                @endphp
                                                <div class="opp-evidence-card {{ $existingEvidence && !$evidenceRejected ? 'has-file' : '' }} {{ $evidenceRejected ? 'is-too-large' : '' }}">
                                                    <label class="opp-label">
                                                        {{ $item['label'] }} <span class="text-danger">*</span>
                                                    </label>
                                                    <label class="opp-evidence-drop">
                                                        <input class="opp-file-native" type="file" name="{{ $inputName }}" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" multiple {{ $existingEvidence && !$evidenceRejected ? '' : 'required' }}>
                                                        <span>
                                                            <i class="bi bi-cloud-arrow-up"></i>
                                                            <strong>Upload files</strong>
                                                            <span>or drag and drop<br>PDF, JPG, PNG</span>
                                                        </span>
                                                    </label>
                                                    <small class="opp-file-size-error" data-file-size-error></small>
                                                <span class="opp-evidence-state">
                                                    @if ($evidenceRejected)
                                                        Reupload required: {{ $existingEvidence->review_note ?: $existingEvidence->file_name }}
                                                    @else
                                                        {{ $existingEvidence ? $existingEvidence->file_name : 'No file uploaded' }}
                                                    @endif
                                                </span>
                                                </div>
                                            @empty
                                                <div class="opp-field" style="grid-column: 1 / -1;">
                                                    <span>Selected Evidence Required From Client</span>
                                                    <strong>No evidence documents have been requested yet.</strong>
                                                </div>
                                            @endforelse
                                        </div>
                                    </div>

                                    <div class="opp-evidence-bottom">
                                        <span class="opp-secure-note"><i class="bi bi-lock"></i> Your documents are securely shared with our legal team and will be used only for your case.</span>
                                        <div class="opp-evidence-actions">
                                            <button class="opp-btn opp-btn-outline js-evidence-draft" type="submit" name="save_draft" value="1" formnovalidate><i class="bi bi-file-earmark"></i> Save Draft</button>
                                            <button class="opp-btn opp-upload-submit" type="submit"><i class="bi bi-send"></i> Submit Evidence</button>
                                        </div>
                                    </div>
                                    <div class="opp-upload-error js-evidence-size-error"></div>
                                </div>
                            </form>
                        @elseif ($actionType === 'risk')
                            @php
                                $riskOption = $riskOptions[$case->risk_level] ?? null;
                            @endphp
                            <div class="opp-risk-assessment risk-{{ \Illuminate\Support\Str::before($case->risk_level, ' ') }}">
                                <h3>{{ $case->risk_level }}</h3>
                                @if ($riskOption)
                                    <strong>{{ $riskOption['subheading'] }}</strong>
                                    <p>{{ $riskOption['content'] }}</p>
                                @endif
                            </div>
                            @if ($case->risk_note)
                                <div class="opp-legal-note-panel">
                                    <span>Legal Team Note</span>
                                    <p>{{ $case->risk_note }}</p>
                                </div>
                            @endif
                        @elseif ($actionType === 'payment')
                            @if ($case->risk_level)
                                @php
                                    $riskOption = $riskOptions[$case->risk_level] ?? null;
                                @endphp
                                <div class="opp-risk-assessment risk-{{ \Illuminate\Support\Str::before($case->risk_level, ' ') }}">
                                    <h3>{{ $case->risk_level }}</h3>
                                    @if ($riskOption)
                                        <strong>{{ $riskOption['subheading'] }}</strong>
                                        <p>{{ $riskOption['content'] }}</p>
                                    @endif
                                </div>
                            @endif
                            @if ($case->risk_note)
                                <div class="opp-legal-note-panel">
                                    <span>Legal Team Note</span>
                                    <p>{{ $case->risk_note }}</p>
                                </div>
                            @endif
                            <div class="opp-package-card">
                                <div class="opp-package-card-head">
                                    <span class="opp-package-card-icon"><i class="bi bi-shield-check"></i></span>
                                    <div class="opp-package-card-title">
                                        <span>Selected Package</span>
                                        <h3>{{ $case->package_name ?: 'Opposition Defence Package' }}</h3>
                                    </div>
                                    <div class="opp-package-card-price">
                                        @if ($oppositionDiscountAmount > 0)
                                            <span class="opp-price-original">₹{{ number_format($oppositionOriginalAmount, 2) }}</span>
                                        @endif
                                        <strong>₹<span data-opposition-payable>{{ number_format($oppositionPayableAmount, 2) }}</span></strong>
                                    </div>
                                </div>
                                @if (!empty($case->included_services))
                                    <div class="opp-package-card-body">
                                        <span class="opp-package-includes">Package Includes</span>
                                        <ul>
                                            @foreach ($case->included_services as $service)
                                                <li><i class="bi bi-check-circle-fill"></i><span>{{ $service }}</span></li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                            @if ($oppositionPaymentCoupons->isNotEmpty())
                                <div class="opp-coupon-box">
                                    <span>Available Discount</span>
                                    @foreach ($oppositionPaymentCoupons as $coupon)
                                        @php
                                            $discountedAmount = $coupon->discountedAmountFor($oppositionOriginalAmount);
                                        @endphp
                                        <label>
                                            <input type="radio" name="opposition_discount_coupon_id" value="{{ $coupon->id }}" data-payable-amount="{{ number_format($discountedAmount, 2, '.', '') }}" @checked($oppositionAutoCoupon?->id === $coupon->id)>
                                            <span>
                                                <span class="opp-coupon-code">{{ $coupon->code }}</span>
                                                {{ $coupon->discount_label }} · Pay ₹{{ number_format($discountedAmount, 2) }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                            <form method="POST" action="{{ route('trademark-opposition.payment', $case) }}" class="js-opposition-payment-form" data-create-order-url="{{ route('trademark-opposition.payment.create-order', $case) }}" data-verify-url="{{ route('trademark-opposition.payment.verify-signature', $case) }}" data-amount="{{ number_format($oppositionPayableAmount, 2, '.', '') }}">
                                @csrf
                                @include('partials.government-fee-notice')
                                <label class="opp-payment-consent"><input type="checkbox" name="success_disclaimer" value="1" required><span>I understand Legal Bruz cannot guarantee success in opposition proceedings.</span></label>
                                <div class="opp-actions"><button class="opp-btn opp-btn-green" type="submit"><i class="bi bi-credit-card"></i> Pay & Continue with ₹<span data-opposition-button-amount>{{ number_format($oppositionPayableAmount, 2) }}</span></button></div>
                            </form>
                        @elseif ($actionType === 'draft')
                            @php
                                $formatSentFileSize = static function (?int $bytes): string {
                                    $bytes = max(0, (int) $bytes);
                                    return $bytes >= 1048576
                                        ? number_format($bytes / 1048576, 1) . ' MB'
                                        : number_format(max(1, (int) ceil($bytes / 1024))) . ' KB';
                                };
                                $draftBytes = Storage::disk('public')->exists($case->draft_path)
                                    ? Storage::disk('public')->size($case->draft_path)
                                    : 0;
                                $draftExtension = strtoupper(pathinfo($case->draft_display_name, PATHINFO_EXTENSION) ?: 'FILE');
                                $additionalDraftDocuments = $case->evidence->where('evidence_type', 'counter_statement_additional_document');
                            @endphp
                            <div class="opp-draft-section">
                                <span class="opp-draft-section-title">Counter Statement Draft</span>
                                <div class="opp-sent-documents">
                                    <div class="opp-sent-document">
                                        <span class="opp-sent-document-icon"><i class="bi bi-file-earmark-text"></i></span>
                                        <div class="opp-sent-document-copy">
                                            <strong>{{ $case->draft_display_name }}</strong>
                                            <span>{{ $draftExtension }} · {{ $formatSentFileSize($draftBytes) }} · Uploaded {{ $formatDateTime($case->updated_at) }}</span>
                                        </div>
                                        <div class="opp-sent-document-actions">
                                            <a class="opp-doc-action" href="{{ route('trademark-opposition.file.view', [$case, 'draft']) }}" target="_blank"><i class="bi bi-eye"></i> View</a>
                                            <a class="opp-doc-action" href="{{ route('trademark-opposition.file.view', [$case, 'draft']) }}?download=1"><i class="bi bi-download"></i> Download</a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if ($additionalDraftDocuments->isNotEmpty())
                                <div class="opp-draft-section">
                                    <span class="opp-draft-section-title">Other Documents</span>
                                    <div class="opp-sent-documents">
                                        @foreach ($additionalDraftDocuments as $additionalDocument)
                                            <div class="opp-sent-document">
                                                <span class="opp-sent-document-icon"><i class="bi bi-file-earmark-check"></i></span>
                                                <div class="opp-sent-document-copy">
                                                    <strong>{{ $evidenceDocumentName($additionalDocument) }}</strong>
                                                    <span>{{ strtoupper($additionalDocument->file_type ?: 'FILE') }} · {{ $formatSentFileSize($additionalDocument->file_size) }} · Uploaded {{ $formatDateTime($additionalDocument->created_at) }}</span>
                                                </div>
                                                <div class="opp-sent-document-actions">
                                                    <a class="opp-doc-action" href="{{ route('trademark-opposition.document.view', [$case, 'evidence', $additionalDocument->id]) }}" target="_blank"><i class="bi bi-eye"></i> View</a>
                                                    <a class="opp-doc-action" href="{{ route('trademark-opposition.document.view', [$case, 'evidence', $additionalDocument->id]) }}?download=1"><i class="bi bi-download"></i> Download</a>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="opp-draft-decisions">
                                <button class="opp-decision-button opp-decision-approve" type="button" data-open-draft-modal="approve">
                                    <i class="bi bi-check-circle"></i><span><strong>Approve Draft</strong><span>Approve and submit for final processing.</span></span>
                                </button>
                                <button class="opp-decision-button opp-decision-change" type="button" data-open-draft-modal="changes">
                                    <i class="bi bi-pencil-square"></i><span><strong>Request Changes</strong><span>Request corrections and a revised upload.</span></span>
                                </button>
                            </div>
                        @elseif ($actionType === 'changes-requested')
                            <div class="opp-draft-review-intro">
                                <h3>Draft Reupload Requested</h3>
                                <p class="opp-muted">Your requested changes have been sent to the legal team. No further action is required until a revised draft is uploaded.</p>
                            </div>
                            <div class="opp-warning"><strong>Your note:</strong> {{ $case->client_change_request }}</div>
                        @elseif ($actionType === 'filed')
                            <div class="opp-action-empty-state mb-3">
                                <span class="opp-action-empty-icon"><i class="bi bi-send-check"></i></span>
                                <div>
                                    <strong>{{ $actionTitle }}</strong>
                                    <p>{{ $actionCopy }}</p>
                                </div>
                            </div>
                            <div class="opp-actions"><a class="opp-btn" href="{{ route('trademark-opposition.file.view', [$case, 'filing-acknowledgment']) }}" target="_blank"><i class="bi bi-download"></i> Download Acknowledgment</a></div>
                        @elseif ($actionType === 'payment-complete')
                            <div class="opp-action-empty-state">
                                <span class="opp-action-empty-icon"><i class="bi bi-bell"></i></span>
                                <div>
                                    <strong>No action is required right now.</strong>
                                    <p>Your payment has been received. You will be notified through email when the counter statement draft is ready.</p>
                                </div>
                            </div>
                        @elseif ($actionType === 'empty-stage')
                            <div class="opp-action-empty-state">
                                <span class="opp-action-empty-icon"><i class="bi bi-info-circle"></i></span>
                                <div>
                                    <strong>{{ $actionTitle }}</strong>
                                    <p>{{ $actionCopy }}</p>
                                </div>
                            </div>
                        @elseif ($actionType === 'waiting' && !$finalOutcomeContent)
                            <div class="opp-action-empty-state">
                                <span class="opp-action-empty-icon"><i class="bi bi-info-circle"></i></span>
                                <div>
                                    <strong>{{ $actionTitle }}</strong>
                                    <p>{{ $actionCopy }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </section>

                @unless ($actionOnly)
                <section class="opp-files-card" id="case-files">
                    <h2>Case Details</h2>
                    <div class="opp-file-list">
                        <div class="opp-field"><span>Trademark</span><strong>{{ $case->trademark_name }}</strong></div>
                        <div class="opp-field"><span>Application</span><strong>{{ $case->application_number }} · Class {{ $case->trademark_class }}</strong></div>
                        @if ($defenceOutcomeLabel)
                            <div class="opp-field"><span>Defence Result</span><strong>{{ $defenceOutcomeLabel }}</strong></div>
                        @endif
                        @if ($case->risk_level)
                            <div class="opp-field risk-{{ \Illuminate\Support\Str::before($case->risk_level, ' ') }}"><span>Risk Assessment</span><strong>{{ $case->risk_level }}</strong><div>{{ $case->risk_note }}</div></div>
                        @endif
                        @if ($hasVerifiedOppositionPayment)
                            <div class="opp-sidebar-payment">
                                <h3>Opposition Package Payment</h3>
                                <p>Your opposition defence package payment has been received.</p>
                                <div class="opp-field">
                                    <span>Opposition Defence Package</span>
                                    <strong>{{ $case->package_name }} · ₹{{ number_format((float) ($case->paid_amount ?: $case->package_price), 2) }}</strong>
                                </div>
                                <a class="opp-btn opp-btn-green" href="{{ route('trademark-opposition.payment.invoice', $case) }}" target="_blank"><i class="bi bi-receipt"></i> Download Invoice</a>
                            </div>
                        @endif
                    </div>
                </section>
                @endunless
            </div>
        </div>
    </div>

    @unless ($actionOnly)
        @foreach ($stageDocuments as $stageIndex => $documents)
            @continue($documents->isEmpty())
            @php
                $clientStageDocuments = $documents->where('sender', 'client');
                $adminStageDocuments = $documents->where('sender', 'admin');
            @endphp
            <div class="opp-stage-doc-modal" data-stage-documents-modal="{{ $stageIndex }}" role="dialog" aria-modal="true" aria-labelledby="stageDocumentsTitle{{ $stageIndex }}">
                <div class="opp-stage-doc-dialog">
                    <div class="opp-stage-doc-head">
                        <div>
                            <h3 id="stageDocumentsTitle{{ $stageIndex }}">{{ str_ends_with($statusOrder[$stageIndex][0], 'Documents') ? $statusOrder[$stageIndex][0] : $statusOrder[$stageIndex][0] . ' Documents' }}</h3>
                            <p>Documents exchanged during this stage</p>
                        </div>
                        <button class="opp-stage-doc-close" type="button" data-close-stage-documents aria-label="Close">&times;</button>
                    </div>
                    <div class="opp-stage-doc-body">
                        @if ($clientStageDocuments->isNotEmpty())
                            <section class="opp-stage-doc-group">
                                <h4>Documents You Sent</h4>
                                <div class="opp-stage-doc-list">
                                    @foreach ($clientStageDocuments as $document)
                                        @php
                                            $viewUrl = route('trademark-opposition.document.view', [$case, $document['kind'], $document['record']->id]);
                                        @endphp
                                        <div class="opp-stage-doc-row">
                                            <span class="opp-stage-doc-icon"><i class="bi bi-file-earmark-check"></i></span>
                                            <div class="opp-stage-doc-copy">
                                                <strong>{{ $document['label'] }}</strong>
                                                <span>{{ $document['file_type'] }} · {{ $formatStageFileSize($document['file_size']) }} · Sent {{ $formatDateTime($document['created_at']) }}</span>
                                            </div>
                                            <div class="opp-stage-doc-actions">
                                                <a href="{{ $viewUrl }}" target="_blank"><i class="bi bi-eye"></i> View</a>
                                                <a href="{{ $viewUrl }}?download=1"><i class="bi bi-download"></i> Download</a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        @if ($adminStageDocuments->isNotEmpty())
                            <section class="opp-stage-doc-group">
                                <h4>Documents Sent by Admin</h4>
                                <div class="opp-stage-doc-list">
                                    @foreach ($adminStageDocuments as $document)
                                        @php
                                            $viewUrl = match ($document['kind']) {
                                                'draft' => route('trademark-opposition.file.view', [$case, 'draft']),
                                                'filing-acknowledgment' => route('trademark-opposition.file.view', [$case, 'filing-acknowledgment']),
                                                default => route('trademark-opposition.document.view', [$case, $document['kind'], $document['record']->id]),
                                            };
                                        @endphp
                                        <div class="opp-stage-doc-row">
                                            <span class="opp-stage-doc-icon"><i class="bi bi-file-earmark-arrow-down"></i></span>
                                            <div class="opp-stage-doc-copy">
                                                <strong>{{ $document['label'] }}</strong>
                                                <span>{{ $document['file_type'] }} · {{ $formatStageFileSize($document['file_size']) }} · Sent {{ $formatDateTime($document['created_at']) }}</span>
                                            </div>
                                            <div class="opp-stage-doc-actions">
                                                <a href="{{ $viewUrl }}" target="_blank"><i class="bi bi-eye"></i> View</a>
                                                <a href="{{ $viewUrl }}?download=1"><i class="bi bi-download"></i> Download</a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    @endunless

    @if ($actionType === 'draft')
        <div class="opp-draft-modal" data-draft-modal="approve" role="dialog" aria-modal="true" aria-labelledby="approveDraftTitle">
            <div class="opp-draft-modal-dialog">
                <div class="opp-draft-modal-head">
                    <h3 id="approveDraftTitle">Approve Counter Statement Draft</h3>
                    <button class="opp-draft-modal-close" type="button" data-close-draft-modal aria-label="Close">&times;</button>
                </div>
                <form class="js-draft-decision-form" method="POST" action="{{ route('trademark-opposition.draft.approve', $case) }}">
                    @csrf
                    <div class="opp-draft-modal-body">
                        <p>Based on your review, you can add an approval note optionally.</p>
                        <label class="opp-label" for="clientApprovalNote">Approval Note</label>
                        <textarea class="opp-input" id="clientApprovalNote" name="client_approval_note" rows="4" maxlength="3000" placeholder="Add an optional approval note"></textarea>
                        <div class="opp-draft-modal-actions">
                            <button class="opp-btn opp-btn-gray" type="button" data-close-draft-modal>Cancel</button>
                            <button class="opp-btn opp-btn-green" type="submit"><i class="bi bi-check-circle"></i> Approve Draft</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="opp-draft-modal" data-draft-modal="changes" role="dialog" aria-modal="true" aria-labelledby="requestChangesTitle">
            <div class="opp-draft-modal-dialog">
                <div class="opp-draft-modal-head">
                    <h3 id="requestChangesTitle">Request Draft Reupload</h3>
                    <button class="opp-draft-modal-close" type="button" data-close-draft-modal aria-label="Close">&times;</button>
                </div>
                <form class="js-draft-decision-form" method="POST" action="{{ route('trademark-opposition.draft.request-changes', $case) }}">
                    @csrf
                    <div class="opp-draft-modal-body">
                        <p>Identify the document and clearly describe every correction required. The matter will return to the legal drafting team.</p>
                        <label class="opp-label" for="clientChangeRequest">Reupload / Change Note</label>
                        <textarea class="opp-input" id="clientChangeRequest" name="client_change_request" rows="5" maxlength="3000" placeholder="Example: Please correct the applicant address on page 2 and upload the revised draft." required></textarea>
                        <div class="opp-draft-modal-actions">
                            <button class="opp-btn opp-btn-gray" type="button" data-close-draft-modal>Cancel</button>
                            <button class="opp-btn" type="submit"><i class="bi bi-arrow-repeat"></i> Request Reupload</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        const showClientFallbackAlert = ({ title, text, confirmButtonText }) => {
            return new Promise((resolve) => {
                const overlay = document.createElement('div');
                overlay.style.cssText = 'position:fixed;inset:0;z-index:3000;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.48);padding:18px;';
                overlay.innerHTML = `
                    <div style="width:min(420px,100%);background:#fff;border-radius:12px;box-shadow:0 24px 70px rgba(15,23,42,.28);padding:26px;text-align:center;font-family:Inter,system-ui,sans-serif;">
                        <div style="width:54px;height:54px;border-radius:50%;display:grid;place-items:center;margin:0 auto 14px;border:2px solid #93c5fd;color:#2563eb;font-size:28px;">?</div>
                        <h2 style="margin:0 0 10px;color:#102a4c;font-size:1.35rem;font-weight:700;">${title}</h2>
                        <p style="margin:0;color:#536176;font-size:.95rem;line-height:1.5;">${text}</p>
                        <div style="display:flex;justify-content:center;gap:10px;margin-top:22px;">
                            <button type="button" data-alert-cancel style="min-height:40px;border:0;border-radius:7px;background:#6b7280;color:#fff;padding:0 16px;font-weight:500;">Cancel</button>
                            <button type="button" data-alert-confirm style="min-height:40px;border:0;border-radius:7px;background:#2563eb;color:#fff;padding:0 16px;font-weight:500;">${confirmButtonText}</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(overlay);
                const close = (value) => {
                    overlay.remove();
                    resolve(value);
                };
                overlay.querySelector('[data-alert-cancel]')?.addEventListener('click', () => close(false));
                overlay.querySelector('[data-alert-confirm]')?.addEventListener('click', () => close(true));
                overlay.addEventListener('click', (event) => {
                    if (event.target === overlay) {
                        close(false);
                    }
                });
            });
        };

        const confirmClientStageAction = async ({
            title = 'Confirm action',
            text = 'Are you sure you want to submit this action?',
            confirmButtonText = 'Yes, continue',
            icon = 'question',
        } = {}) => {
            if (!window.Swal) {
                return showClientFallbackAlert({ title, text, confirmButtonText });
            }

            const result = await Swal.fire({
                title,
                text,
                icon,
                showCancelButton: true,
                confirmButtonText,
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#6b7280',
                reverseButtons: true,
                focusCancel: true,
            });

            return result.isConfirmed;
        };

        document.querySelectorAll('[data-open-draft-modal]').forEach((button) => {
            button.addEventListener('click', () => {
                const modal = document.querySelector(`[data-draft-modal="${button.dataset.openDraftModal}"]`);
                modal?.classList.add('is-visible');
                modal?.querySelector('textarea')?.focus();
            });
        });

        document.querySelectorAll('[data-open-stage-documents]').forEach((button) => {
            button.addEventListener('click', () => {
                document.querySelector(`[data-stage-documents-modal="${button.dataset.openStageDocuments}"]`)?.classList.add('is-visible');
            });
        });

        document.querySelectorAll('[data-stage-documents-modal]').forEach((modal) => {
            modal.querySelectorAll('[data-close-stage-documents]').forEach((button) => {
                button.addEventListener('click', () => modal.classList.remove('is-visible'));
            });
            modal.addEventListener('click', (event) => {
                if (event.target === modal) modal.classList.remove('is-visible');
            });
        });

        document.querySelectorAll('[data-draft-modal]').forEach((modal) => {
            modal.querySelectorAll('[data-close-draft-modal]').forEach((button) => {
                button.addEventListener('click', () => modal.classList.remove('is-visible'));
            });
            modal.addEventListener('click', (event) => {
                if (event.target === modal) modal.classList.remove('is-visible');
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                document.querySelectorAll('[data-draft-modal].is-visible').forEach((modal) => modal.classList.remove('is-visible'));
                document.querySelectorAll('[data-stage-documents-modal].is-visible').forEach((modal) => modal.classList.remove('is-visible'));
            }
        });

        document.querySelectorAll('.js-opposition-upload-form').forEach((form) => {
            const maxFileBytes = Number(form.dataset.maxFileBytes || 7864320);
            const errorBox = form.querySelector('.js-upload-size-error');
            const submitButton = form.querySelector('.opp-upload-submit');
            const formatMb = (bytes) => (bytes / (1024 * 1024)).toFixed(2);
            const maxMb = (maxFileBytes / (1024 * 1024)).toFixed(1);

            const updateInputState = (input) => {
                const card = input.closest('.opp-upload-card');
                const picker = input.closest('.opp-file-picker');
                const file = input.files?.[0] || null;
                const fileName = picker?.querySelector('.opp-file-name');
                const sizeError = card?.querySelector('[data-file-size-error]');
                const isTooLarge = Boolean(file && file.size > maxFileBytes);

                if (fileName) {
                    fileName.textContent = file ? file.name : 'No file chosen';
                }

                if (card) {
                    card.classList.toggle('has-file', Boolean(file));
                    card.classList.toggle('is-too-large', isTooLarge);
                    if (file) {
                        card.classList.remove('is-rejected');
                    }
                }

                if (sizeError) {
                    sizeError.textContent = isTooLarge
                        ? `File is ${formatMb(file.size)} MB. Maximum allowed is ${maxMb} MB.`
                        : '';
                }

                return isTooLarge;
            };

            const validateFileSizes = () => {
                const inputs = Array.from(form.querySelectorAll('input[type="file"]'));
                const oversizedInputs = inputs.filter((input) => updateInputState(input));

                if (oversizedInputs.length === 0) {
                    if (errorBox) {
                        errorBox.classList.remove('is-visible');
                        errorBox.classList.remove('is-info');
                        errorBox.textContent = '';
                    }
                    return true;
                }

                if (errorBox) {
                    errorBox.textContent = `Please keep each file under ${maxMb} MB. The highlighted upload ${oversizedInputs.length === 1 ? 'box has a file' : 'boxes have files'} over the limit.`;
                    errorBox.classList.add('is-visible');
                    errorBox.classList.remove('is-info');
                }

                return false;
            };

            const setUploadMessage = (message, isInfo = false) => {
                if (!errorBox) {
                    return;
                }

                errorBox.textContent = message;
                errorBox.classList.add('is-visible');
                errorBox.classList.toggle('is-info', isInfo);
            };

            const uploadInput = async (input) => {
                const token = form.querySelector('input[name="_token"]')?.value;
                const body = new FormData();

                if (token) {
                    body.append('_token', token);
                }

                body.append(input.name, input.files[0]);

                const response = await fetch(form.action, {
                    method: 'POST',
                    body,
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (response.ok) {
                    return;
                }

                let message = 'Upload failed. Please try again.';
                try {
                    const data = await response.json();
                    message = data.message || Object.values(data.errors || {}).flat()[0] || message;
                } catch (error) {
                    if (response.status === 413) {
                        message = `Please keep each file under ${maxMb} MB.`;
                    }
                }

                throw new Error(message);
            };

            form.querySelectorAll('input[type="file"]').forEach((input) => {
                input.addEventListener('change', () => validateFileSizes());
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();

                if (!validateFileSizes()) {
                    const firstOversizedCard = form.querySelector('.opp-upload-card.is-too-large');
                    (firstOversizedCard || errorBox)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }

                const selectedInputs = Array.from(form.querySelectorAll('input[type="file"]'))
                    .filter((input) => input.files?.[0]);

                if (selectedInputs.length === 0) {
                    setUploadMessage('Please choose at least one document to upload.');
                    return;
                }

                const confirmed = await confirmClientStageAction({
                    title: 'Upload documents?',
                    text: 'Upload the selected document(s) to the legal team?',
                    confirmButtonText: 'Yes, upload',
                });
                if (!confirmed) {
                    return;
                }

                window.LegalBruzButtonLoading?.set(submitButton, 'Submitting...');

                if (form.querySelector('input[name^="requested_documents"]')) {
                    HTMLFormElement.prototype.submit.call(form);
                    return;
                }

                try {
                    for (const [index, input] of selectedInputs.entries()) {
                        setUploadMessage(`Uploading document ${index + 1} of ${selectedInputs.length}...`, true);
                        await uploadInput(input);
                    }

                    setUploadMessage('Documents uploaded successfully. Refreshing...', true);
                    window.location.reload();
                } catch (error) {
                    setUploadMessage(error.message || 'Upload failed. Please try again.');
                    window.LegalBruzButtonLoading?.reset(submitButton);
                }
            });
        });

        document.querySelectorAll('.js-evidence-upload-form').forEach((form) => {
            const maxFileBytes = Number(form.dataset.maxFileBytes || 10485760);
            const errorBox = form.querySelector('.js-evidence-size-error');
            const submitButton = form.querySelector('.opp-upload-submit');
            const draftButton = form.querySelector('.js-evidence-draft');
            const draftInput = form.querySelector('[data-evidence-draft-input]');
            const maxMb = (maxFileBytes / (1024 * 1024)).toFixed(0);
            const formatMb = (bytes) => (bytes / (1024 * 1024)).toFixed(2);

            const setMessage = (message, isInfo = false) => {
                if (!errorBox) {
                    return;
                }

                errorBox.textContent = message;
                errorBox.classList.add('is-visible');
                errorBox.classList.toggle('is-info', isInfo);
            };

            const clearMessage = () => {
                if (!errorBox) {
                    return;
                }

                errorBox.textContent = '';
                errorBox.classList.remove('is-visible', 'is-info');
            };

            const updateInputState = (input) => {
                const card = input.closest('.opp-evidence-card');
                const state = card?.querySelector('.opp-evidence-state');
                const sizeError = card?.querySelector('[data-file-size-error]');
                const files = Array.from(input.files || []);
                const oversizedFile = files.find((file) => file.size > maxFileBytes);

                if (state && files.length > 0) {
                    state.textContent = files.length === 1 ? files[0].name : `${files.length} files selected`;
                } else if (state && !card?.classList.contains('has-file')) {
                    state.textContent = 'No file uploaded';
                }

                card?.classList.toggle('has-file', files.length > 0);
                card?.classList.toggle('is-too-large', Boolean(oversizedFile));
                card?.classList.remove('is-drag-over');

                if (sizeError) {
                    sizeError.textContent = oversizedFile
                        ? `File is ${formatMb(oversizedFile.size)} MB. Maximum allowed is ${maxMb} MB.`
                        : '';
                }

                return Boolean(oversizedFile);
            };

            const validateFileSizes = () => {
                const oversizedInputs = Array.from(form.querySelectorAll('input[type="file"]'))
                    .filter((input) => updateInputState(input));

                if (oversizedInputs.length === 0) {
                    clearMessage();
                    return true;
                }

                setMessage(`Please keep each uploaded file under ${maxMb} MB.`);
                return false;
            };

            form.querySelectorAll('input[type="file"]').forEach((input) => {
                input.addEventListener('change', validateFileSizes);

                const dropZone = input.closest('.opp-evidence-drop');
                const card = input.closest('.opp-evidence-card');

                dropZone?.addEventListener('dragenter', (event) => {
                    event.preventDefault();
                    card?.classList.add('is-drag-over');
                });

                dropZone?.addEventListener('dragover', (event) => {
                    event.preventDefault();
                    card?.classList.add('is-drag-over');
                });

                dropZone?.addEventListener('dragleave', (event) => {
                    if (!dropZone.contains(event.relatedTarget)) {
                        card?.classList.remove('is-drag-over');
                    }
                });

                dropZone?.addEventListener('drop', (event) => {
                    event.preventDefault();
                    card?.classList.remove('is-drag-over');

                    if (!event.dataTransfer?.files?.length) {
                        return;
                    }

                    input.files = event.dataTransfer.files;
                    validateFileSizes();
                });
            });

            form.addEventListener('submit', async (event) => {
                if (!validateFileSizes()) {
                    event.preventDefault();
                    const firstOversizedCard = form.querySelector('.opp-evidence-card.is-too-large');
                    (firstOversizedCard || errorBox)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }

                const clickedButton = event.submitter || submitButton;
                if (draftInput) {
                    draftInput.value = clickedButton === draftButton ? '1' : '0';
                }
                const isDraft = clickedButton === draftButton;
                const confirmMessage = isDraft
                    ? 'Save this evidence as a draft for later submission?'
                    : 'Submit this evidence to the legal team for review?';
                event.preventDefault();
                const confirmed = await confirmClientStageAction({
                    title: isDraft ? 'Save draft?' : 'Submit evidence?',
                    text: confirmMessage,
                    confirmButtonText: isDraft ? 'Yes, save draft' : 'Yes, submit',
                });
                if (!confirmed) {
                    return;
                }
                const loadingText = isDraft ? 'Saving...' : 'Submitting...';
                window.LegalBruzButtonLoading?.set(clickedButton, loadingText);
                HTMLFormElement.prototype.submit.call(form);
            });
        });

        document.querySelectorAll('.js-opposition-payment-form').forEach((form) => {
            const button = form.querySelector('button[type="submit"]');
            const amountText = form.querySelector('[data-opposition-button-amount]');
            const payableText = document.querySelector('[data-opposition-payable]');
            const couponInputs = Array.from(document.querySelectorAll('input[name="opposition_discount_coupon_id"]'));

            const selectedCoupon = () => couponInputs.find((input) => input.checked);
            const updateAmountText = () => {
                const selected = selectedCoupon();
                const amount = selected?.dataset.payableAmount || form.dataset.amount || '0.00';
                const formatted = Number(amount).toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
                if (amountText) amountText.textContent = formatted;
                if (payableText) payableText.textContent = formatted;
            };

            couponInputs.forEach((input) => input.addEventListener('change', updateAmountText));
            updateAmountText();

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                event.stopImmediatePropagation();

                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                const confirmed = await confirmClientStageAction({
                    title: 'Proceed to payment?',
                    text: `You will be redirected to Razorpay checkout for ₹${amountText?.textContent || form.dataset.amount}.`,
                    confirmButtonText: 'Yes, pay now',
                });

                if (!confirmed) {
                    return;
                }

                window.LegalBruzButtonLoading?.set(button, 'Creating order...');

                try {
                    const response = await fetch(form.dataset.createOrderUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': form.querySelector('input[name="_token"]')?.value || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            success_disclaimer: form.querySelector('input[name="success_disclaimer"]')?.checked ? '1' : '',
                            discount_coupon_id: selectedCoupon()?.value || null,
                        }),
                    });
                    const orderData = await response.json();

                    if (!response.ok || orderData.status !== 'success') {
                        throw new Error(orderData.message || 'Unable to create payment order.');
                    }

                    const razorpay = new Razorpay({
                        key: orderData.key,
                        amount: orderData.amount,
                        currency: orderData.currency,
                        name: '{{ config('app.name') }}',
                        description: orderData.description,
                        order_id: orderData.order_id,
                        prefill: {
                            name: orderData.user_name,
                            email: orderData.user_email,
                            contact: orderData.user_phone,
                        },
                        theme: { color: '#2A9D8F' },
                        handler: async (paymentResponse) => {
                            window.LegalBruzButtonLoading?.set(button, 'Verifying payment...');
                            const verifyResponse = await fetch(form.dataset.verifyUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]')?.value || '',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify(paymentResponse),
                            });
                            const verifyData = await verifyResponse.json();

                            if (!verifyResponse.ok || verifyData.status !== 'success') {
                                throw new Error(verifyData.message || 'Payment verification failed.');
                            }

                            if (window.Swal) {
                                await Swal.fire({
                                    icon: 'success',
                                    title: 'Payment Successful',
                                    text: 'Your payment has been received. Redirecting...',
                                    confirmButtonColor: '#2A9D8F',
                                });
                            }

                            window.location.href = verifyData.redirect_url;
                        },
                        modal: {
                            ondismiss: () => {
                                window.LegalBruzButtonLoading?.reset(button);
                            },
                        },
                    });

                    razorpay.open();
                    window.LegalBruzButtonLoading?.reset(button);
                } catch (error) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Payment Error',
                            text: error.message || 'Unable to process payment.',
                            confirmButtonColor: '#2A9D8F',
                        });
                    }
                    window.LegalBruzButtonLoading?.reset(button);
                }
            });
        });

        document.querySelectorAll('.opp-action-card form:not(.js-opposition-upload-form):not(.js-evidence-upload-form):not(.js-opposition-payment-form):not(.js-draft-decision-form)').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                if (form.dataset.clientConfirmed === 'true') {
                    return;
                }

                event.preventDefault();
                const submitterText = event.submitter?.textContent?.trim();
                const actionName = submitterText || 'this action';
                const confirmed = await confirmClientStageAction({
                    title: 'Submit action?',
                    text: `Submit ${actionName}?`,
                    confirmButtonText: 'Yes, submit',
                });
                if (!confirmed) {
                    return;
                }

                form.dataset.clientConfirmed = 'true';
                window.LegalBruzButtonLoading?.set(event.submitter, 'Submitting...');
                HTMLFormElement.prototype.submit.call(form);
            });
        });
    </script>
@endsection
