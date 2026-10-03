@extends('layouts.app')

@section('content')
    @php
        $workflow = \App\Support\TrademarkOppositionWorkflow::class;
        $displayTimezone = config('app.timezone', 'Europe/London');
        $formatDateTime = fn ($timestamp, string $format = 'd M Y, h:i A T') => $timestamp
            ? \Illuminate\Support\Carbon::parse($timestamp)->timezone($displayTimezone)->format($format)
            : null;
        $requiredDocuments = \App\Support\TrademarkOppositionWorkflow::requiredDocuments();
        $optionalDocuments = \App\Support\TrademarkOppositionWorkflow::optionalDocuments();
        $allDocumentTypes = $requiredDocuments + $optionalDocuments;
        $evidenceTypes = \App\Support\TrademarkOppositionWorkflow::evidenceTypes();
        $currentStatus = $case->current_admin_status;
        $latestDocuments = $case->documents->sortByDesc('id')->unique('document_type')->keyBy('document_type');
        $latestUploadedDocuments = $latestDocuments->filter();
        $allLatestDocumentsReviewed = $latestUploadedDocuments->isNotEmpty()
            && $latestUploadedDocuments->every(fn ($document) => $document->review_status === 'reviewed');
        $latestEvidence = $case->evidence
            ->where('file_path', '!=', 'metadata')
            ->sortByDesc('id')
            ->unique('evidence_type')
            ->keyBy('evidence_type');
        $reviewableLatestEvidence = $latestEvidence
            ->filter(fn ($evidence) => $evidence->uploaded_by === 'client');
        $hasVerifiedOppositionPayment = $case->payment_status === 'paid'
            && filled($case->payment_reference)
            && filled($case->transaction_id)
            && filled($case->paid_at);
        $allLatestEvidenceReviewed = $reviewableLatestEvidence->isNotEmpty()
            && $reviewableLatestEvidence->every(fn ($evidence) => $evidence->review_status === 'reviewed');
        $selectableLatestEvidence = $reviewableLatestEvidence
            ->filter(fn ($evidence) => !in_array($evidence->review_status, ['reviewed', 'rejected'], true))
            ->values();
        $evidenceNeedsReview = $currentStatus === \App\Support\TrademarkOppositionWorkflow::ADMIN_LEGAL_ANALYSIS
            && $reviewableLatestEvidence->isNotEmpty()
            && !$allLatestEvidenceReviewed;
        $documentsNeedReview = $currentStatus === \App\Support\TrademarkOppositionWorkflow::ADMIN_OPPOSITION_REVIEW
            && $latestUploadedDocuments->isNotEmpty()
            && $latestUploadedDocuments->contains(fn ($document) => $document->review_status !== 'reviewed');
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
        $evidenceRequestTypes = [
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
        $riskOptions = [
            'Low Risk' => [
                'subheading' => 'Weak opposition',
                'content' => 'Strong defence available.',
            ],
            'Medium Risk' => [
                'subheading' => 'Requires substantial evidence',
                'content' => 'Possible hearing.',
            ],
        ];
        $defenceOutcomeOptions = \App\Support\TrademarkOppositionWorkflow::defenceOutcomeOptions();
        $defenceFinalOutcomeOptions = \App\Support\TrademarkOppositionWorkflow::defenceFinalOutcomeOptions();
        $defenceOutcomeLabel = \App\Support\TrademarkOppositionWorkflow::defenceOutcomeLabel($case->defence_case_status);
        $registryDocumentRequests = collect($case->third_party_evidence_requests ?? [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values();
        $latestRegistryStageEvidence = $case->evidence
            ->where('file_path', '!=', 'metadata')
            ->where('uploaded_by', 'client')
            ->filter(fn ($evidence) => \Illuminate\Support\Str::startsWith($evidence->evidence_type, 'third_party_requested_evidence_'))
            ->sortByDesc('id')
            ->unique('evidence_type')
            ->values();
        $registryStageEvidenceNeedsReview = $latestRegistryStageEvidence->isNotEmpty()
            && $latestRegistryStageEvidence->contains(fn ($evidence) => $evidence->review_status !== 'reviewed');
        $registryStageEvidenceReviewed = $latestRegistryStageEvidence->isNotEmpty()
            && $latestRegistryStageEvidence->every(fn ($evidence) => $evidence->review_status === 'reviewed');
        $registryRequestCanBeEdited = $registryDocumentRequests->isNotEmpty() && !$registryStageEvidenceReviewed;
        $nextDefendStage = $currentStatus === \App\Support\TrademarkOppositionWorkflow::ADMIN_FINAL_OUTCOME
            ? \App\Support\TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED
            : \App\Support\TrademarkOppositionWorkflow::nextDefendStage($currentStatus);
        $currentStageDescription = \App\Support\TrademarkOppositionWorkflow::defendStageDescription($currentStatus);
        $nextStageDescription = \App\Support\TrademarkOppositionWorkflow::defendNextStageDescription($nextDefendStage);
        $nextStageButtonLabel = $currentStatus === \App\Support\TrademarkOppositionWorkflow::ADMIN_FINAL_OUTCOME
            ? 'Close Matter & Notify Client'
            : \App\Support\TrademarkOppositionWorkflow::defendStageButtonLabel($nextDefendStage);
        $selectedGrounds = $case->grounds->pluck('ground_name')->all();
        $selectedEvidenceTypes = $case->requested_evidence_types ?? [];
        $hasEvidenceRequest = $case->grounds->isNotEmpty() || !empty($selectedEvidenceTypes) || filled($case->evidence_request_note);
        $editingEvidenceRequest = request()->boolean('edit_evidence_request') || $errors->has('grounds') || $errors->has('evidence_types');
        $editingRegistryRequest = request()->boolean('edit_registry_request') || $errors->has('evidence_items') || old('message_to_client') !== null;
        $editingFinalOutcome = request()->boolean('edit_final_outcome')
            || $errors->has('final_outcome')
            || $errors->has('final_order')
            || $errors->has('optional_documents');
        $showRegistryRequestForm = $registryDocumentRequests->isEmpty() || ($registryRequestCanBeEdited && $editingRegistryRequest);
        $resendingRegistryRequest = $registryRequestCanBeEdited && $editingRegistryRequest;
        $defendHearingStatuses = [
            $workflow::ADMIN_HEARING_PREPARATION,
            $workflow::ADMIN_HEARING_SCHEDULED,
            $workflow::ADMIN_HEARING_ADJOURNED,
            $workflow::ADMIN_HEARING_COMPLETED,
        ];
        $isDefendHearingStatus = in_array($currentStatus, $defendHearingStatuses, true);
        $hearingDocuments = $case->evidence->where('evidence_type', 'hearing_notice_document');
        $latestHearingHistory = $case->statusHistories
            ->filter(fn ($history) => in_array($history->new_status, $defendHearingStatuses, true) && $history->changed_by === 'admin')
            ->sortByDesc('id')
            ->first();
        $latestHearingNote = (string) ($latestHearingHistory?->note ?? '');
        $extractHearingNotePart = static function (string $note, string $label): string {
            $pattern = '/(?:^|\\s)' . preg_quote($label, '/') . ':\\s*(.*?)(?=\\s(?:Adjournment reason|Hearing outcome):|$)/';

            return preg_match($pattern, $note, $matches) ? trim($matches[1]) : '';
        };
        $hearingAdjournmentReason = $extractHearingNotePart($latestHearingNote, 'Adjournment reason');
        $hearingOutcomeValue = $extractHearingNotePart($latestHearingNote, 'Hearing outcome');
        $hearingAdminNote = trim(preg_replace('/\\s(?:Adjournment reason|Hearing outcome):\\s*.*$/', '', $latestHearingNote));
        $hearingFormWasSubmitted = $case->statusHistories->contains(fn ($history) => in_array($history->old_status, $defendHearingStatuses, true)
            && in_array($history->new_status, $defendHearingStatuses, true)
            && $history->changed_by === 'admin');
        $editingHearingForm = request()->boolean('edit_hearing_form')
            || $errors->has('hearing_date')
            || $errors->has('hearing_notice')
            || $errors->has('hearing_outcome')
            || $errors->has('adjournment_reason')
            || ($isDefendHearingStatus && old('status') !== null);
        $hearingFormHasSubmission = $isDefendHearingStatus
            && (
                filled($case->hearing_date)
                || filled($case->final_outcome)
                || $hearingDocuments->isNotEmpty()
                || $hearingFormWasSubmitted
            );
        $showHearingForm = !($hearingFormHasSubmission && !$editingHearingForm);

        $activeAction = 'none';
        if ($editingEvidenceRequest) {
            $activeAction = 'request-evidence';
        } elseif ($latestUploadedDocuments->isEmpty() || in_array($currentStatus, [\App\Support\TrademarkOppositionWorkflow::ADMIN_APPLICATION_RECEIVED, \App\Support\TrademarkOppositionWorkflow::ADMIN_DOCUMENTS_PENDING], true)) {
            $activeAction = 'none';
        } elseif ($documentsNeedReview) {
            $activeAction = 'view-documents';
        } elseif ($currentStatus === \App\Support\TrademarkOppositionWorkflow::ADMIN_EVIDENCE_COLLECTION || ($allLatestDocumentsReviewed && !$hasEvidenceRequest)) {
            $activeAction = 'request-evidence';
        } elseif ($evidenceNeedsReview) {
            $activeAction = 'review-evidence';
        } elseif (!$case->risk_level || !$case->package_price || $currentStatus === \App\Support\TrademarkOppositionWorkflow::ADMIN_LEGAL_ANALYSIS) {
            $activeAction = 'risk';
        } elseif (!$hasVerifiedOppositionPayment) {
            $activeAction = 'await-payment';
        } elseif (!$case->draft_path || in_array($currentStatus, [\App\Support\TrademarkOppositionWorkflow::ADMIN_COUNTER_STATEMENT_DRAFTING, \App\Support\TrademarkOppositionWorkflow::ADMIN_CLIENT_APPROVAL], true)) {
            $activeAction = 'draft';
        } elseif ($currentStatus === \App\Support\TrademarkOppositionWorkflow::ADMIN_READY_FOR_FILING) {
            $activeAction = 'filing';
        } elseif (in_array($currentStatus, $trackingStatuses, true) || in_array($currentStatus, [
            \App\Support\TrademarkOppositionWorkflow::ADMIN_COUNTER_STATEMENT_FILED,
            \App\Support\TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED,
        ], true)) {
            $activeAction = 'tracking';
        }
        $oppositionTimelineRank = match ($currentStatus) {
            $workflow::ADMIN_COUNTER_STATEMENT_FILED, $workflow::ADMIN_AWAITING_EVIDENCE_STAGE => 2,
            $workflow::ADMIN_EVIDENCE_BY_OPPONENT => 3,
            $workflow::ADMIN_EVIDENCE_BY_APPLICANT => 4,
            $workflow::ADMIN_EVIDENCE_IN_REPLY, $workflow::ADMIN_EVIDENCE_FILED => 5,
            $workflow::ADMIN_HEARING_PREPARATION, $workflow::ADMIN_HEARING_SCHEDULED, $workflow::ADMIN_HEARING_ADJOURNED, $workflow::ADMIN_HEARING_COMPLETED => 6,
            $workflow::ADMIN_DECISION_AWAITED, $workflow::ADMIN_FINAL_OUTCOME => 7,
            $workflow::ADMIN_OPPOSITION_ALLOWED, $workflow::ADMIN_OPPOSITION_DISMISSED, $workflow::ADMIN_SETTLEMENT_CLOSED,
            $workflow::ADMIN_MATTER_CLOSED => 8,
            default => 1,
        };
        $oppositionTimelineSteps = [
            'Trademark Advertised',
            'Opposition Filed',
            'Counter Statement Filed',
            'Evidence by Opponent',
            'Evidence by Applicant',
            'Evidence in Reply',
            'Hearing',
            'Decision',
        ];
    @endphp

    <style>
        .admin-opp{max-width:1440px;margin:0 auto 32px;padding:0 18px;color:#22324a;font-size:.95rem}.admin-shell{display:grid;margin-top:0;grid-template-columns:minmax(0,1fr) 420px;gap:22px;align-items:start}.admin-main{min-width:0;margin-top:0}.admin-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:18px}.admin-head h1{font-size:1.6rem;line-height:1.2;margin:0;color:#102a4c;font-weight:900}.admin-sub{margin:.35rem 0 0;color:#607089;font-weight:700}.admin-badge{display:inline-flex;border-radius:999px;padding:7px 12px;background:#eaf1ff;color:#174ea6;font-size:.78rem;font-weight:900;white-space:nowrap}.admin-card{background:#fff;padding:0;border:1px solid #dfe8f4;border-radius:9px;box-shadow:0 12px 26px rgba(8,36,90,.06);margin-bottom:18px;overflow:hidden}.admin-card-head{background:#203e68;color:#fff;padding:18px 24px}.admin-card-head h2{font-size:1.2rem;margin:0;font-weight:900;color:#fff!important;line-height:1.25}.admin-card-body{padding:22px 24px}.admin-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.admin-field{padding:13px;border:1px solid #edf1f7;border-radius:7px;background:#fbfdff;min-width:0}.admin-field span{display:block;color:#66758b;font-size:.78rem;font-weight:900;text-transform:uppercase;margin-bottom:4px}.admin-field strong{display:block;color:#1d2b41;font-size:.95rem;line-height:1.45;overflow-wrap:anywhere}.admin-table{width:100%;border-collapse:collapse}.admin-table th{background:#f7f9fc;color:#202633;font-size:.78rem;text-transform:uppercase;letter-spacing:0;font-weight:900;text-align:left;padding:12px}.admin-table td{border-top:1px solid #e6ebf2;padding:12px;vertical-align:middle;color:#3f4b5d;font-size:.95rem}.admin-table td:nth-child(2),.admin-table th:nth-child(2){text-align:center}.admin-table td:last-child,.admin-table th:last-child{text-align:right}.admin-doc-type{font-weight:900;color:#202633}.admin-status-pill{display:inline-flex;align-items:center;justify-content:center;min-width:132px;white-space:nowrap;border-radius:999px;padding:7px 14px;background:#eaf8f0;color:#16814a;font-size:.78rem;font-weight:900}.admin-status-pill.is-muted{background:#f3f5f8;color:#66758b}.admin-status-pill.is-warning{background:#fff7ed;color:#b45309}.admin-status-pill.is-info{background:#e0f2fe;color:#0369a1}.admin-status-pill.is-danger{background:#fee2e2;color:#b91c1c}.admin-link{color:#2a9d8f;font-weight:900;text-decoration:none}.admin-link:hover{color:#207d72}.admin-timeline{list-style:none;margin:0;padding:0}.admin-timeline li{border-bottom:1px solid #eef2f7;padding:12px 0}.admin-timeline li:first-child{padding-top:0}.admin-timeline li:last-child{border-bottom:0;padding-bottom:0}.admin-timeline strong{color:#203e68;font-size:.95rem}.admin-timeline small{display:block;color:#6b7890;margin-top:3px;font-size:.84rem}.stage-panel{position:sticky;top:90px;background:#fff;border:1px solid #dfe8f4;border-radius:9px;box-shadow:0 16px 34px rgba(8,36,90,.1);overflow:hidden}.stage-panel-head{background:#203e68;color:#fff;padding:18px 24px}.stage-panel-head h2{font-size:1.2rem;margin:0;font-weight:900;color:#fff!important;line-height:1.25}.stage-panel-body{padding:24px}.stage-note{border-left:4px solid #2a9d8f;background:#f2fbf8;border-radius:7px;padding:15px 16px;margin-bottom:18px;color:#203e68;line-height:1.55;font-size:.95rem;font-weight:600}.admin-input{width:100%;min-height:42px;border:1px solid #d9e0ea;border-radius:7px;padding:9px 11px;color:#202633;background:#fff;font-size:.95rem}.admin-input:focus{outline:2px solid rgba(42,157,143,.18);border-color:#2a9d8f}.admin-label{display:block;color:#263c5c;font-size:.95rem;font-weight:900;margin:0 0 8px}.admin-btn{min-height:42px;border:0;border-radius:7px;background:#2a9d8f;color:#fff;font-size:.95rem;font-weight:900;padding:0 16px;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:7px}.admin-btn:hover{color:#fff;background:#23867a}.admin-btn-secondary{background:#203e68}.admin-btn-secondary:hover{background:#193354}.admin-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.admin-checks{display:grid;grid-template-columns:1fr;gap:8px}.admin-checks label{border:1px solid #edf1f7;border-radius:7px;padding:9px 10px;font-size:.95rem;font-weight:800;background:#fbfdff}.admin-review-list{display:grid;gap:12px}.admin-review-item{border:1px solid #dfe8f4;border-radius:8px;background:#fbfdff;padding:12px}.admin-review-item strong{display:block;color:#203e68;margin-bottom:8px}.admin-empty{margin:0;color:#66758b}.admin-current-file{font-size:.86rem;margin:.7rem 0 0}.mt-2{margin-top:.5rem}.mb-2{margin-bottom:.5rem}@media(max-width:1100px){.admin-shell{grid-template-columns:1fr}.admin-main{margin-top:0}.stage-panel{position:static}.admin-detail-grid{grid-template-columns:1fr}}@media(max-width:640px){.admin-opp{padding:0 12px}.admin-head{display:block}.admin-head h1{font-size:1.35rem}.admin-badge{margin-top:10px}.admin-card-body,.stage-panel-body{padding:16px}.admin-card-head,.stage-panel-head{padding:16px}.admin-card-head h2,.stage-panel-head h2{font-size:1.1rem}.admin-table{display:block;overflow-x:auto;white-space:nowrap}}
    </style>
    <style>
        [data-doc-card] .admin-card-head,[data-evidence-card] .admin-card-head{display:flex;align-items:center;justify-content:space-between;gap:12px}.admin-select-btn{min-height:34px;border:1px solid rgba(255,255,255,.55);border-radius:7px;background:rgba(255,255,255,.12);color:#fff;font-weight:900;padding:0 14px}.admin-select-btn:hover{background:rgba(255,255,255,.2)}.admin-doc-select{display:none;margin-right:10px;transform:scale(1.15)}[data-doc-card].is-selecting .admin-doc-select,[data-evidence-card].is-selecting .admin-doc-select{display:inline-block}.admin-doc-bulk-actions{display:none;align-items:center;gap:10px;justify-content:flex-end;margin-top:16px}.admin-doc-bulk-actions.is-visible{display:flex}.admin-btn-danger{background:#dc2626}.admin-btn-danger:hover{background:#b91c1c}.admin-review-modal{position:fixed;inset:0;z-index:1050;display:none;align-items:center;justify-content:center;background:rgba(15,35,60,.45);padding:18px}.admin-review-modal.is-visible{display:flex}.admin-review-dialog{width:min(520px,100%);border-radius:9px;background:#fff;box-shadow:0 24px 60px rgba(15,35,60,.28);overflow:hidden}.admin-review-dialog header{background:#203e68;color:#fff;padding:16px 20px}.admin-review-dialog header h3{margin:0;color:#fff!important;font-size:1.1rem;font-weight:900}.admin-review-dialog-body{padding:20px}.admin-review-dialog-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:14px}.admin-btn-light{background:#e5eaf2;color:#203e68}.admin-btn-light:hover{background:#d8e0eb;color:#203e68}
        .admin-table{table-layout:fixed}.admin-table th:nth-child(1),.admin-table td:nth-child(1){width:18%}.admin-table th:nth-child(2),.admin-table td:nth-child(2){width:18%}.admin-table th:nth-child(3),.admin-table td:nth-child(3){width:auto;font-size:.86rem;line-height:1.35;overflow-wrap:anywhere;word-break:break-word}.admin-table th:last-child,.admin-table td:last-child{width:92px;white-space:nowrap}
        .admin-btn{font-weight:500}
        .linked-opposition-card{border:1px solid #dfe8f4;border-radius:8px;background:#fbfdff;padding:13px;margin-bottom:16px}.linked-opposition-card span{display:block;color:#66758b;font-size:.76rem;font-weight:900;text-transform:uppercase;margin-bottom:4px}.linked-opposition-card strong{display:block;color:#203e68;font-size:.95rem;font-weight:900;line-height:1.35;overflow-wrap:anywhere}.linked-opposition-meta{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 12px}.linked-opposition-card .admin-link{display:inline-flex;align-items:center;gap:6px}
        .stage-summary-card{border:1px solid #dfe8f4;border-radius:9px;background:#fbfdff;padding:13px;margin-bottom:14px}.stage-summary-card.is-next{background:#f2fbf8;border-color:#bfe8dc}.stage-summary-card span{display:block;color:#66758b;font-size:.74rem;font-weight:900;text-transform:uppercase;margin-bottom:4px}.stage-summary-card strong{display:block;color:#203e68;font-size:1rem;font-weight:900;line-height:1.35}.stage-summary-card p{margin:6px 0 0;color:#536176;font-size:.88rem;line-height:1.45;font-weight:650}.requested-document-list{display:grid;gap:8px}.requested-document-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:center}.requested-document-remove{border:1px solid #fecaca;background:#fff;color:#b91c1c;border-radius:7px;min-height:38px;padding:0 10px;font-weight:900}.requested-document-remove:hover{background:#fee2e2}
        .stage-summary-card.is-review-alert{background:#fff7ed;border-color:#fdba74;box-shadow:0 0 0 3px rgba(251,146,60,.12)}.stage-summary-card.is-review-alert span{color:#c2410c}.stage-summary-card.is-review-alert strong{color:#9a3412}.admin-table tr.is-review-target{background:#fff7ed}.admin-table tr.is-scroll-highlight{animation:reviewPulse 1.4s ease-in-out 2}@keyframes reviewPulse{0%,100%{box-shadow:inset 0 0 0 0 rgba(245,158,11,0)}35%{box-shadow:inset 4px 0 0 #f59e0b;background:#fffbeb}}
    </style>
    <style>
        .evidence-request-summary{display:grid;gap:14px}.evidence-summary-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}.evidence-summary-head h3{margin:0;color:#203e68;font-size:1rem;font-weight:900}.evidence-summary-head p{margin:4px 0 0;color:#607089;font-weight:700;font-size:.9rem;line-height:1.45}.evidence-summary-card{border:1px solid #dfe8f4;border-radius:8px;background:#fbfdff;padding:13px}.evidence-summary-card h4{margin:0 0 10px;color:#203e68;font-size:.95rem;font-weight:900}.evidence-chip-list{display:flex;flex-wrap:wrap;gap:8px}.evidence-chip{display:inline-flex;align-items:center;border-radius:999px;background:#eef6ff;color:#174ea6;border:1px solid #cfe0ff;padding:6px 10px;font-size:.82rem;font-weight:900}.evidence-chip.is-ground{background:#ecfdf3;color:#17653b;border-color:#b9ebcc}.evidence-summary-note{margin:0;color:#3f4b5d;line-height:1.55;font-weight:650}.evidence-edit-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:2px}
        .risk-option-grid{display:grid;gap:10px}.risk-option{position:relative;display:grid;grid-template-columns:auto 1fr;gap:10px;border:1px solid #dfe8f4;border-radius:8px;background:#fbfdff;padding:12px;cursor:pointer}.risk-option input{margin-top:4px}.risk-option strong{display:block;color:#203e68;font-size:.95rem;font-weight:900}.risk-option span{display:block;color:#102a4c;font-weight:900;margin-top:2px}.risk-option p{margin:5px 0 0;color:#607089;font-size:.88rem;line-height:1.45;font-weight:650}.risk-option:has(input:checked){border-color:#2a9d8f;background:#f2fbf8;box-shadow:0 0 0 2px rgba(42,157,143,.12)}
        .evidence-meta-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.evidence-meta-item{border:1px solid #e6edf7;border-radius:7px;background:#fff;padding:10px}.evidence-meta-item span{display:block;color:#66758b;font-size:.74rem;font-weight:900;text-transform:uppercase;margin-bottom:4px}.evidence-meta-item strong{display:block;color:#1d2b41;font-size:.92rem;line-height:1.35;overflow-wrap:anywhere}@media(max-width:900px){.evidence-meta-grid{grid-template-columns:1fr}}
        .admin-registry-steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;counter-reset:registry}.admin-registry-step{position:relative;border:1px solid #dfe8f4;border-radius:10px;background:#fbfdff;padding:15px 14px 14px 46px;color:#607089;font-weight:850;min-height:68px}.admin-registry-step::before{counter-increment:registry;content:counter(registry);position:absolute;left:14px;top:14px;width:24px;height:24px;border-radius:50%;display:grid;place-items:center;background:#e7edf6;color:#607089;font-size:.78rem;font-weight:900}.admin-registry-step.is-complete{border-color:#a8e6bf;background:#f0fbf5;color:#137747}.admin-registry-step.is-complete::before{background:#159447;color:#fff}.admin-registry-step.is-current{border-color:#8fb3ff;background:#f3f7ff;color:#174ea6;box-shadow:0 0 0 2px rgba(23,78,166,.08)}@media(max-width:900px){.admin-registry-steps{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:560px){.admin-registry-steps{grid-template-columns:1fr}}
    </style>

    <div class="admin-opp">
        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if (session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif
        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        <div class="admin-head">
            <div>
                <h1>{{ $case->case_number }} · {{ $case->trademark_name }}</h1>
                <p class="admin-sub">Flow A: Defend My Trademark</p>
            </div>
            <span class="admin-badge">{{ $case->current_admin_status }}</span>
        </div>

        <div class="admin-shell">
            <div class="admin-main">
                <section class="admin-card">
                    <header class="admin-card-head"><h2>Opposition Application</h2></header>
                    <div class="admin-card-body">
                        <div class="admin-detail-grid">
                            <div class="admin-field"><span>Applicant</span><strong>{{ $case->applicant_name }}</strong></div>
                            <div class="admin-field"><span>Email / Mobile</span><strong>{{ $case->email }} · {{ $case->mobile_number }}</strong></div>
                            <div class="admin-field"><span>Application</span><strong>{{ $case->application_number }} · Class {{ $case->trademark_class }}</strong></div>
                            <div class="admin-field"><span>Counter Statement Deadline</span><strong>{{ $case->counter_statement_deadline->format('d M Y') }} · {{ $case->deadline_status_label }}</strong></div>
                            <div class="admin-field"><span>Client Stage</span><strong>{{ $case->current_client_stage }}</strong></div>
                            <div class="admin-field"><span>Risk</span><strong>{{ $case->risk_level ? $case->risk_level . ' · ' . $case->risk_note : 'Not assessed' }}</strong></div>
                            <div class="admin-field"><span>Defence Result</span><strong>{{ $defenceOutcomeLabel ?: 'Not set' }}</strong></div>
                        </div>
                    </div>
                </section>

                <section class="admin-card">
                    <header class="admin-card-head"><h2>Opposition Timeline</h2></header>
                    <div class="admin-card-body">
                        <div class="admin-registry-steps">
                            @foreach ($oppositionTimelineSteps as $timelineIndex => $timelineLabel)
                                <div class="admin-registry-step {{ $timelineIndex < $oppositionTimelineRank ? 'is-complete' : ($timelineIndex === $oppositionTimelineRank ? 'is-current' : '') }}">
                                    {{ $timelineLabel }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="admin-card" id="opposition-documents" data-doc-card>
                    <header class="admin-card-head">
                        <h2>Documents</h2>
                        @unless ($allLatestDocumentsReviewed)
                            <button class="admin-select-btn" type="button" data-doc-select-toggle>Select</button>
                        @endunless
                    </header>
                    <div class="admin-card-body">
                        <form method="POST" action="{{ route('admin.trademark-opposition.documents.review', $case) }}" data-doc-review-form>
                            @csrf
                            <input type="hidden" name="action" data-doc-review-action>
                            <input type="hidden" name="note" data-doc-review-note>
                            <table class="admin-table">
                                <thead><tr><th>Type</th><th>Status</th><th>File</th><th>Actions</th></tr></thead>
                                <tbody>
                                    @foreach ($allDocumentTypes as $type => $label)
                                        @php
                                            $document = $latestDocuments->get($type);
                                            $statusClass = match ($document?->review_status) {
                                            'reviewed' => '',
                                            'reuploaded' => 'is-info',
                                            'rejected' => 'is-danger',
                                            default => $document ? 'is-warning' : 'is-muted',
                                        };
                                        $statusLabel = match ($document?->review_status) {
                                            'reviewed' => 'Reviewed',
                                            'reuploaded' => 'Reuploaded',
                                            'rejected' => 'Reupload required',
                                            default => $document ? 'Pending review' : 'Pending',
                                        };
                                        @endphp
                                        <tr>
                                            <td class="admin-doc-type">
                                                @if ($document)
                                                    <input class="admin-doc-select" type="checkbox" name="document_ids[]" value="{{ $document->id }}" data-doc-checkbox>
                                                @endif
                                                {{ $label }}
                                            </td>
                                            <td>
                                                <span class="admin-status-pill {{ $statusClass }}">{{ $statusLabel }}</span>
                                            </td>
                                            <td>{{ $document?->file_name ?: 'No file uploaded' }}</td>
                                            <td>
                                                @if ($document)
                                                    <a class="admin-link" href="{{ route('admin.trademark-opposition.document.view', [$case, 'document', $document->id]) }}" target="_blank">View</a>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="admin-doc-bulk-actions" data-doc-bulk-actions>
                                <button class="admin-btn" type="button" data-doc-open-modal="reviewed">Mark as Reviewed</button>
                                <button class="admin-btn admin-btn-danger" type="button" data-doc-open-modal="rejected">Ask for Reupload</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="admin-card" data-evidence-card>
                    <header class="admin-card-head">
                        <h2>Evidence</h2>
                        @if ($selectableLatestEvidence->isNotEmpty())
                            <button class="admin-select-btn" type="button" data-evidence-select-toggle>Select</button>
                        @endif
                    </header>
                    <div class="admin-card-body">
                        @if ($latestEvidence->isNotEmpty())
                            <form method="POST" action="{{ route('admin.trademark-opposition.evidence.review', $case) }}" data-evidence-review-form>
                                @csrf
                                <input type="hidden" name="action" data-evidence-review-action>
                                <input type="hidden" name="note" data-evidence-review-note>
                                <table class="admin-table">
                                    <thead><tr><th>Type</th><th>Status</th><th>File</th><th>Actions</th></tr></thead>
                                    <tbody>
                                        @foreach ($latestEvidence as $evidence)
                                            @php
                                                $isRegistryStageEvidence = \Illuminate\Support\Str::startsWith($evidence->evidence_type, 'third_party_requested_evidence_');
                                                $isAdminSentEvidence = $evidence->uploaded_by === 'admin';
                                                $isPendingRegistryStageEvidence = $isRegistryStageEvidence && $evidence->review_status !== 'reviewed';
                                                $isSelectableEvidence = !$isAdminSentEvidence && !in_array($evidence->review_status, ['reviewed', 'rejected'], true);
                                                $evidenceDisplayType = $isRegistryStageEvidence
                                                    ? 'Admin Requested Evidence ' . \Illuminate\Support\Str::headline(\Illuminate\Support\Str::after($evidence->evidence_type, 'third_party_requested_evidence_'))
                                                    : ($evidenceTypes[$evidence->evidence_type] ?? \Illuminate\Support\Str::headline(str_replace('_', ' ', $evidence->evidence_type)));
                                                $statusClass = $isAdminSentEvidence ? 'is-muted' : match ($evidence->review_status) {
                                                    'reviewed' => '',
                                                    'reuploaded' => 'is-info',
                                                    'rejected' => 'is-danger',
                                                    default => 'is-warning',
                                                };
                                                $statusLabel = $isAdminSentEvidence ? 'Admin shared' : match ($evidence->review_status) {
                                                    'reviewed' => 'Reviewed',
                                                    'reuploaded' => 'Reuploaded',
                                                    'rejected' => 'Reupload required',
                                                    default => 'Pending review',
                                                };
                                            @endphp
                                            <tr @class(['is-review-target' => $isPendingRegistryStageEvidence]) @if ($isPendingRegistryStageEvidence) data-pending-registry-evidence @endif>
                                                <td class="admin-doc-type">
                                                    @if ($isSelectableEvidence)
                                                        <input class="admin-doc-select" type="checkbox" name="evidence_ids[]" value="{{ $evidence->id }}" data-evidence-checkbox>
                                                    @endif
                                                    {{ $evidenceDisplayType }}
                                                </td>
                                                <td><span class="admin-status-pill {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                                <td>{{ $evidence->file_name }}</td>
                                                <td><a class="admin-link" href="{{ route('admin.trademark-opposition.document.view', [$case, 'evidence', $evidence->id]) }}" target="_blank">View</a></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <div class="admin-doc-bulk-actions" data-evidence-bulk-actions>
                                    <button class="admin-btn" type="button" data-evidence-open-modal="reviewed">Mark as Reviewed</button>
                                    <button class="admin-btn admin-btn-danger" type="button" data-evidence-open-modal="rejected">Ask for Reupload</button>
                                </div>
                            </form>
                        @else
                            <p class="admin-empty">No evidence uploaded yet.</p>
                        @endif

                        @php
                            $evidenceMeta = $case->evidence->where('file_path', 'metadata')->last();
                            $evidenceMetaValues = $evidenceMeta ? json_decode($evidenceMeta->file_name, true) : [];
                            $evidenceMetaValues = is_array($evidenceMetaValues) ? array_filter($evidenceMetaValues, fn ($value) => filled($value)) : [];
                        @endphp
                        @if ($evidenceMeta)
                            <div class="admin-field mt-2">
                                <span>Evidence Intake Details</span>
                                @if ($evidenceMetaValues !== [])
                                    <div class="evidence-meta-grid">
                                        @foreach ($evidenceMetaValues as $label => $value)
                                            <div class="evidence-meta-item">
                                                <span>{{ $label }}</span>
                                                <strong>{{ $value }}</strong>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <strong>{{ $evidenceMeta->file_name }}</strong>
                                @endif
                            </div>
                        @endif
                    </div>
                </section>

                <section class="admin-card">
                    <header class="admin-card-head"><h2>Status History</h2></header>
                    <div class="admin-card-body">
                        <ul class="admin-timeline">
                            @foreach ($case->statusHistories as $history)
                                <li><strong>{{ $history->new_status }}</strong><small>{{ $formatDateTime($history->created_at) }} · {{ $history->changed_by }} · {{ $history->note }}</small></li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            </div>

            <aside class="stage-panel">
                <header class="stage-panel-head"><h2>Stage Actions</h2></header>
                <div class="stage-panel-body">
                    @if($linkedOppositionCase)
                        <div class="linked-opposition-card">
                            <span>Linked Opposition Application</span>
                            <strong>{{ $linkedOppositionCase->case_number }} · {{ $linkedOppositionCase->trademark_to_oppose ?: $linkedOppositionCase->trademark_name }}</strong>
                            <div class="linked-opposition-meta">
                                <span class="admin-status-pill is-info">{{ $linkedOppositionCase->current_admin_status }}</span>
                                @if($linkedOppositionCase->oppose_case_status)
                                    <span class="admin-status-pill">{{ \App\Support\TrademarkOppositionWorkflow::opposeOutcomeLabel($linkedOppositionCase->oppose_case_status) }}</span>
                                @endif
                            </div>
                            <a class="admin-link" href="{{ route('admin.trademark-opposition.oppose.show', $linkedOppositionCase) }}" target="_blank">
                                <i class="bi bi-box-arrow-up-right"></i> View Opposition Application
                            </a>
                        </div>
                    @else
                        <div class="linked-opposition-card">
                            <span>Linked Opposition Application</span>
                            <strong>No linked opponent case found</strong>
                        </div>
                    @endif

                    @if ($activeAction === 'none')
                        <div class="stage-note">There are no stage actions right now. The case is waiting for the client to upload the required deadline documents.</div>
                    @elseif ($activeAction === 'view-documents')
                        <div class="stage-note">The client has uploaded documents for Opposition Review. Open the Documents section and mark each file as reviewed or request reupload.</div>
                        <div class="admin-actions"><button class="admin-btn" type="button" data-scroll-documents>View Documents</button></div>
                    @elseif ($activeAction === 'request-evidence')
                        @if ($hasEvidenceRequest && !$editingEvidenceRequest)
                            <div class="stage-note">Evidence request has been sent to the client. Review the selected grounds and requested documents below.</div>
                            <div class="evidence-request-summary">
                                <div class="evidence-summary-head">
                                    <div>
                                        <h3>Selected Evidence Request</h3>
                                        <p>Edit this request if a ground or required client document was selected incorrectly.</p>
                                    </div>
                                    <a class="admin-btn admin-btn-secondary" href="{{ route('admin.trademark-opposition.show', $case) }}?edit_evidence_request=1">Edit</a>
                                </div>

                                <div class="evidence-summary-card">
                                    <h4>Selected Opposition Grounds</h4>
                                    <div class="evidence-chip-list">
                                        @forelse ($case->grounds as $ground)
                                            <span class="evidence-chip is-ground">{{ $ground->ground_name }}</span>
                                        @empty
                                            <span class="text-muted">No opposition grounds selected.</span>
                                        @endforelse
                                    </div>
                                </div>

                                <div class="evidence-summary-card">
                                    <h4>Selected Evidence Required From Client</h4>
                                    <div class="evidence-chip-list">
                                        @forelse ($selectedEvidenceTypes as $evidenceType)
                                            <span class="evidence-chip">{{ $evidenceType }}</span>
                                        @empty
                                            <span class="text-muted">No evidence documents selected.</span>
                                        @endforelse
                                    </div>
                                </div>

                                @if ($case->evidence_request_note)
                                    <div class="evidence-summary-card">
                                        <h4>Admin Note Sent To Client</h4>
                                        <p class="evidence-summary-note">{{ $case->evidence_request_note }}</p>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="stage-note">
                                {{ $hasEvidenceRequest ? 'Edit the selected opposition grounds or evidence uploads, then notify the client again.' : 'Documents are reviewed. Select the opposition grounds for client context, then choose the evidence uploads required from the client.' }}
                            </div>
                            <form method="POST" action="{{ route('admin.trademark-opposition.request-evidence', $case) }}" data-swal-confirm data-swal-title="Submit stage action?" data-swal-text="{{ $hasEvidenceRequest ? 'Update Request & Notify Client will update the evidence request and notify the client.' : 'Ask Selected Documents will request the selected evidence from the client.' }}" data-swal-confirm-text="Yes, submit" data-loading-text="Submitting...">
                                @csrf
                                @if ($errors->has('grounds'))
                                    <div class="alert alert-danger">{{ $errors->first('grounds') }}</div>
                                @endif
                                @if ($errors->has('evidence_types'))
                                    <div class="alert alert-danger">{{ $errors->first('evidence_types') }}</div>
                                @endif
                                <label class="admin-label">Opposition Grounds</label>
                                <div class="admin-checks">
                                    @foreach ($oppositionGroundDescriptions as $ground => $description)
                                        <label><input type="checkbox" name="grounds[]" value="{{ $ground }}" @checked(in_array($ground, old('grounds', $selectedGrounds), true))> <strong>{{ $ground }}</strong><br><small>{{ $description }}</small></label>
                                    @endforeach
                                </div>
                                <label class="admin-label mt-2">Admin Note</label>
                                <textarea class="admin-input" name="note" rows="4" placeholder="Write short legal/internal note based on the Notice of Opposition...">{{ old('note', $case->evidence_request_note) }}</textarea>
                                <label class="admin-label mt-2">Select Evidence Required From Client</label>
                                <div class="admin-checks">
                                    @foreach ($evidenceRequestTypes as $evidenceType)
                                        <label><input type="checkbox" name="evidence_types[]" value="{{ $evidenceType }}" @checked(in_array($evidenceType, old('evidence_types', $selectedEvidenceTypes), true))> {{ $evidenceType }}</label>
                                    @endforeach
                                </div>
                                <div class="admin-actions">
                                    <button class="admin-btn" type="submit">{{ $hasEvidenceRequest ? 'Update Request & Notify Client' : 'Ask Selected Documents' }}</button>
                                    @if ($hasEvidenceRequest)
                                        <a class="admin-btn admin-btn-light" href="{{ route('admin.trademark-opposition.show', $case) }}">Cancel</a>
                                    @endif
                                </div>
                            </form>
                        @endif
                    @elseif ($activeAction === 'review-evidence')
                        <div class="stage-note">Client submitted evidence. Review each evidence file and either mark it as reviewed or ask the client to reupload corrected files.</div>
                        <div class="admin-actions">
                            <button class="admin-btn" type="button" data-scroll-evidence>Review Evidence</button>
                            <a class="admin-btn admin-btn-secondary" href="{{ route('admin.trademark-opposition.show', $case) }}?edit_evidence_request=1">Edit Ask for Evidence Uploads</a>
                        </div>
                    @elseif ($activeAction === 'analysis')
                        <div class="stage-note">Review the uploaded opposition documents and select the legal grounds for the counter statement.</div>
                        <form method="POST" action="{{ route('admin.trademark-opposition.analysis', $case) }}" data-swal-confirm data-swal-title="Submit stage action?" data-swal-text="Save Legal Analysis will update the case stage." data-swal-confirm-text="Yes, submit" data-loading-text="Submitting...">
                            @csrf
                            <div class="admin-checks">
                                @foreach ($grounds as $ground)
                                    <label><input type="checkbox" name="grounds[]" value="{{ $ground }}" @checked($case->grounds->pluck('ground_name')->contains($ground))> {{ $ground }}</label>
                                @endforeach
                            </div>
                            <label class="admin-label mt-2">Internal Note</label>
                            <textarea class="admin-input" name="admin_note" rows="3" placeholder="Admin notes">{{ old('admin_note', $case->admin_internal_notes) }}</textarea>
                            <div class="admin-actions"><button class="admin-btn" type="submit">Save Legal Analysis</button></div>
                        </form>
                    @elseif ($activeAction === 'risk')
                        <div class="stage-note">Legal Review is ready. Select a risk assessment, add internal and client-visible notes, and optionally upload supporting review documents.</div>
                        <form method="POST" action="{{ route('admin.trademark-opposition.risk', $case) }}" enctype="multipart/form-data" data-swal-confirm data-swal-title="Submit risk assessment?" data-swal-text="Save Risk Assessment & Notify Client will update the case stage and notify the client." data-swal-confirm-text="Yes, notify client" data-loading-text="Submitting...">
                            @csrf
                            <label class="admin-label">Risk Assessment</label>
                            <div class="risk-option-grid">
                                @foreach ($riskOptions as $level => $riskOption)
                                    <label class="risk-option">
                                        <input type="radio" name="risk_level" value="{{ $level }}" required @checked(old('risk_level', $case->risk_level) === $level)>
                                        <div>
                                            <strong>{{ $level }}</strong>
                                            <span>{{ $riskOption['subheading'] }}</span>
                                            <p>{{ $riskOption['content'] }}</p>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                            <label class="admin-label mt-2">Internal Admin Note</label>
                            <textarea class="admin-input" name="internal_admin_note" rows="3" placeholder="Only admin/legal team can see this note.">{{ old('internal_admin_note', $case->admin_internal_notes) }}</textarea>
                            <label class="admin-label mt-2">Client Visible Note</label>
                            <textarea class="admin-input" name="client_visible_note" rows="4" placeholder="Client will see this note in the dashboard.">{{ old('client_visible_note', $case->risk_note) }}</textarea>
                            <label class="admin-label mt-2">Opposition Defence Package</label>
                            <input class="admin-input mb-2" name="package_name" placeholder="Opposition Defence Package" value="{{ old('package_name', $case->package_name ?: 'Opposition Defence Package') }}" required>
                            <input class="admin-input mb-2" type="number" min="1" step="0.01" name="package_price" placeholder="Opposition package price" value="{{ old('package_price', $case->package_price) }}" required>
                            <textarea class="admin-input mb-2" name="included_services" rows="4" placeholder="Describe what this opposition defence package will offer, for example: review of opposition grounds, counter statement drafting, filing support, evidence review, legal coordination, and case monitoring.">{{ old('included_services', implode("\n", $case->included_services ?? [])) }}</textarea>
                            <label class="admin-label mt-2">Optional Documents</label>
                            <input class="admin-input" type="file" name="optional_documents[]" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" multiple>
                            <div class="admin-actions"><button class="admin-btn" type="submit">Save Risk Assessment & Notify Client</button></div>
                        </form>
                    @elseif ($activeAction === 'await-payment')
                        <div class="stage-note">Waiting for the client to complete the opposition defence package payment. Draft attachment controls will appear after a verified Razorpay payment.</div>
                    @elseif ($activeAction === 'draft')
                        <div class="stage-note">Send the counter statement draft and any additional supporting documents to the client for approval.</div>
                        <form method="POST" action="{{ route('admin.trademark-opposition.draft', $case) }}" enctype="multipart/form-data" data-draft-notification-form data-has-current-draft="{{ $case->draft_path ? '1' : '0' }}" data-swal-confirm data-swal-title="Send draft to client?" data-swal-text="Upload Draft & Notify Client will send the counter statement draft and supporting documents to the client for approval." data-swal-icon="question" data-swal-confirm-text="Yes, notify client" data-loading-text="Submitting...">
                            @csrf
                            <label class="admin-label">Counter Statement Draft <span class="text-danger">{{ $case->draft_path ? '' : '*' }}</span></label>
                            <input class="admin-input" type="file" name="draft" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" data-counter-statement-draft>
                            <div class="alert alert-danger mt-2 d-none" data-draft-upload-error>Please choose the Counter Statement Draft before notifying the client.</div>
                            @error('draft')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            @if ($case->draft_path)<p class="admin-current-file"><a class="admin-link" href="{{ route('admin.trademark-opposition.file.view', [$case, 'draft']) }}" target="_blank">Current draft: {{ $case->draft_display_name }}</a> <span class="text-muted">(leave empty to send it again)</span></p>@endif
                            @include('admin.trademark-opposition.partials.additional-documents', [
                                'case' => $case,
                                'documents' => $case->evidence->where('evidence_type', 'counter_statement_additional_document'),
                                'addButtonAttribute' => 'data-add-draft-document',
                                'listAttribute' => 'data-draft-document-list',
                                'templateAttribute' => 'data-draft-document-template',
                                'rowAttribute' => 'data-draft-document-row',
                                'removeAttribute' => 'data-remove-draft-document',
                                'emptyAttribute' => 'data-draft-document-empty',
                            ])
                            @error('optional_documents')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            @error('optional_documents.*')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            @if ($case->client_approval_status)<p class="text-muted mb-0">Client approval: {{ str_replace('_', ' ', $case->client_approval_status) }}</p>@endif
                            @if ($case->client_change_request)<div class="alert alert-warning mt-2">{{ $case->client_change_request }}</div>@endif
                            <div class="admin-actions"><button class="admin-btn" type="submit">Upload Draft &amp; Notify Client</button></div>
                        </form>
                    @elseif ($activeAction === 'filing')
                        <div class="stage-note">Upload the filing acknowledgment after the counter statement is filed with the Registry.</div>
                        @if ($case->client_approval_note)<div class="alert alert-success"><strong>Client approval note:</strong> {{ $case->client_approval_note }}</div>@endif
                        <form method="POST" action="{{ route('admin.trademark-opposition.file', $case) }}" enctype="multipart/form-data" data-swal-confirm data-swal-title="Mark filed?" data-swal-text="This will mark the counter statement as filed and notify the client." data-swal-confirm-text="Yes, mark filed" data-loading-text="Submitting...">
                            @csrf
                            <label class="admin-label">Filing Acknowledgment</label>
                            <input class="admin-input" type="file" name="filing_acknowledgment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" @required(!$case->filing_acknowledgment_path)>
                            @if ($case->filing_acknowledgment_path)<p class="admin-current-file"><a class="admin-link" href="{{ route('admin.trademark-opposition.file.view', [$case, 'filing-acknowledgment']) }}" target="_blank">Current acknowledgment</a> <span class="text-muted">(leave empty to send it again)</span></p>@endif
                            @include('admin.trademark-opposition.partials.additional-documents', [
                                'case' => $case,
                                'documents' => $case->evidence->where('evidence_type', 'counter_statement_filing_additional_document'),
                                'addButtonAttribute' => 'data-add-filing-document',
                                'listAttribute' => 'data-filing-document-list',
                                'templateAttribute' => 'data-filing-document-template',
                                'rowAttribute' => 'data-filing-document-row',
                                'removeAttribute' => 'data-remove-filing-document',
                                'emptyAttribute' => 'data-filing-document-empty',
                            ])
                            @error('optional_documents')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            @error('optional_documents.*')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            <div class="admin-actions"><button class="admin-btn" type="submit">Mark Counter Statement Filed</button></div>
                        </form>
                    @elseif ($activeAction === 'tracking')
                        <div class="stage-summary-card">
                            <span>Current Stage</span>
                            <strong>{{ $currentStatus }}</strong>
                            <p>{{ $currentStageDescription }}</p>
                            <p><strong>Client Stage:</strong> {{ \App\Support\TrademarkOppositionWorkflow::clientStageForAdminStatus($currentStatus) }}</p>
                            <p><strong>Case Status:</strong> {{ $currentStatus === \App\Support\TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED ? 'Closed' : 'Active' }}</p>
                        </div>
                        @if ($nextDefendStage && $currentStatus !== \App\Support\TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED)
                            <div class="stage-summary-card is-next">
                                <span>Next Stage</span>
                                <strong>{{ $nextDefendStage }}</strong>
                                <p>{{ $nextStageDescription }}</p>
                            </div>
                        @endif
                        @if ($isDefendHearingStatus)
                            @if ($latestRegistryStageEvidence->isNotEmpty())
                                <div class="stage-summary-card {{ $registryStageEvidenceNeedsReview ? 'is-review-alert' : '' }}">
                                    <span>Client Evidence Uploads</span>
                                    <strong>{{ $registryStageEvidenceNeedsReview ? 'Client submitted documents. Review required.' : 'Reviewed' }}</strong>
                                    <p>
                                        {{ $latestRegistryStageEvidence->count() }} requested document type{{ $latestRegistryStageEvidence->count() === 1 ? '' : 's' }} uploaded by the client.
                                        @if ($registryStageEvidenceNeedsReview)
                                            These documents require admin review before the matter can move forward.
                                        @endif
                                    </p>
                                    <div class="admin-actions">
                                        <button class="admin-btn admin-btn-secondary" type="button" data-scroll-evidence>View Evidences</button>
                                    </div>
                                </div>
                            @endif

                            @if (!$showHearingForm)
                                <div class="stage-summary-card">
                                    <span>Hearing Form Submitted</span>
                                    <strong>{{ $currentStatus }}</strong>
                                    <p>{{ $currentStageDescription }}</p>
                                    @if ($case->hearing_date)
                                        <p><strong>Hearing Date:</strong> {{ $case->hearing_date->format('d M Y') }}</p>
                                    @endif
                                    @if ($case->final_outcome)
                                        <p><strong>Hearing Outcome:</strong> {{ $case->final_outcome }}</p>
                                    @endif
                                    @if ($hearingAdjournmentReason !== '')
                                        <p><strong>Reason for Adjournment:</strong> {{ $hearingAdjournmentReason }}</p>
                                    @endif
                                    @if ($hearingAdminNote !== '')
                                        <p><strong>Admin Note:</strong> {{ $hearingAdminNote }}</p>
                                    @endif
                                    @if ($registryDocumentRequests->isNotEmpty())
                                        <p><strong>Requested Documents:</strong> {{ $registryDocumentRequests->join(', ') }}</p>
                                    @endif
                                    @if ($case->third_party_evidence_message)
                                        <p><strong>Note to Client:</strong> {{ $case->third_party_evidence_message }}</p>
                                    @endif
                                    <div class="admin-actions">
                                        <a class="admin-btn admin-btn-secondary" href="{{ route('admin.trademark-opposition.show', $case) }}?edit_hearing_form=1">Edit Hearing Form</a>
                                    </div>
                                </div>
                            @else
                            <div class="stage-note">The matter is now in hearing stage. Add hearing details, upload hearing notice, request required documents, and notify the client.</div>
                            <form method="POST" action="{{ route('admin.trademark-opposition.tracking', $case) }}" enctype="multipart/form-data" data-hearing-stage-form data-swal-confirm data-swal-title="Update hearing stage?" data-swal-text="This will save hearing details and notify the client when applicable." data-swal-confirm-text="Yes, update" data-loading-text="Submitting...">
                                @csrf
                                <label class="admin-label">Hearing Status</label>
                                <select class="admin-input mb-2" name="status" required data-hearing-status>
                                    <option value="{{ $workflow::ADMIN_HEARING_PREPARATION }}" @selected(old('status', $currentStatus) === $workflow::ADMIN_HEARING_PREPARATION)>Hearing Awaited</option>
                                    <option value="{{ $workflow::ADMIN_HEARING_SCHEDULED }}" @selected(old('status', $currentStatus) === $workflow::ADMIN_HEARING_SCHEDULED)>Hearing Scheduled</option>
                                    <option value="{{ $workflow::ADMIN_HEARING_COMPLETED }}" @selected(old('status', $currentStatus) === $workflow::ADMIN_HEARING_COMPLETED)>Hearing Completed</option>
                                    <option value="{{ $workflow::ADMIN_HEARING_ADJOURNED }}" @selected(old('status', $currentStatus) === $workflow::ADMIN_HEARING_ADJOURNED)>Adjourned</option>
                                </select>
                                @error('status')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror

                                <div class="admin-hearing-field" data-hearing-date-field hidden>
                                    <label class="admin-label" data-hearing-date-label>Hearing Date</label>
                                    <input class="admin-input mb-2" type="date" name="hearing_date" value="{{ old('hearing_date', optional($case->hearing_date)->format('Y-m-d')) }}">
                                    @error('hearing_date')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                                </div>

                                <div class="admin-hearing-field" data-hearing-notice-field hidden>
                                    <label class="admin-label" data-hearing-notice-label>Upload Hearing Notice <span class="admin-visible">PDF, optional if issued by the Trademark Registry.</span></label>
                                    <input class="admin-input mb-2" type="file" name="hearing_notice" accept=".pdf">
                                    @error('hearing_notice')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                                </div>

                                <div class="admin-hearing-field" data-hearing-adjournment-field hidden>
                                    <label class="admin-label">Reason for Adjournment <span class="admin-visible">Optional</span></label>
                                    <textarea class="admin-input mb-2" name="adjournment_reason" rows="3" placeholder="Briefly mention why the hearing was adjourned.">{{ old('adjournment_reason', $hearingAdjournmentReason) }}</textarea>
                                    @error('adjournment_reason')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                                </div>

                                <div class="admin-hearing-field" data-hearing-notes-field hidden>
                                    <label class="admin-label">Hearing Notes <span class="admin-visible">Admin only</span></label>
                                    <textarea class="admin-input mb-2" name="note" rows="4" placeholder="Hearing arguments prepared. Adjourned to next hearing. Registry requested additional clarification.">{{ old('note', $hearingAdminNote) }}</textarea>
                                    @error('note')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                                </div>

                                <div class="admin-hearing-field" data-hearing-outcome-field hidden>
                                    <label class="admin-label">Hearing Outcome</label>
                                    <select class="admin-input mb-2" name="hearing_outcome" data-hearing-outcome>
                                        <option value="">Select hearing outcome</option>
                                        @foreach(['Further Hearing Required', 'Matter Concluded'] as $outcome)
                                            <option value="{{ $outcome }}" @selected(old('hearing_outcome', $case->final_outcome ?: $hearingOutcomeValue) === $outcome)>{{ $outcome }}</option>
                                        @endforeach
                                    </select>
                                    @error('hearing_outcome')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                                </div>

                                <label class="admin-label mt-2">Request Documents From Client</label>
                                <div class="requested-document-list" data-requested-document-list>
                                    @php $requestRows = old('evidence_items', $registryDocumentRequests->isNotEmpty() ? $registryDocumentRequests->all() : ['']); @endphp
                                    @foreach ($requestRows as $requestRow)
                                        <div class="requested-document-row" data-requested-document-row>
                                            <input class="admin-input" name="evidence_items[]" placeholder="e.g. Hearing authorization, additional affidavit" value="{{ $requestRow }}">
                                            <button class="requested-document-remove" type="button" data-remove-requested-document>Remove</button>
                                        </div>
                                    @endforeach
                                </div>
                                <button class="admin-btn admin-btn-secondary mt-2" type="button" data-add-requested-document>Add Requested Document</button>
                                <template data-requested-document-template>
                                    <div class="requested-document-row" data-requested-document-row>
                                        <input class="admin-input" name="evidence_items[]" placeholder="e.g. Hearing authorization, additional affidavit">
                                        <button class="requested-document-remove" type="button" data-remove-requested-document>Remove</button>
                                    </div>
                                </template>

                                <label class="admin-label mt-2">Note to Client</label>
                                <textarea class="admin-input mb-2" name="message_to_client" rows="3" placeholder="Explain hearing details or documents required from the client.">{{ old('message_to_client', $case->third_party_evidence_message) }}</textarea>
                                @error('evidence_items')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror

                                @include('admin.trademark-opposition.partials.additional-documents', [
                                    'case' => $case,
                                    'documents' => $case->evidence->whereIn('evidence_type', [
                                        'hearing_notice_document',
                                    ]),
                                    'addButtonAttribute' => 'data-add-tracking-document',
                                    'listAttribute' => 'data-tracking-document-list',
                                    'templateAttribute' => 'data-tracking-document-template',
                                    'rowAttribute' => 'data-tracking-document-row',
                                    'removeAttribute' => 'data-remove-tracking-document',
                                    'emptyAttribute' => 'data-tracking-document-empty',
                                    'showVisibility' => true,
                                    'label' => 'Additional Documents',
                                ])

                                <div class="admin-actions">
                                    <button class="admin-btn admin-btn-secondary" type="submit" data-hearing-submit>Save</button>
                                    @if ($editingHearingForm)
                                        <a class="admin-btn admin-btn-light" href="{{ route('admin.trademark-opposition.show', $case) }}">Cancel</a>
                                    @endif
                                </div>
                            </form>
                            @endif
                        @else
                        <form method="POST" action="{{ route('admin.trademark-opposition.tracking', $case) }}" enctype="multipart/form-data" data-swal-confirm data-swal-title="Update tracking?" data-swal-text="This will save the selected tracking stage." data-swal-confirm-text="Yes, update" data-loading-text="Submitting...">
                            @csrf
                            <input type="hidden" name="status" value="{{ $resendingRegistryRequest ? $currentStatus : ($currentStatus === \App\Support\TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED ? $currentStatus : $nextDefendStage) }}">
                            @if ($resendingRegistryRequest)
                                <input type="hidden" name="resend_registry_request" value="1">
                            @endif

                            @if ($currentStatus === \App\Support\TrademarkOppositionWorkflow::ADMIN_FINAL_OUTCOME || ($currentStatus === \App\Support\TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED && $editingFinalOutcome))
                                <label class="admin-label mt-2">Final Outcome</label>
                                <select class="admin-input mb-2" name="final_outcome">
                                    <option value="">Select final outcome</option>
                                    @foreach ($defenceFinalOutcomeOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('final_outcome', $case->final_outcome) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('final_outcome')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                                <label class="admin-label mt-2">Final Outcome Note to Client</label>
                                <textarea class="admin-input mb-2" name="final_client_message" rows="3" placeholder="Describe the final conclusion for the client action center.">{{ old('final_client_message', $case->final_client_message) }}</textarea>
                                @error('final_client_message')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror

                                <label class="admin-label mt-2">Final Order / Registry Decision</label>
                                <input class="admin-input mb-2" type="file" name="final_order" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                @error('final_order')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            @endif

                            @if (!in_array($currentStatus, [
                                \App\Support\TrademarkOppositionWorkflow::ADMIN_FINAL_OUTCOME,
                                \App\Support\TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED,
                            ], true))
                                @if ($latestRegistryStageEvidence->isNotEmpty())
                                    <div class="stage-summary-card {{ $registryStageEvidenceNeedsReview ? 'is-review-alert' : '' }}">
                                        <span>Client Evidence Uploads</span>
                                        <strong>{{ $registryStageEvidenceNeedsReview ? 'Client submitted documents. Review required.' : 'Reviewed' }}</strong>
                                        <p>
                                            {{ $latestRegistryStageEvidence->count() }} requested document type{{ $latestRegistryStageEvidence->count() === 1 ? '' : 's' }} uploaded by the client.
                                            @if ($registryStageEvidenceNeedsReview)
                                                These documents require admin review before the matter can move forward.
                                            @endif
                                        </p>
                                        <div class="admin-actions">
                                            <button class="admin-btn admin-btn-secondary" type="button" data-scroll-evidence>View Evidences</button>
                                        </div>
                                    </div>
                                @endif

                                @if ($registryDocumentRequests->isNotEmpty() && !$showRegistryRequestForm)
                                    <div class="stage-summary-card">
                                        <span>Requested Documents From Client</span>
                                        <strong>{{ $registryDocumentRequests->count() }} document{{ $registryDocumentRequests->count() === 1 ? '' : 's' }} requested</strong>
                                        <p>{{ $registryDocumentRequests->join(', ') }}</p>
                                        @if ($case->third_party_evidence_message)
                                            <p><strong>Note to Client:</strong> {{ $case->third_party_evidence_message }}</p>
                                        @endif
                                        @if ($registryRequestCanBeEdited)
                                            <div class="admin-actions">
                                                <a class="admin-btn admin-btn-secondary" href="{{ route('admin.trademark-opposition.show', $case) }}?edit_registry_request=1">Edit Evidence Stage Form</a>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <label class="admin-label mt-2">Request Documents From Client</label>
                                    <div class="requested-document-list" data-requested-document-list>
                                        @php $requestRows = old('evidence_items', $registryDocumentRequests->isNotEmpty() ? $registryDocumentRequests->all() : ['']); @endphp
                                        @foreach ($requestRows as $requestRow)
                                            <div class="requested-document-row" data-requested-document-row>
                                                <input class="admin-input" name="evidence_items[]" placeholder="e.g. Affidavit, exhibit, hearing authorization" value="{{ $requestRow }}">
                                                <button class="requested-document-remove" type="button" data-remove-requested-document>Remove</button>
                                            </div>
                                        @endforeach
                                    </div>
                                    <button class="admin-btn admin-btn-secondary mt-2" type="button" data-add-requested-document>Add Requested Document</button>
                                    <template data-requested-document-template>
                                        <div class="requested-document-row" data-requested-document-row>
                                            <input class="admin-input" name="evidence_items[]" placeholder="e.g. Affidavit, exhibit, hearing authorization">
                                            <button class="requested-document-remove" type="button" data-remove-requested-document>Remove</button>
                                        </div>
                                    </template>
                                    <label class="admin-label mt-2">Note to Client</label>
                                    <textarea class="admin-input mb-2" name="message_to_client" rows="3" placeholder="Explain what the client must upload and why.">{{ old('message_to_client', $case->third_party_evidence_message) }}</textarea>
                                    @error('evidence_items')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                                @endif
                            @endif

                            @if (in_array($currentStatus, [
                                \App\Support\TrademarkOppositionWorkflow::ADMIN_DECISION_AWAITED,
                                \App\Support\TrademarkOppositionWorkflow::ADMIN_FINAL_OUTCOME,
                            ], true) || ($currentStatus === \App\Support\TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED && $editingFinalOutcome))
                                @include('admin.trademark-opposition.partials.additional-documents', [
                                    'case' => $case,
                                    'documents' => $case->evidence->whereIn('evidence_type', [
                                        'final_order_document',
                                        'final_order_internal_document',
                                        'decision_additional_document',
                                    ]),
                                    'addButtonAttribute' => 'data-add-tracking-document',
                                    'listAttribute' => 'data-tracking-document-list',
                                    'templateAttribute' => 'data-tracking-document-template',
                                    'rowAttribute' => 'data-tracking-document-row',
                                    'removeAttribute' => 'data-remove-tracking-document',
                                    'emptyAttribute' => 'data-tracking-document-empty',
                                    'showVisibility' => true,
                                    'label' => 'Final / Additional Documents',
                                ])
                            @elseif ($currentStatus === \App\Support\TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED && !$editingFinalOutcome)
                                @include('admin.trademark-opposition.partials.additional-documents', [
                                    'case' => $case,
                                    'documents' => $case->evidence->whereIn('evidence_type', [
                                        'final_order_document',
                                        'final_order_internal_document',
                                        'decision_additional_document',
                                        'matter_closed_document',
                                    ]),
                                    'addButtonAttribute' => 'data-add-closed-document',
                                    'listAttribute' => 'data-closed-document-list',
                                    'templateAttribute' => 'data-closed-document-template',
                                    'rowAttribute' => 'data-closed-document-row',
                                    'removeAttribute' => 'data-remove-closed-document',
                                    'emptyAttribute' => 'data-closed-document-empty',
                                    'showVisibility' => true,
                                    'label' => 'Matter Closed / Additional Documents',
                                ])
                            @endif

                            <label class="admin-label mt-2">Internal Admin Note</label>
                            <textarea class="admin-input" name="note" rows="2" placeholder="Internal admin note" @disabled($currentStatus === \App\Support\TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED && !$editingFinalOutcome)>{{ old('note') }}</textarea>
                            @if ($currentStatus !== \App\Support\TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED || $editingFinalOutcome)
                                <div class="admin-actions">
                                    <button class="admin-btn" type="submit">
                                        @if ($editingFinalOutcome && $currentStatus === \App\Support\TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED)
                                            Update Decision & Close Matter
                                        @else
                                            {{ $resendingRegistryRequest ? 'Update Request & Notify Client' : $nextStageButtonLabel }}
                                        @endif
                                    </button>
                                    @if ($editingFinalOutcome && $currentStatus === \App\Support\TrademarkOppositionWorkflow::ADMIN_MATTER_CLOSED)
                                        <a class="admin-btn admin-btn-light" href="{{ route('admin.trademark-opposition.show', $case) }}">Cancel</a>
                                    @endif
                                </div>
                            @else
                                <div class="admin-actions">
                                    <button class="admin-btn" type="submit">Save Additional Documents</button>
                                    <a class="admin-btn admin-btn-secondary" href="{{ route('admin.trademark-opposition.show', $case) }}?edit_final_outcome=1">Edit Decision</a>
                                </div>
                            @endif
                        </form>
                        @endif
                    @else
                        <div class="stage-note">There are no stage actions right now for this status.</div>
                    @endif
                </div>
            </aside>
        </div>

        <div class="admin-review-modal" data-doc-review-modal>
            <div class="admin-review-dialog">
                <header><h3 data-doc-modal-title>Review documents</h3></header>
                <div class="admin-review-dialog-body">
                    <p class="text-muted" data-doc-modal-copy></p>
                    <label class="admin-label" data-doc-modal-label>Review Note</label>
                    <textarea class="admin-input" rows="4" data-doc-modal-note placeholder="Add note for the client"></textarea>
                    <div class="admin-review-dialog-actions">
                        <button class="admin-btn admin-btn-light" type="button" data-doc-modal-cancel>Cancel</button>
                        <button class="admin-btn" type="button" data-doc-modal-submit>Submit</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-review-modal" data-evidence-review-modal>
            <div class="admin-review-dialog">
                <header><h3 data-evidence-modal-title>Review evidence</h3></header>
                <div class="admin-review-dialog-body">
                    <p class="text-muted" data-evidence-modal-copy></p>
                    <label class="admin-label" data-evidence-modal-label>Review Note</label>
                    <textarea class="admin-input" rows="4" data-evidence-modal-note placeholder="Add note for the client"></textarea>
                    <div class="admin-review-dialog-actions">
                        <button class="admin-btn admin-btn-light" type="button" data-evidence-modal-cancel>Cancel</button>
                        <button class="admin-btn" type="button" data-evidence-modal-submit>Submit</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('admin.trademark-opposition.partials.additional-documents-script')

    <script>
        const showAdminFallbackAlert = ({ title, text, confirmButtonText }) => {
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
                            <button type="button" data-alert-confirm style="min-height:40px;border:0;border-radius:7px;background:#2a9d8f;color:#fff;padding:0 16px;font-weight:500;">${confirmButtonText}</button>
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

        const confirmAdminStageAction = async ({
            title = 'Confirm stage action',
            text = 'Are you sure you want to submit this stage action?',
            confirmButtonText = 'Yes, submit',
            icon = 'question',
        } = {}) => {
            if (!window.Swal) {
                return showAdminFallbackAlert({ title, text, confirmButtonText });
            }

            const result = await Swal.fire({
                title,
                text,
                icon,
                showCancelButton: true,
                confirmButtonText,
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#2a9d8f',
                cancelButtonColor: '#6b7280',
                reverseButtons: true,
                focusCancel: true,
            });

            return result.isConfirmed;
        };

        (() => {
            const form = document.querySelector('[data-draft-notification-form]');
            if (!form) return;

            const draftInput = form.querySelector('[data-counter-statement-draft]');
            const error = form.querySelector('[data-draft-upload-error]');
            const hasCurrentDraft = form.dataset.hasCurrentDraft === '1';

            draftInput?.addEventListener('change', () => {
                error?.classList.toggle('d-none', Boolean(draftInput.files?.length));
            });

            form.addEventListener('submit', (event) => {
                if (!hasCurrentDraft && !draftInput?.files?.length) {
                    event.preventDefault();
                    error?.classList.remove('d-none');
                    draftInput?.focus();
                    draftInput?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }

                const button = event.submitter || form.querySelector('[type="submit"]');
                window.LegalBruzButtonLoading?.set(button, 'Uploading & Notifying...');
            });
        })();

        (() => {
            const card = document.querySelector('[data-doc-card]');
            const form = document.querySelector('[data-doc-review-form]');
            if (!card || !form) return;

            const toggle = card.querySelector('[data-doc-select-toggle]');
            const checkboxes = Array.from(card.querySelectorAll('[data-doc-checkbox]'));
            const bulkActions = card.querySelector('[data-doc-bulk-actions]');
            const modal = document.querySelector('[data-doc-review-modal]');
            const modalTitle = modal?.querySelector('[data-doc-modal-title]');
            const modalCopy = modal?.querySelector('[data-doc-modal-copy]');
            const modalLabel = modal?.querySelector('[data-doc-modal-label]');
            const modalNote = modal?.querySelector('[data-doc-modal-note]');
            const modalSubmit = modal?.querySelector('[data-doc-modal-submit]');
            const modalCancel = modal?.querySelector('[data-doc-modal-cancel]');
            const actionInput = form.querySelector('[data-doc-review-action]');
            const noteInput = form.querySelector('[data-doc-review-note]');
            let pendingAction = null;

            const selectedCount = () => checkboxes.filter((checkbox) => checkbox.checked).length;
            const refreshBulkActions = () => {
                bulkActions?.classList.toggle('is-visible', selectedCount() > 0);
            };

            toggle?.addEventListener('click', () => {
                const isSelecting = card.classList.toggle('is-selecting');
                toggle.textContent = isSelecting ? 'Cancel' : 'Select';
                if (!isSelecting) {
                    checkboxes.forEach((checkbox) => { checkbox.checked = false; });
                    refreshBulkActions();
                }
            });

            checkboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', refreshBulkActions);
            });

            card.querySelectorAll('[data-doc-open-modal]').forEach((button) => {
                button.addEventListener('click', () => {
                    if (selectedCount() === 0) return;
                    pendingAction = button.dataset.docOpenModal;
                    const isRejected = pendingAction === 'rejected';
                    modalTitle.textContent = isRejected ? 'Ask for Reupload' : 'Mark Documents as Reviewed';
                    modalCopy.textContent = isRejected
                        ? 'Add the issues the client must fix before reuploading the selected document(s).'
                        : 'Add an optional review note. The client will receive an email with the reviewed document name(s).';
                    modalLabel.textContent = isRejected ? 'Reupload Note' : 'Review Note';
                    modalNote.placeholder = isRejected ? 'List the document issues that need to be fixed' : 'Optional note for the client';
                    modalNote.value = '';
                    modal?.classList.add('is-visible');
                    modalNote.focus();
                });
            });

            modalCancel?.addEventListener('click', () => {
                modal?.classList.remove('is-visible');
                pendingAction = null;
            });

            modal?.addEventListener('click', (event) => {
                if (event.target === modal) {
                    modal.classList.remove('is-visible');
                    pendingAction = null;
                }
            });

            modalSubmit?.addEventListener('click', async () => {
                const note = modalNote.value.trim();
                if (pendingAction === 'rejected' && note === '') {
                    modalNote.focus();
                    return;
                }

                actionInput.value = pendingAction;
                noteInput.value = note;
                const actionLabel = pendingAction === 'rejected' ? 'ask the client to reupload the selected document(s)' : 'mark the selected document(s) as reviewed';
                const confirmed = await confirmAdminStageAction({
                    title: pendingAction === 'rejected' ? 'Ask for reupload?' : 'Mark documents reviewed?',
                    text: `This will ${actionLabel}.`,
                    confirmButtonText: pendingAction === 'rejected' ? 'Yes, ask reupload' : 'Yes, mark reviewed',
                    icon: pendingAction === 'rejected' ? 'warning' : 'question',
                });
                if (!confirmed) {
                    return;
                }
                window.LegalBruzButtonLoading?.set(modalSubmit, 'Submitting...');
                HTMLFormElement.prototype.submit.call(form);
            });

            document.querySelector('[data-scroll-documents]')?.addEventListener('click', () => {
                document.querySelector('#opposition-documents')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        })();

        (() => {
            const card = document.querySelector('[data-evidence-card]');
            const form = document.querySelector('[data-evidence-review-form]');
            if (!card || !form) return;

            const toggle = card.querySelector('[data-evidence-select-toggle]');
            const checkboxes = Array.from(card.querySelectorAll('[data-evidence-checkbox]'));
            const bulkActions = card.querySelector('[data-evidence-bulk-actions]');
            const modal = document.querySelector('[data-evidence-review-modal]');
            const modalTitle = modal?.querySelector('[data-evidence-modal-title]');
            const modalCopy = modal?.querySelector('[data-evidence-modal-copy]');
            const modalLabel = modal?.querySelector('[data-evidence-modal-label]');
            const modalNote = modal?.querySelector('[data-evidence-modal-note]');
            const modalSubmit = modal?.querySelector('[data-evidence-modal-submit]');
            const modalCancel = modal?.querySelector('[data-evidence-modal-cancel]');
            const actionInput = form.querySelector('[data-evidence-review-action]');
            const noteInput = form.querySelector('[data-evidence-review-note]');
            let pendingAction = null;

            const selectedCount = () => checkboxes.filter((checkbox) => checkbox.checked).length;
            const refreshBulkActions = () => {
                bulkActions?.classList.toggle('is-visible', selectedCount() > 0);
            };

            toggle?.addEventListener('click', () => {
                const isSelecting = card.classList.toggle('is-selecting');
                toggle.textContent = isSelecting ? 'Cancel' : 'Select';
                if (!isSelecting) {
                    checkboxes.forEach((checkbox) => { checkbox.checked = false; });
                    refreshBulkActions();
                }
            });

            checkboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', refreshBulkActions);
            });

            card.querySelectorAll('[data-evidence-open-modal]').forEach((button) => {
                button.addEventListener('click', () => {
                    if (selectedCount() === 0) return;
                    pendingAction = button.dataset.evidenceOpenModal;
                    const isRejected = pendingAction === 'rejected';
                    modalTitle.textContent = isRejected ? 'Ask for Evidence Reupload' : 'Mark Evidence as Reviewed';
                    modalCopy.textContent = isRejected
                        ? 'Add the issues the client must fix before reuploading the selected evidence file(s).'
                        : 'Add an optional review note for the selected evidence file(s).';
                    modalLabel.textContent = isRejected ? 'Reupload Note' : 'Review Note';
                    modalNote.placeholder = isRejected ? 'List the evidence issues that need to be fixed' : 'Optional note';
                    modalNote.value = '';
                    modal?.classList.add('is-visible');
                    modalNote.focus();
                });
            });

            modalCancel?.addEventListener('click', () => {
                modal?.classList.remove('is-visible');
                pendingAction = null;
            });

            modal?.addEventListener('click', (event) => {
                if (event.target === modal) {
                    modal.classList.remove('is-visible');
                    pendingAction = null;
                }
            });

            modalSubmit?.addEventListener('click', async () => {
                const note = modalNote.value.trim();
                if (pendingAction === 'rejected' && note === '') {
                    modalNote.focus();
                    return;
                }

                actionInput.value = pendingAction;
                noteInput.value = note;
                const actionLabel = pendingAction === 'rejected' ? 'ask the client to reupload the selected evidence file(s)' : 'mark the selected evidence file(s) as reviewed';
                const confirmed = await confirmAdminStageAction({
                    title: pendingAction === 'rejected' ? 'Ask for evidence reupload?' : 'Mark evidence reviewed?',
                    text: `This will ${actionLabel}.`,
                    confirmButtonText: pendingAction === 'rejected' ? 'Yes, ask reupload' : 'Yes, mark reviewed',
                    icon: pendingAction === 'rejected' ? 'warning' : 'question',
                });
                if (!confirmed) {
                    return;
                }
                window.LegalBruzButtonLoading?.set(modalSubmit, 'Submitting...');
                HTMLFormElement.prototype.submit.call(form);
            });

            document.querySelector('[data-scroll-evidence]')?.addEventListener('click', () => {
                const target = card.querySelector('[data-pending-registry-evidence]') || card;
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (target !== card) {
                    target.classList.remove('is-scroll-highlight');
                    window.setTimeout(() => target.classList.add('is-scroll-highlight'), 120);
                }
            });
        })();

        (() => {
            const form = document.querySelector('[data-hearing-stage-form]');
            if (!form) return;

            const status = form.querySelector('[data-hearing-status]');
            const dateField = form.querySelector('[data-hearing-date-field]');
            const dateLabel = form.querySelector('[data-hearing-date-label]');
            const dateInput = dateField?.querySelector('input[name="hearing_date"]');
            const noticeField = form.querySelector('[data-hearing-notice-field]');
            const noticeLabel = form.querySelector('[data-hearing-notice-label]');
            const adjournmentField = form.querySelector('[data-hearing-adjournment-field]');
            const notesField = form.querySelector('[data-hearing-notes-field]');
            const outcomeField = form.querySelector('[data-hearing-outcome-field]');
            const outcomeInput = form.querySelector('[data-hearing-outcome]');
            const submit = form.querySelector('[data-hearing-submit]');
            const scheduledStatus = @json($workflow::ADMIN_HEARING_SCHEDULED);
            const completedStatus = @json($workflow::ADMIN_HEARING_COMPLETED);
            const adjournedStatus = @json($workflow::ADMIN_HEARING_ADJOURNED);

            const refreshHearingFields = () => {
                const isScheduled = status?.value === scheduledStatus;
                const isCompleted = status?.value === completedStatus;
                const isAdjourned = status?.value === adjournedStatus;
                const furtherHearingRequired = isCompleted && outcomeInput?.value === 'Further Hearing Required';
                const showDateAndNotice = isScheduled || isAdjourned || furtherHearingRequired;
                const showNotes = isScheduled || isAdjourned;

                if (dateField && dateInput) {
                    dateField.hidden = !showDateAndNotice;
                    dateInput.required = showDateAndNotice;
                    if (dateLabel) {
                        dateLabel.textContent = isAdjourned || furtherHearingRequired ? 'New Hearing Date' : 'Hearing Date';
                    }
                }
                if (noticeField) {
                    noticeField.hidden = !showDateAndNotice;
                    if (noticeLabel) {
                        noticeLabel.innerHTML = isAdjourned || furtherHearingRequired
                            ? 'Upload New Hearing Notice <span class="admin-visible">PDF, optional if issued by the Trademark Registry.</span>'
                            : 'Upload Hearing Notice <span class="admin-visible">PDF, optional if issued by the Trademark Registry.</span>';
                    }
                }
                if (adjournmentField) {
                    adjournmentField.hidden = !isAdjourned;
                }
                if (notesField) {
                    notesField.hidden = !showNotes;
                }
                if (outcomeField && outcomeInput) {
                    outcomeField.hidden = !isCompleted;
                    outcomeInput.required = isCompleted;
                }
                if (submit) {
                    submit.textContent = isCompleted && !furtherHearingRequired ? 'Save & Move to Decision Awaited' : 'Save';
                }
            };

            status?.addEventListener('change', refreshHearingFields);
            outcomeInput?.addEventListener('change', refreshHearingFields);
            refreshHearingFields();
        })();
    </script>
@endsection
