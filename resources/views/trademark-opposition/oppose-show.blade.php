@extends('layouts.app')

@section('content')
    @php
        $workflow = \App\Support\TrademarkOppositionWorkflow::class;
        $displayTimezone = config('app.timezone', 'Europe/London');
        $formatDateTime = fn ($timestamp, string $format = 'd M Y, h:i A T') => $timestamp
            ? \Illuminate\Support\Carbon::parse($timestamp)->timezone($displayTimezone)->format($format)
            : null;
        $uploadedEvidence = $case->evidence->where('file_path', '!=', 'metadata')->where('uploaded_by', 'client')->sortByDesc('id')->unique('evidence_type')->reject(fn ($evidence) => $evidence->review_status === 'rejected')->pluck('evidence_type')->all();
        $requiredEvidenceComplete = collect($requiredEvidenceGroups)->every(fn ($types) => count(array_intersect($types, $uploadedEvidence)) > 0);
        $requiredEvidenceTypes = collect($requiredEvidenceGroups)->flatten()->unique()->all();
        $missingRequiredEvidence = collect($requiredEvidenceGroups)
            ->filter(fn ($types) => count(array_intersect($types, $uploadedEvidence)) === 0)
            ->map(fn ($types) => collect($types)->map(fn ($type) => $evidenceTypes[$type] ?? $type)->join(' or '))
            ->values();
        $latestEvidence = $case->evidence->where('file_path', '!=', 'metadata')->where('uploaded_by', 'client')->sortByDesc('id')->unique('evidence_type');
        $latestEvidenceByType = $latestEvidence->keyBy('evidence_type');
        $hasRejectedEvidence = $latestEvidence->contains(fn ($evidence) => $evidence->review_status === 'rejected');
        $rejectedEvidenceNotes = $latestEvidence
            ->filter(fn ($evidence) => $evidence->review_status === 'rejected' && filled($evidence->review_note))
            ->pluck('review_note')
            ->unique()
            ->values();
        $latestThirdPartyEvidenceDocuments = $case->evidence
            ->where('file_path', '!=', 'metadata')
            ->where('uploaded_by', 'client')
            ->filter(fn ($document) => \Illuminate\Support\Str::startsWith($document->evidence_type, 'third_party_requested_evidence_'))
            ->sortByDesc('id')
            ->unique('evidence_type')
            ->values();
        $rejectedThirdPartyEvidenceDocuments = $latestThirdPartyEvidenceDocuments
            ->filter(fn ($document) => $document->review_status === 'rejected')
            ->values();
        $thirdPartyEvidenceRequestLabels = collect($case->third_party_evidence_requests ?? [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values();
        if ($rejectedThirdPartyEvidenceDocuments->isNotEmpty()) {
            $thirdPartyEvidenceRequestLabels = $rejectedThirdPartyEvidenceDocuments
                ->map(fn ($document) => \Illuminate\Support\Str::headline(\Illuminate\Support\Str::after($document->evidence_type, 'third_party_requested_evidence_')))
                ->values();
        }
        $hasThirdPartyEvidenceReupload = $rejectedThirdPartyEvidenceDocuments->isNotEmpty();
        $hasAdminEvidenceRequest = $case->statusHistories->contains(
            fn ($history) => $history->new_status === $workflow::ADMIN_EVIDENCE_COLLECTION
                && $history->changed_by === 'admin'
        );
        $effectiveAdminStatus = $case->current_admin_status;
        if ($case->current_admin_status === $workflow::ADMIN_NOTICE_FILED && filled($case->filing_acknowledgment_path)) {
            $effectiveAdminStatus = $workflow::ADMIN_COUNTER_STATEMENT_AWAITED;
        }
        $hasEvidenceStageDocuments = $case->evidence
            ->where('file_path', '!=', 'metadata')
            ->contains(fn ($document) => \Illuminate\Support\Str::startsWith($document->evidence_type, 'third_party_requested_evidence_'));
        $hasEnteredEvidenceStage = $case->statusHistories->contains(fn ($history) => in_array($history->new_status, [
            $workflow::ADMIN_EVIDENCE_BY_OPPONENT,
            $workflow::ADMIN_EVIDENCE_BY_APPLICANT,
            $workflow::ADMIN_EVIDENCE_IN_REPLY,
        ], true));
        $hasReachedLaterEvidenceStage = in_array($case->current_admin_status, [
            $workflow::ADMIN_EVIDENCE_FILED,
            $workflow::ADMIN_HEARING_PREPARATION,
            $workflow::ADMIN_HEARING_SCHEDULED,
            $workflow::ADMIN_HEARING_COMPLETED,
            $workflow::ADMIN_HEARING_ADJOURNED,
            $workflow::ADMIN_DECISION_AWAITED,
            $workflow::ADMIN_MATTER_CLOSED,
            $workflow::ADMIN_OPPOSITION_ALLOWED,
            $workflow::ADMIN_OPPOSITION_DISMISSED,
            $workflow::ADMIN_SETTLEMENT_CLOSED,
        ], true);
        if (($case->third_party_evidence_pending || $hasEvidenceStageDocuments) && $hasEnteredEvidenceStage && !$hasReachedLaterEvidenceStage) {
            $effectiveAdminStatus = $workflow::ADMIN_EVIDENCE_BY_OPPONENT;
        }
        $isCaseUnderReview = $case->current_admin_status === $workflow::ADMIN_APPLICATION_RECEIVED
            && !$hasAdminEvidenceRequest;
        $displayClientStage = $isCaseUnderReview
            ? $workflow::CLIENT_UNDER_REVIEW
            : $workflow::clientStageForAdminStatus($effectiveAdminStatus);
        if ($effectiveAdminStatus === $workflow::ADMIN_READY_FOR_FILING) {
            $displayClientStage = 'Ready for Filing';
        }
        $displayActionStatus = $displayClientStage;
        $statusOrder = [
            ['Case Opened', 'Your trademark opposition matter has been initiated.', 'bi-folder-check'],
            ['Evidence Collection', 'Ownership, use, market, and confusion evidence is collected.', 'bi-cloud-upload'],
            ['Legal Review Underway', 'Our legal team evaluates the conflicting marks and legal rights.', 'bi-search'],
            ['Pricing & Payment', 'Review the recommendation, package, pricing, and payment.', 'bi-credit-card'],
            ['Drafting in Progress', 'The Notice of Opposition is prepared and reviewed.', 'bi-file-earmark-text'],
            ['Document Filed', 'The Notice of Opposition is filed with the Registry.', 'bi-send-check'],
            ['Awaiting Third Party Action', 'Waiting for the applicant to file the Counter Statement.', 'bi-hourglass-split'],
            ['Evidence Stage', 'Registry evidence rounds are underway.', 'bi-files'],
            ['Hearing Stage', 'The matter is listed or prepared for hearing.', 'bi-people'],
            ['Decision Awaited', 'Hearing is complete and the Registry decision is awaited.', 'bi-clock-history'],
            ['Matter Closed', 'The Registry decision or settlement has concluded the matter.', 'bi-check2-circle'],
        ];
        $stepStatusMap = [
            [$workflow::ADMIN_APPLICATION_RECEIVED],
            [$workflow::ADMIN_EVIDENCE_COLLECTION, $workflow::ADMIN_DOCUMENTS_PENDING],
            [$workflow::ADMIN_LEGAL_ANALYSIS],
            [$workflow::ADMIN_LEGAL_ANALYSIS_COMPLETED],
            ['Payment Completed', $workflow::ADMIN_NOTICE_DRAFTING, $workflow::ADMIN_DRAFT_UNDER_LEGAL_REVIEW, $workflow::ADMIN_CLIENT_APPROVAL_PENDING],
            [$workflow::ADMIN_READY_FOR_FILING, $workflow::ADMIN_NOTICE_FILED],
            [$workflow::ADMIN_COUNTER_STATEMENT_AWAITED],
            [$workflow::ADMIN_EVIDENCE_BY_OPPONENT, $workflow::ADMIN_EVIDENCE_BY_APPLICANT, $workflow::ADMIN_EVIDENCE_IN_REPLY, $workflow::ADMIN_EVIDENCE_FILED],
            [$workflow::ADMIN_HEARING_PREPARATION, $workflow::ADMIN_HEARING_SCHEDULED, $workflow::ADMIN_HEARING_ADJOURNED, $workflow::ADMIN_HEARING_COMPLETED],
            [$workflow::ADMIN_DECISION_AWAITED],
            [$workflow::ADMIN_MATTER_CLOSED, $workflow::ADMIN_OPPOSITION_ALLOWED, $workflow::ADMIN_OPPOSITION_DISMISSED, $workflow::ADMIN_SETTLEMENT_CLOSED],
        ];
        $statusChangedAt = static function (int $index) use ($case, $stepStatusMap) {
            $statuses = $stepStatusMap[$index] ?? [];
            $history = $case->statusHistories->first(fn ($item) => in_array($item->new_status, $statuses, true));

            if ($history) {
                return $history->created_at;
            }

            return $index === 0 ? $case->created_at : null;
        };
        $stageRank = match ($effectiveAdminStatus) {
            $workflow::ADMIN_APPLICATION_RECEIVED => 1,
            $workflow::ADMIN_EVIDENCE_COLLECTION, $workflow::ADMIN_DOCUMENTS_PENDING => 1,
            $workflow::ADMIN_LEGAL_ANALYSIS => 2,
            $workflow::ADMIN_LEGAL_ANALYSIS_COMPLETED => 3,
            'Payment Completed', $workflow::ADMIN_NOTICE_DRAFTING, $workflow::ADMIN_DRAFT_UNDER_LEGAL_REVIEW,
            $workflow::ADMIN_CLIENT_APPROVAL_PENDING => 4,
            $workflow::ADMIN_READY_FOR_FILING,
            $workflow::ADMIN_NOTICE_FILED => 5,
            $workflow::ADMIN_COUNTER_STATEMENT_AWAITED => 6,
            $workflow::ADMIN_EVIDENCE_BY_OPPONENT, $workflow::ADMIN_EVIDENCE_BY_APPLICANT,
            $workflow::ADMIN_EVIDENCE_IN_REPLY,
            $workflow::ADMIN_EVIDENCE_FILED => 7,
            $workflow::ADMIN_HEARING_PREPARATION,
            $workflow::ADMIN_HEARING_SCHEDULED,
            $workflow::ADMIN_HEARING_ADJOURNED,
            $workflow::ADMIN_HEARING_COMPLETED => 8,
            $workflow::ADMIN_DECISION_AWAITED => 9,
            $workflow::ADMIN_MATTER_CLOSED, $workflow::ADMIN_OPPOSITION_ALLOWED,
            $workflow::ADMIN_OPPOSITION_DISMISSED, $workflow::ADMIN_SETTLEMENT_CLOSED => 10,
            default => 0,
        };
        if ($isCaseUnderReview) {
            $stageRank = 0;
        }
        $recommendationText = $case->recommendation_level ? ($recommendations[$case->recommendation_level] ?? null) : null;
        $visibleLegalReviewPoints = $case->legalReviewPoints
            ->where('is_client_visible', true)
            ->pluck('review_point')
            ->map(fn ($point) => trim((string) $point))
            ->filter()
            ->unique()
            ->values();
        $draftClientNote = trim((string) ($case->draft_client_note ?? ''));
        if ($draftClientNote === '') {
            $draftReadyHistory = $case->statusHistories->first(fn ($history) => $history->new_status === $workflow::ADMIN_CLIENT_APPROVAL_PENDING
                && $history->changed_by === 'admin'
                && \Illuminate\Support\Str::contains((string) $history->note, ' Client note: '));
            $draftClientNote = $draftReadyHistory
                ? trim(\Illuminate\Support\Str::after((string) $draftReadyHistory->note, ' Client note: '))
                : '';
        }
        $hasVerifiedOpposePayment = $case->payment_status === 'paid' && filled($case->payment_reference) && filled($case->transaction_id) && filled($case->paid_at);
        $hasOpposePricing = (float) ($case->total_amount ?: $case->package_price) > 0;
        $oppositionFilingOriginalAmount = (float) ($case->total_amount ?: $case->package_price ?: 0);
        $oppositionFilingPaymentCoupons = auth()->check()
            ? \App\Models\DiscountCoupon::availableForPayment('opposition_filing', auth()->id())
            : collect();
        $oppositionFilingAutoCoupon = auth()->check()
            ? \App\Models\DiscountCoupon::autoApplyForPayment('opposition_filing', auth()->id())
            : null;
        $oppositionFilingPayableAmount = $oppositionFilingAutoCoupon
            ? $oppositionFilingAutoCoupon->discountedAmountFor($oppositionFilingOriginalAmount)
            : $oppositionFilingOriginalAmount;
        $actionType = 'waiting';
        if ($isCaseUnderReview) {
            $actionType = 'initial-review';
        } elseif (($case->third_party_evidence_pending || $hasThirdPartyEvidenceReupload) && !in_array($effectiveAdminStatus, [
            $workflow::ADMIN_MATTER_CLOSED,
            $workflow::ADMIN_OPPOSITION_ALLOWED,
            $workflow::ADMIN_OPPOSITION_DISMISSED,
            $workflow::ADMIN_SETTLEMENT_CLOSED,
        ], true)) {
            $actionType = 'third-party-evidence';
        } elseif (in_array($effectiveAdminStatus, [$workflow::ADMIN_EVIDENCE_COLLECTION, $workflow::ADMIN_DOCUMENTS_PENDING], true)) {
            $actionType = $hasAdminEvidenceRequest && (!$requiredEvidenceComplete || $hasRejectedEvidence)
                ? 'evidence'
                : 'evidence-review';
        } elseif ($effectiveAdminStatus === $workflow::ADMIN_LEGAL_ANALYSIS) {
            $actionType = 'review';
        } elseif ($effectiveAdminStatus === $workflow::ADMIN_LEGAL_ANALYSIS_COMPLETED) {
            $actionType = $hasOpposePricing ? 'payment' : 'pricing-preparation';
        } elseif (in_array($effectiveAdminStatus, [
            'Payment Completed',
            $workflow::ADMIN_NOTICE_DRAFTING,
            $workflow::ADMIN_DRAFT_UNDER_LEGAL_REVIEW,
            $workflow::ADMIN_CLIENT_APPROVAL_PENDING,
        ], true)) {
            if (!$case->draft_path || $case->client_approval_status === 'changes_requested') {
                $actionType = 'waiting';
            } elseif ($case->client_approval_status !== 'approved') {
                $actionType = 'draft';
            }
        } elseif ($effectiveAdminStatus === $workflow::ADMIN_READY_FOR_FILING) {
            $actionType = 'waiting';
        } elseif ($effectiveAdminStatus === $workflow::ADMIN_NOTICE_FILED && $case->filing_acknowledgment_path) {
            $actionType = 'filed';
        }
        $closedStatuses = [
            $workflow::ADMIN_MATTER_CLOSED,
            $workflow::ADMIN_OPPOSITION_ALLOWED,
            $workflow::ADMIN_OPPOSITION_DISMISSED,
            $workflow::ADMIN_SETTLEMENT_CLOSED,
        ];
        $isMatterClosed = in_array($effectiveAdminStatus, $closedStatuses, true);
        $waitingTitle = $isMatterClosed ? 'Matter Closed' : 'No action is required right now.';
        $waitingMessage = $case->client_approval_status === 'changes_requested'
            ? 'Your change request is with the drafting team. A revised draft will appear here and you will be notified through email.'
            : 'Your matter is progressing. You will be notified through email when your next action is ready.';
        if (in_array($effectiveAdminStatus, [
            $workflow::ADMIN_EVIDENCE_BY_OPPONENT,
            $workflow::ADMIN_EVIDENCE_BY_APPLICANT,
            $workflow::ADMIN_EVIDENCE_IN_REPLY,
            $workflow::ADMIN_EVIDENCE_FILED,
        ], true) && !$case->third_party_evidence_pending && !$hasThirdPartyEvidenceReupload) {
            $waitingMessage = "Our legal team is preparing your evidence for filing before the Trademark Registry.\nYou will be notified if any further documents are required.";
        }
        if (in_array($effectiveAdminStatus, [
            $workflow::ADMIN_HEARING_PREPARATION,
        ], true)) {
            $waitingMessage = "We are awaiting the hearing schedule from the Trademark Registry.";
        }
        if ($effectiveAdminStatus === $workflow::ADMIN_HEARING_SCHEDULED) {
            if ($case->final_outcome === 'Further Hearing Required') {
                $waitingMessage = filled($case->hearing_date)
                    ? "Your hearing has been rescheduled.\n\nNew Hearing Date:\n" . $case->hearing_date->format('d F Y')
                    : 'Your hearing has been rescheduled. The new hearing date will be updated shortly.';
            } else {
                $waitingMessage = filled($case->hearing_date)
                    ? "Your hearing has been scheduled.\n\nDate:\n" . $case->hearing_date->format('d M Y')
                    : 'Your hearing has been scheduled. The hearing date will be updated shortly.';
            }
        }
        if ($effectiveAdminStatus === $workflow::ADMIN_HEARING_ADJOURNED) {
            $waitingMessage = filled($case->hearing_date)
                ? "Your hearing has been rescheduled.\n\nNew Hearing Date:\n" . $case->hearing_date->format('d F Y')
                : 'Your hearing has been rescheduled. The new hearing date will be updated shortly.';
        }
        if ($effectiveAdminStatus === $workflow::ADMIN_DECISION_AWAITED) {
            $waitingMessage = 'The hearing has concluded. We are awaiting the final decision from the Trademark Registry. You will be notified once the decision is issued.';
        }
        if ($isMatterClosed) {
            $waitingMessage = match ($case->final_outcome ?: $effectiveAdminStatus) {
                $workflow::ADMIN_OPPOSITION_ALLOWED => 'The Trademark Registry has allowed your opposition. Please view the final order from the stage Documents section.',
                $workflow::ADMIN_OPPOSITION_DISMISSED => 'The Trademark Registry has dismissed your opposition. Please download the final order from the Documents section.',
                $workflow::ADMIN_SETTLEMENT_CLOSED => 'This matter has been closed based on a settlement between the parties.',
                $workflow::FINAL_OUTCOME_WITHDRAWN_BY_OPPONENT => 'The opposition was withdrawn by the opponent. The trademark may continue.',
                $workflow::FINAL_OUTCOME_WITHDRAWN_BY_APPLICANT => 'The trademark applicant withdrew the application. Please view the final documents from the stage Documents section.',
                $workflow::FINAL_OUTCOME_OTHER => 'The matter has been closed. Please view the final documents from the stage Documents section.',
                default => 'The matter has been closed. Please view the final documents from the stage Documents section.',
            };
        }
        $formatBytes = static fn ($bytes) => $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : number_format(max(1, ceil($bytes / 1024))) . ' KB';
        $documentDisplayName = static function ($document) use ($evidenceTypes): string {
            foreach (['label', 'document_name', 'document_title', 'review_note'] as $attribute) {
                $value = trim((string) data_get($document, $attribute));

                if ($value !== '') {
                    return $value;
                }
            }

            $evidenceType = trim((string) data_get($document, 'evidence_type'));
            if (\Illuminate\Support\Str::startsWith($evidenceType, 'third_party_requested_evidence_')) {
                return \Illuminate\Support\Str::headline(
                    \Illuminate\Support\Str::after($evidenceType, 'third_party_requested_evidence_')
                );
            }

            $stageLabels = [
                'oppose_admin_additional_document' => 'Additional Evidence Document',
                'notice_draft_additional_document' => 'Draft Supporting Document',
                'notice_filing_additional_document' => 'Filing Supporting Document',
                'notice_tracking_additional_document' => 'Registry Supporting Document',
                'hearing_notice_document' => 'Hearing Notice',
                'decision_order_document' => 'Registry Decision / Order',
                'decision_additional_document' => 'Decision Supporting Document',
            ];

            return $stageLabels[$evidenceType]
                ?? ($evidenceTypes[$evidenceType] ?? 'Additional Document');
        };
        $evidenceStageDocuments = $case->evidence
            ->where('file_path', '!=', 'metadata')
            ->filter(fn ($document) => $document->uploaded_by === 'client'
                || $document->evidence_type === 'oppose_admin_additional_document')
            ->reject(fn ($document) => \Illuminate\Support\Str::startsWith($document->evidence_type, 'third_party_requested_evidence_'))
            ->sortByDesc('id');
        $draftAdditionalDocuments = $case->evidence
            ->where('evidence_type', 'notice_draft_additional_document')
            ->where('uploaded_by', 'admin')
            ->sortByDesc('id');
        $filingAdditionalDocuments = $case->evidence
            ->where('evidence_type', 'notice_filing_additional_document')
            ->where('uploaded_by', 'admin')
            ->sortByDesc('id');
        $trackingAdditionalDocuments = $case->evidence
            ->where('evidence_type', 'notice_tracking_additional_document')
            ->where('uploaded_by', 'admin')
            ->sortByDesc('id');
        $hearingNoticeDocuments = $case->evidence
            ->where('evidence_type', 'hearing_notice_document')
            ->where('uploaded_by', 'admin')
            ->sortByDesc('id');
        $decisionOrderDocuments = $case->evidence
            ->where('evidence_type', 'decision_order_document')
            ->where('uploaded_by', 'admin')
            ->sortByDesc('id');
        $decisionAdditionalDocuments = $case->evidence
            ->where('evidence_type', 'decision_additional_document')
            ->where('uploaded_by', 'admin')
            ->sortByDesc('id');
        $latestHearingNotice = $hearingNoticeDocuments->first();
        $hearingStatusesForDetails = [
            $workflow::ADMIN_HEARING_PREPARATION,
            $workflow::ADMIN_HEARING_SCHEDULED,
            $workflow::ADMIN_HEARING_ADJOURNED,
            $workflow::ADMIN_HEARING_COMPLETED,
        ];
        $latestHearingHistory = $case->statusHistories
            ->filter(fn ($history) => in_array($history->new_status, $hearingStatusesForDetails, true) && $history->changed_by === 'admin')
            ->sortByDesc('id')
            ->first();
        $latestCompletedHearingHistory = $case->statusHistories
            ->filter(fn ($history) => $history->new_status === $workflow::ADMIN_HEARING_COMPLETED && $history->changed_by === 'admin')
            ->sortByDesc('id')
            ->first();
        $hearingSourceHistory = $effectiveAdminStatus === $workflow::ADMIN_DECISION_AWAITED
            ? ($latestCompletedHearingHistory ?: $latestHearingHistory)
            : $latestHearingHistory;
        $hearingHistoryNote = trim((string) ($hearingSourceHistory?->note ?? ''));
        $hearingOutcomeFromNote = \Illuminate\Support\Str::contains($hearingHistoryNote, ' Hearing outcome: ')
            ? trim(\Illuminate\Support\Str::after($hearingHistoryNote, ' Hearing outcome: '))
            : null;
        $hearingAdjournmentReason = \Illuminate\Support\Str::contains($hearingHistoryNote, ' Adjournment reason: ')
            ? trim(\Illuminate\Support\Str::before(\Illuminate\Support\Str::after($hearingHistoryNote, ' Adjournment reason: '), ' Hearing outcome: '))
            : null;
        $hearingAdminNote = trim(\Illuminate\Support\Str::before(\Illuminate\Support\Str::before($hearingHistoryNote, ' Adjournment reason: '), ' Hearing outcome: '));
        $hearingDisplayStatus = match ($effectiveAdminStatus) {
            $workflow::ADMIN_HEARING_PREPARATION => 'Hearing Awaited',
            $workflow::ADMIN_HEARING_SCHEDULED => $case->final_outcome === 'Further Hearing Required' ? 'Further Hearing Scheduled' : 'Hearing Scheduled',
            $workflow::ADMIN_HEARING_ADJOURNED => 'Hearing Adjourned',
            $workflow::ADMIN_DECISION_AWAITED => 'Hearing Completed',
            default => $effectiveAdminStatus,
        };
        $showHearingDetails = in_array($effectiveAdminStatus, $hearingStatusesForDetails, true);
        $thirdPartyEvidenceDocuments = $case->evidence
            ->filter(fn ($document) => \Illuminate\Support\Str::startsWith($document->evidence_type, 'third_party_requested_evidence_'))
            ->sortByDesc('id');
        $counterStatementDocument = collect($case->counter_statement_path ? [[
            'uploaded_by' => 'admin',
            'kind' => 'counter-statement',
            'label' => 'Counter Statement',
            'file_name' => $case->counter_statement_name ?: 'Counter Statement.pdf',
            'file_type' => 'PDF',
            'file_size' => Storage::disk('public')->exists($case->counter_statement_path) ? Storage::disk('public')->size($case->counter_statement_path) : 0,
            'created_at' => $case->updated_at,
        ]] : []);
        $stageDocuments = collect([
            1 => $evidenceStageDocuments,
            4 => $draftAdditionalDocuments,
            5 => collect($case->filing_acknowledgment_path ? [[
                'uploaded_by' => 'admin',
                'kind' => 'filing-acknowledgment',
                'label' => 'Filing Acknowledgment',
                'file_name' => $case->filing_acknowledgment_name ?: 'Filing Acknowledgment',
                'file_type' => strtoupper(pathinfo((string) $case->filing_acknowledgment_name, PATHINFO_EXTENSION) ?: 'FILE'),
                'file_size' => Storage::disk('public')->exists($case->filing_acknowledgment_path) ? Storage::disk('public')->size($case->filing_acknowledgment_path) : 0,
                'created_at' => $case->updated_at,
            ]] : [])->merge($filingAdditionalDocuments),
            6 => $counterStatementDocument,
            7 => $trackingAdditionalDocuments->merge($thirdPartyEvidenceDocuments),
            8 => $hearingNoticeDocuments,
            10 => $decisionOrderDocuments->merge($decisionAdditionalDocuments),
        ])->filter(fn ($documents) => $documents->isNotEmpty());
        $matterClosedAdminDocuments = $stageDocuments->get(10, collect())
            ->where('uploaded_by', 'admin')
            ->values();
    @endphp

    <style>
        .flowb-page{max-width:1180px;margin:-18px auto 34px;padding:0 18px;color:#26364f;font-size:.9rem}.flowb-layout{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:.75rem;align-items:start}.flowb-card{background:#fff;border:1px solid #e6ebf2;border-radius:12px;box-shadow:0 8px 20px rgba(15,36,68,.07);overflow:hidden;padding-top:0!important}.flowb-status{grid-column:1}.flowb-head,.flowb-action-head{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:.8rem 1rem;background:linear-gradient(135deg,#294d78,#2d4a73);color:#fff;margin-top:0!important;border-top-left-radius:0;border-top-right-radius:0}.flowb-card>.flowb-head,.flowb-card>.flowb-action-head,.flowb-sidebar>h2{margin-top:0!important}.flowb-head h1,.flowb-action-head h2{margin:0;color:#fff!important;font-size:1.15rem;font-weight:900}.flowb-head p{margin:.2rem 0 0;color:rgba(255,255,255,.8)}.flowb-pill{display:inline-flex;align-items:center;border-radius:7px;padding:.4rem .7rem;background:#fff;color:#174ea6;font-weight:900;white-space:nowrap}.flowb-body{padding:.8rem 1rem}.flowb-timeline{list-style:none;margin:0;padding:0}.flowb-step{display:grid;grid-template-columns:42px 1fr auto;gap:.75rem;align-items:center;min-height:62px;padding:.55rem 0;border-bottom:1px solid #e5eaf2}.flowb-step:last-child{border-bottom:0}.flowb-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:50%;background:#f1f4f8;color:#7b8794;font-size:1.05rem}.flowb-step.done .flowb-icon{background:#eafaf2;color:#11915c}.flowb-step.active .flowb-icon{background:#eaf1ff;color:#1464f6}.flowb-copy h3{margin:0 0 .15rem;color:#14294b;font-size:1rem;font-weight:900}.flowb-copy p{margin:0;color:#536176}.flowb-state{text-align:right;min-width:115px}.flowb-badge{display:inline-flex;border-radius:7px;padding:.3rem .55rem;background:#fff3d6;color:#895710;font-weight:900}.done .flowb-badge{background:#ddf8e9;color:#137747}.active .flowb-badge{background:#eaf1ff;color:#1464f6}.flowb-sidebar{grid-column:2;grid-row:1 / span 2}.flowb-sidebar h2{margin:0!important;padding:.9rem 1rem;background:#294d78;color:#fff!important;font-size:1rem}.flowb-side-body{padding:1rem}.flowb-field{padding:.8rem;border:1px solid #e6edf7;border-radius:8px;background:#fbfdff;margin-bottom:.65rem}.flowb-field span{display:block;color:#66758b;font-size:.72rem;font-weight:900;text-transform:uppercase}.flowb-field strong{display:block;margin-top:.2rem;color:#14294b;overflow-wrap:anywhere}.flowb-recommendation{border-color:#f2d27a;background:#fff8df}.flowb-action{grid-column:1;margin-top:.75rem}.flowb-action-body{padding:1.25rem}.flowb-action-body h3{margin:0 0 .3rem;color:#14294b;font-size:1.08rem;font-weight:900}.flowb-muted{color:#607089}.flowb-warning{padding:.8rem;border:1px solid #f1cb72;border-radius:8px;background:#fff8e7;color:#67460d;margin:.8rem 0}.flowb-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.8rem}.flowb-upload{padding:.8rem;border:1px solid #e0e7f0;border-radius:8px;background:#fbfdff}.flowb-upload label{display:block;color:#203e68;font-weight:900;margin-bottom:.45rem}.flowb-input{width:100%;min-height:42px;border:1px solid #cad6e8;border-radius:7px;padding:.55rem .7rem}.flowb-btn{min-height:42px;border:0;border-radius:7px;padding:0 1rem;background:#2a9d8f;color:#fff;font-weight:900;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:.4rem}.flowb-btn:hover{color:#fff;background:#23867a}.flowb-actions{display:flex;gap:.65rem;flex-wrap:wrap;margin-top:1rem}.flowb-btn.green{background:#159447}.flowb-btn.gray{background:#6b7280}.flowb-doc-list{border:1px solid #dfe6ef;border-radius:9px;overflow:hidden}.flowb-doc{display:flex;align-items:center;gap:.8rem;padding:.8rem;border-bottom:1px solid #e7ecf3}.flowb-doc:last-child{border-bottom:0}.flowb-doc i{color:#16814a;font-size:1.2rem}.flowb-doc div{flex:1}.flowb-doc strong,.flowb-doc small{display:block}.flowb-doc a{color:#1464f6;font-weight:900;text-decoration:none}.flowb-modal{position:fixed;inset:0;z-index:3100;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(15,35,60,.5)}.flowb-modal.open{display:flex}.flowb-dialog{width:min(520px,100%);background:#fff;border-radius:12px;overflow:hidden}.flowb-dialog header{display:flex;justify-content:space-between;padding:1rem 1.2rem;border-bottom:1px solid #e6ebf2}.flowb-dialog header h3{margin:0}.flowb-dialog header button{border:0;background:none;font-size:1.4rem}.flowb-dialog form{padding:1.2rem}@media(max-width:1050px){.flowb-layout{display:block}.flowb-sidebar,.flowb-action{margin-top:.75rem}}@media(max-width:640px){.flowb-page{padding:0 12px}.flowb-grid{grid-template-columns:1fr}.flowb-step{grid-template-columns:38px 1fr}.flowb-state{grid-column:2;text-align:left}.flowb-head{align-items:flex-start;flex-direction:column}}
    </style>
    <style>
        .flowb-layout {
            row-gap: .9rem;
        }

        .flowb-status {
            align-self: start;
            display: flex;
            flex-direction: column;
            height: auto;
            height: fit-content;
            min-height: 0;
            padding-bottom: 0 !important;
        }

        .flowb-status .flowb-body {
            flex: 0 0 auto;
            padding-bottom: .75rem !important;
        }

        .flowb-status .flowb-step:last-child {
            min-height: 54px;
            padding-bottom: .35rem;
            margin-bottom: 0;
        }

        .flowb-action {
            align-self: start;
            display: flex;
            flex-direction: column;
            height: auto;
            height: fit-content;
            min-height: 0;
            margin-top: 0 !important;
            padding-bottom: 0 !important;
        }

        .flowb-action .flowb-action-body {
            flex: 0 0 auto;
            padding-bottom: 1rem !important;
        }

        .flowb-action .flowb-empty-state {
            margin-bottom: 0 !important;
        }

        .flowb-empty-state {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            padding: 1.05rem 1.15rem;
            border: 1px solid #d8e7ff;
            border-radius: 14px;
            background: linear-gradient(135deg, #f7fbff, #eef6ff);
            color: #536176;
        }

        .flowb-empty-icon {
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

        .flowb-empty-state strong {
            display: block;
            color: #14294b;
            font-size: 1rem;
            font-weight: 900;
            margin-bottom: .2rem;
        }

        .flowb-empty-state p {
            margin: 0;
            line-height: 1.5;
            font-weight: 650;
        }

        .flowb-hearing-details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .9rem;
            margin-top: 0;
        }

        .flowb-hearing-detail {
            display: grid;
            grid-template-columns: 52px minmax(0, 1fr) auto;
            gap: .9rem;
            align-items: center;
            min-height: 96px;
            padding: 1rem;
            border: 1px solid #dbe7f6;
            border-radius: 13px;
            background: linear-gradient(135deg, #fff, #fbfdff);
            box-shadow: 0 10px 22px rgba(15, 36, 68, .055);
        }

        .flowb-hearing-detail.is-wide {
            grid-column: 1 / -1;
        }

        .flowb-hearing-icon {
            width: 52px;
            height: 52px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #eaf1ff;
            color: #1554c0;
            font-size: 1.45rem;
        }

        .flowb-hearing-icon.is-green {
            background: #e7f7ef;
            color: #16814a;
        }

        .flowb-hearing-icon.is-gold {
            background: #fff4d6;
            color: #9a6a0a;
        }

        .flowb-hearing-copy > span:not(.flowb-hearing-chip) {
            display: block;
            margin-bottom: .28rem;
            color: #66758b;
            font-size: .76rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .flowb-hearing-copy strong,
        .flowb-hearing-copy p {
            margin: 0;
            color: #14294b;
            font-weight: 850;
            line-height: 1.45;
            overflow-wrap: anywhere;
            white-space: pre-line;
        }

        .flowb-hearing-copy a {
            color: #0755d9;
            font-weight: 900;
            text-decoration: none;
        }

        .flowb-hearing-meta {
            display: flex;
            align-items: center;
            gap: .45rem;
            margin-top: .55rem;
            color: #607089;
            font-size: .8rem;
            font-weight: 800;
        }

        .flowb-hearing-chip {
            display: inline-flex;
            align-items: center;
            gap: .32rem;
            width: max-content;
            margin-top: .6rem;
            margin-bottom: 0;
            padding: .28rem .55rem;
            border: 1px solid #a8e6bf;
            border-radius: 8px;
            background: #eefbf4;
            color: #16814a;
            font-size: .78rem;
            font-weight: 900;
            text-transform: none;
        }

        .flowb-hearing-chip.is-muted {
            border-color: #d7dee8;
            background: #eef2f7;
            color: #607089;
        }

        .flowb-hearing-download {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border: 1px solid #cfe0f5;
            border-radius: 10px;
            background: #fff;
            color: #0755d9;
            text-decoration: none;
            font-size: 1.1rem;
        }

        .flowb-hearing-download:hover {
            background: #f3f8ff;
            color: #0755d9;
        }

        @media (max-width: 640px) {
            .flowb-hearing-details {
                grid-template-columns: 1fr;
            }

            .flowb-hearing-detail {
                grid-template-columns: 44px minmax(0, 1fr);
                min-height: 0;
            }

            .flowb-hearing-icon {
                width: 44px;
                height: 44px;
                font-size: 1.2rem;
            }

            .flowb-hearing-download {
                grid-column: 2;
                width: max-content;
                min-width: 42px;
                margin-top: .25rem;
            }
        }

        .flowb-step-date {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .35rem;
            margin-top: .4rem;
            color: #6b7890;
            font-size: .78rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .flowb-step.done .flowb-step-date {
            color: #16814a;
        }

        .flowb-step.active .flowb-step-date {
            color: #1464f6;
        }

        .flowb-step-meta {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .75rem;
            margin-top: .4rem;
            white-space: nowrap;
        }

        .flowb-step-meta .flowb-step-date {
            margin-top: 0;
        }

        .flowb-step-deadline {
            color: #8a5a00 !important;
        }

        .flowb-request-documents {
            margin-top: .85rem;
        }

        .flowb-request-documents h4 {
            margin: 0 0 .55rem;
            color: #14294b;
            font-size: .95rem;
            font-weight: 900;
        }

        .flowb-admin-note {
            display: flex;
            gap: .75rem;
            align-items: flex-start;
            margin: .85rem 0;
            padding: .85rem .95rem;
            border: 1px solid #c7d8f6;
            border-radius: 10px;
            background: #f7fbff;
            color: #26364f;
        }

        .flowb-admin-note i {
            width: 32px;
            height: 32px;
            display: grid;
            place-items: center;
            flex: 0 0 32px;
            border-radius: 50%;
            background: #eaf1ff;
            color: #1464f6;
            font-size: 1rem;
        }

        .flowb-admin-note strong {
            display: block;
            margin-bottom: .2rem;
            color: #14294b;
            font-size: .9rem;
            font-weight: 900;
        }

        .flowb-admin-note p {
            margin: 0;
            color: #536176;
            line-height: 1.45;
            font-weight: 700;
            white-space: pre-line;
        }

        .flowb-evidence-form {
            margin-top: .95rem;
        }

        .flowb-evidence-grid {
            gap: .9rem;
        }

        .flowb-upload-card {
            padding: .95rem;
            border: 1px solid #dfe8f4;
            border-radius: 12px;
            background: linear-gradient(180deg, #fbfdff, #f8fbff);
            box-shadow: 0 6px 14px rgba(15, 36, 68, .035);
            transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        .flowb-upload-card:hover,
        .flowb-upload-card.has-file {
            border-color: #b7d0ee;
            box-shadow: 0 10px 20px rgba(15, 36, 68, .06);
        }

        .flowb-upload-card.has-file {
            background: linear-gradient(180deg, #fbfffd, #f2fbf6);
            border-color: #9eddbc;
        }

        .flowb-upload-card.needs-changes {
            background: linear-gradient(180deg, #fffdf6, #fff8df);
            border-color: #efc45d;
            box-shadow: 0 8px 18px rgba(161, 98, 7, .08);
        }

        .flowb-upload-card.needs-changes .flowb-upload-icon {
            background: #fff0bf;
            color: #a16207;
        }

        .flowb-required-mark {
            color: #dc2626;
            font-size: 1rem;
            line-height: 1;
        }

        .flowb-global-admin-note {
            margin: 0 0 .9rem;
            padding: .8rem .9rem;
            border: 1px solid #f1cb72;
            border-radius: 8px;
            background: #fff8e7;
            color: #67460d;
            font-size: .85rem;
            line-height: 1.45;
            font-weight: 700;
        }

        .flowb-global-admin-note strong {
            display: block;
            margin-bottom: .25rem;
            color: #8a5a00;
        }

        .flowb-global-admin-note p {
            margin: 0;
        }

        .flowb-client-legal-points {
            margin-top: 1rem;
            padding: .9rem 1rem;
            border: 1px solid #cfe0f6;
            border-radius: 9px;
            background: #f7fbff;
        }

        .flowb-client-legal-points h4 {
            margin: 0 0 .65rem;
            color: #173b6c;
            font-size: .95rem;
            font-weight: 900;
        }

        .flowb-client-legal-points ul {
            display: grid;
            gap: .5rem;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .flowb-client-legal-points li {
            display: flex;
            align-items: center;
            gap: .55rem;
            color: #203e68;
            font-weight: 800;
        }

        .flowb-client-legal-points li i {
            color: #2a9d8f;
        }

        .flowb-review-requirements {
            margin-top: .55rem !important;
        }

        .flowb-upload-title {
            display: flex;
            align-items: center;
            gap: .6rem;
            margin-bottom: .75rem;
            color: #17355f;
            font-weight: 900;
            line-height: 1.25;
        }

        .flowb-upload-icon {
            width: 32px;
            height: 32px;
            display: grid;
            place-items: center;
            flex: 0 0 32px;
            border-radius: 9px;
            background: #eaf6f0;
            color: #138455;
        }

        .flowb-file-picker {
            position: relative;
            display: flex;
            align-items: center;
            gap: .65rem;
            min-height: 48px;
            padding: .5rem .6rem;
            border: 1px dashed #b9cbe5;
            border-radius: 10px;
            background: #fff;
            cursor: pointer;
        }

        .flowb-file-picker:hover {
            border-color: #2a9d8f;
            background: #fbfffd;
        }

        .flowb-file-native {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .flowb-file-choose {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            flex: 0 0 auto;
            border-radius: 8px;
            padding: .42rem .65rem;
            background: #294d78;
            color: #fff;
            font-weight: 900;
            font-size: .82rem;
        }

        .flowb-file-name {
            min-width: 0;
            color: #26364f;
            font-weight: 700;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .flowb-upload-state {
            display: flex;
            align-items: center;
            gap: .35rem;
            margin-top: .55rem;
            color: #728197;
            font-size: .78rem;
            font-weight: 800;
        }

        .flowb-upload-state.is-uploaded {
            color: #128454;
        }

        .flowb-note-field {
            margin-top: .9rem;
        }

        .flowb-note-field label {
            display: block;
            margin-bottom: .45rem;
            color: #17355f;
            font-weight: 900;
        }

        .flowb-note-field label span {
            color: #607089;
            font-weight: 700;
        }

        .flowb-note-field textarea {
            resize: vertical;
        }

        .flowb-upload-actions {
            align-items: center;
            justify-content: space-between;
            padding-top: .9rem;
            border-top: 1px solid #e7edf5;
        }

        .flowb-secure-note {
            color: #607089;
            font-size: .8rem;
            font-weight: 700;
        }

        .flowb-upload-error {
            display: none;
            width: 100%;
            margin: 0 0 .8rem;
            padding: .7rem .8rem;
            border: 1px solid #f2b8b5;
            border-radius: 8px;
            background: #fff1f0;
            color: #b42318;
            font-weight: 800;
        }

        .flowb-upload-error.is-visible {
            display: block;
        }

        .flowb-stage-title{display:flex;align-items:center;gap:.7rem;flex-wrap:wrap;margin-bottom:.15rem}
        .flowb-stage-title h3{margin:0}
        .flowb-stage-doc-link{display:inline-flex;align-items:center;gap:.35rem;border:0;padding:0;background:transparent;color:#149b7e;font-size:.8rem;font-weight:900;white-space:nowrap;cursor:pointer}
        .flowb-stage-doc-link:hover{color:#0d765f;text-decoration:underline}
        .flowb-stage-doc-modal{position:fixed;inset:0;z-index:3050;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(15,35,60,.5)}
        .flowb-stage-doc-modal.is-visible{display:flex}
        .flowb-stage-doc-dialog{width:min(760px,100%);max-height:min(760px,90vh);overflow:auto;border-radius:12px;background:#fff;box-shadow:0 24px 70px rgba(15,35,60,.3)}
        .flowb-stage-doc-head{position:sticky;top:0;z-index:1;display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1rem 1.2rem;border-bottom:1px solid #e3e9f2;background:#fff}
        .flowb-stage-doc-head h3{margin:0;color:#14294b;font-size:1.08rem}
        .flowb-stage-doc-head p{margin:.2rem 0 0;color:#66758b;font-size:.82rem}
        .flowb-stage-doc-close{border:0;background:transparent;color:#607089;font-size:1.4rem;cursor:pointer}
        .flowb-stage-doc-body{display:grid;gap:1.2rem;padding:1.2rem}
        .flowb-stage-doc-group{min-height:0!important;margin:0!important;padding:0!important}
        .flowb-stage-doc-group h4{margin:0 0 .6rem;color:#536176;font-size:.78rem;font-weight:900;letter-spacing:.03em;text-transform:uppercase}
        .flowb-stage-doc-list{overflow:hidden;border:1px solid #dfe6ef;border-radius:9px}
        .flowb-stage-doc-row{display:flex;align-items:center;gap:.8rem;padding:.85rem;border-bottom:1px solid #e7ecf3}
        .flowb-stage-doc-row:last-child{border-bottom:0}
        .flowb-stage-doc-icon{width:38px;height:38px;flex:0 0 38px;display:grid;place-items:center;border-radius:8px;background:#edf9f1;color:#16814a}
        .flowb-stage-doc-copy{min-width:0;flex:1}
        .flowb-stage-doc-copy strong{display:block;color:#14294b;font-size:.88rem;overflow-wrap:anywhere}
        .flowb-stage-doc-copy span{display:block;margin-top:.15rem;color:#66758b;font-size:.76rem}
        .flowb-stage-doc-actions{display:flex;gap:.45rem}
        .flowb-stage-doc-actions a{display:inline-flex;align-items:center;gap:.35rem;min-height:34px;padding:0 .7rem;border:1px solid #a9c1ff;border-radius:6px;color:#0755d9;font-size:.78rem;font-weight:800;text-decoration:none}

        @media(max-width:640px) {
            .flowb-file-picker {
                align-items: flex-start;
                flex-direction: column;
            }

            .flowb-file-name {
                white-space: normal;
            }

            .flowb-stage-doc-row{align-items:flex-start;flex-wrap:wrap}
            .flowb-stage-doc-actions{width:100%;padding-left:46px}
        }

    </style>

    <div class="flowb-page">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if(isset($errors) && $errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <div class="flowb-layout">
            <section class="flowb-card flowb-status">
                <header class="flowb-head"><div><h1>Oppose a Trademark · Status Tracking</h1><p>{{ $case->case_number }} · {{ $case->trademark_you_own }} vs {{ $case->trademark_to_oppose }}</p></div><span class="flowb-pill">{{ $displayClientStage }}</span></header>
                <div class="flowb-body"><ol class="flowb-timeline">
                    @foreach($statusOrder as $index => [$title,$copy,$icon])
                        @php
                            $state = $index < $stageRank ? 'done' : ($index === $stageRank ? 'active' : 'pending');
                            if ($isMatterClosed && $index === array_key_last($statusOrder)) {
                                $state = 'done';
                            }
                            $changedAt = $statusChangedAt($index);
                            $badgeLabel = $isCaseUnderReview && $index === 0
                                ? 'Under Review'
                                : ($state === 'done' ? 'Completed' : ucfirst($state));
                        @endphp
                        <li class="flowb-step {{ $state }}">
                            <span class="flowb-icon"><i class="bi {{ $icon }}"></i></span>
                            <div class="flowb-copy">
                                <div class="flowb-stage-title">
                                    <h3>{{ $title }}</h3>
                                    @if(($stageDocuments->get($index, collect()))->isNotEmpty())
                                        <button class="flowb-stage-doc-link" type="button" data-open-flowb-stage-documents="{{ $index }}">
                                            <i class="bi bi-folder2-open"></i> View Documents
                                        </button>
                                    @endif
                                </div>
                                <p>{{ $copy }}</p>
                            </div>
                            <div class="flowb-state">
                                <span class="flowb-badge">{{ $badgeLabel }}</span>
                                @if($state !== 'pending' && ($changedAt || ($index === 7 && $case->counter_statement_deadline) || ($index === 8 && $case->hearing_date)))
                                    <div class="flowb-step-meta">
                                        @if($changedAt)
                                            <small class="flowb-step-date">
                                                <i class="bi bi-clock"></i>
                                                {{ $formatDateTime($changedAt) }}
                                            </small>
                                        @endif
                                        @if($index === 7 && $case->counter_statement_deadline)
                                            <small class="flowb-step-date flowb-step-deadline">
                                                <i class="bi bi-calendar-event"></i>
                                                Deadline {{ $case->counter_statement_deadline->format('d M Y') }}
                                            </small>
                                        @endif
                                        @if($index === 8 && $case->hearing_date)
                                            <small class="flowb-step-date flowb-step-deadline">
                                                <i class="bi bi-calendar-event"></i>
                                                Hearing {{ $case->hearing_date->format('d M Y') }}
                                            </small>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol></div>
            </section>

            <aside class="flowb-card flowb-sidebar"><h2>Case Details</h2><div class="flowb-side-body">
                <div class="flowb-field"><span>Trademark You Own</span><strong>{{ $case->trademark_you_own }}</strong></div>
                <div class="flowb-field"><span>Trademark to Oppose</span><strong>{{ $case->trademark_to_oppose }}</strong></div>
                <div class="flowb-field"><span>Application</span><strong>{{ $case->opposed_application_number }} · Class {{ $case->trademark_class }}</strong></div>
                <div class="flowb-field"><span>Conflict Reason</span><strong>{{ $case->conflict_reason }}</strong></div>
                @if($case->recommendation_level)<div class="flowb-field flowb-recommendation"><span>Legal Recommendation</span><strong>{{ $case->recommendation_level }}</strong><div>{{ $recommendationText }}</div>@if($case->recommendation_note_visible && $case->recommendation_note)<div>{{ $case->recommendation_note }}</div>@endif</div>@endif
                @if($case->package_name)<div class="flowb-field"><span>Opposition Package</span><strong>{{ $case->package_name }} · ₹{{ number_format((float)($hasVerifiedOpposePayment ? ($case->paid_amount ?: $oppositionFilingOriginalAmount) : $oppositionFilingPayableAmount),2) }}</strong></div>@endif
                @if($hasVerifiedOpposePayment)
                    <div class="flowb-field flowb-recommendation">
                        <span>Payment Received</span>
                        <strong>{{ $case->package_name ?: 'Notice of Opposition Filing Package' }} · ₹{{ number_format((float)($case->paid_amount ?: $case->total_amount ?: $case->package_price),2) }}</strong>
                        <div class="flowb-actions"><a class="flowb-btn green" href="{{ route('trademark-opposition.oppose.payment.invoice', $case) }}" target="_blank"><i class="bi bi-receipt"></i> Download Invoice</a></div>
                    </div>
                @endif
            </div></aside>

            <section class="flowb-card flowb-action" id="action-center"><header class="flowb-action-head"><h2>Action Center</h2><span class="flowb-pill">{{ $displayActionStatus }}</span></header><div class="flowb-action-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
                @if($actionType==='initial-review')
                    <div class="flowb-empty-state">
                        <span class="flowb-empty-icon"><i class="bi bi-info-circle"></i></span>
                        <div>
                            <strong>No action is required right now.</strong>
                            <p>Your case details are under review. We will notify you by email about the next steps when your action is required.</p>
                        </div>
                    </div>
                @elseif($actionType==='evidence')
                    <h3>Upload Opposition Evidence</h3><p class="flowb-muted">Upload ownership, use, market, and confusion evidence for legal review.</p>
                    @if($rejectedEvidenceNotes->isNotEmpty() || $missingRequiredEvidence->isNotEmpty())
                        <div class="flowb-global-admin-note">
                            @if($rejectedEvidenceNotes->isNotEmpty())
                                <strong>Admin Note — Changes Required</strong>
                                @foreach($rejectedEvidenceNotes as $adminNote)
                                    <p>{{ $adminNote }}</p>
                                @endforeach
                            @endif
                            @if($missingRequiredEvidence->isNotEmpty())
                                <p class="flowb-review-requirements">
                                    <strong>Required before admin review:</strong>
                                    {{ $missingRequiredEvidence->join(', ') }}.
                                </p>
                            @endif
                        </div>
                    @endif
                    <form class="flowb-evidence-form" method="POST" action="{{ route('trademark-opposition.oppose.evidence',$case) }}" enctype="multipart/form-data" data-max-file-bytes="10485760" data-max-request-bytes="39845888">
                        @csrf
                        <div class="flowb-upload-error" data-flowb-upload-error role="alert"></div>
                        <div class="flowb-grid flowb-evidence-grid">
                            @foreach($evidenceTypes as $key=>$label)
                                @php
                                    $existingEvidence = $latestEvidenceByType->get($key);
                                    $needsChanges = $existingEvidence?->review_status === 'rejected';
                                @endphp
                                @continue($existingEvidence?->review_status === 'reviewed')
                                <div class="flowb-upload-card {{ $existingEvidence && !$needsChanges ? 'has-file' : '' }} {{ $needsChanges ? 'needs-changes' : '' }}">
                                    <div class="flowb-upload-title">
                                        <span class="flowb-upload-icon"><i class="bi bi-file-earmark-arrow-up"></i></span>
                                        <span>
                                            {{ $label }}
                                            @if(in_array($key, $requiredEvidenceTypes, true))
                                                <span class="flowb-required-mark" aria-label="Required">*</span>
                                            @endif
                                        </span>
                                    </div>
                                    <label class="flowb-file-picker">
                                        <input class="flowb-file-native" type="file" name="{{ $key }}[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                        <span class="flowb-file-choose"><i class="bi bi-cloud-arrow-up"></i> Choose files</span>
                                        <span class="flowb-file-name" data-flowb-file-name>{{ $existingEvidence ? $existingEvidence->file_name : 'No file chosen' }}</span>
                                    </label>
                                    <small class="flowb-upload-state {{ $existingEvidence && !$needsChanges ? 'is-uploaded' : '' }}">
                                        <i class="bi {{ $needsChanges ? 'bi-exclamation-triangle-fill' : ($existingEvidence ? 'bi-check-circle-fill' : 'bi-info-circle') }}"></i>
                                        {{ $needsChanges ? 'Changes requested — upload a corrected document.' : ($existingEvidence ? 'Already uploaded — choose again to replace/add more.' : 'Accepted: PDF, JPG, PNG, DOC, DOCX.') }}
                                    </small>
                                </div>
                            @endforeach
                        </div>
                        <div class="flowb-actions flowb-upload-actions">
                            <button class="flowb-btn" type="submit"><i class="bi bi-cloud-upload"></i> Submit Evidence</button>
                            <span class="flowb-secure-note"><i class="bi bi-shield-check"></i> You can upload multiple files for each document type.</span>
                        </div>
                    </form>
                @elseif($actionType==='review')
                    <div class="flowb-empty-state">
                        <span class="flowb-empty-icon"><i class="bi bi-info-circle"></i></span>
                        <div>
                            <strong>No action is required right now.</strong>
                            <p>Legal Review Underway. Our team is reviewing similarity, confusion, prior rights, passing off, bad faith, and reputation evidence. You will be notified through email when your action is needed.</p>
                        </div>
                    </div>
                @elseif($actionType==='evidence-review')
                    <div class="flowb-empty-state">
                        <span class="flowb-empty-icon"><i class="bi bi-hourglass-split"></i></span>
                        <div>
                            <strong>No action is required right now.</strong>
                            <p>Your submitted evidence is being reviewed by our team. We will notify you by email if changes or additional documents are required.</p>
                        </div>
                    </div>
                @elseif($actionType==='pricing-preparation')
                    <div class="flowb-empty-state">
                        <span class="flowb-empty-icon"><i class="bi bi-credit-card"></i></span>
                        <div>
                            <strong>No action is required right now.</strong>
                            <p>Your legal review is complete and the pricing package is being prepared. We will notify you by email when payment is ready.</p>
                        </div>
                    </div>
                @elseif($actionType==='payment')
                    <h3>Pricing & Payment</h3><p class="flowb-muted">Includes legal analysis, Notice of Opposition drafting, filing, and case monitoring.</p>@if($case->included_services)<ul>@foreach($case->included_services as $service)<li>{{ $service }}</li>@endforeach</ul>@endif
                    <form method="POST" action="{{ route('trademark-opposition.oppose.payment',$case) }}" class="js-flowb-payment-form" data-create-order-url="{{ route('trademark-opposition.oppose.payment.create-order', $case) }}" data-verify-url="{{ route('trademark-opposition.oppose.payment.verify-signature', $case) }}" data-amount="{{ number_format($oppositionFilingPayableAmount,2,'.','') }}">@csrf
                        @include('partials.payment-coupons', ['paymentCoupons' => $oppositionFilingPaymentCoupons, 'paymentAmount' => $oppositionFilingOriginalAmount, 'couponInputName' => 'opposition_filing_discount_coupon_id', 'selectedCouponId' => $oppositionFilingAutoCoupon?->id])
                        @include('partials.government-fee-notice')
                        <label class="flowb-disclaimer"><input type="checkbox" name="success_disclaimer" value="1" required>  &nbsp;I understand that Legal Bruz cannot guarantee success. The final decision rests with the Trademark Registry.</label><div class="flowb-actions"><button class="flowb-btn green" type="submit"><i class="bi bi-credit-card"></i> Pay ₹<span data-flowb-pay-amount>{{ number_format($oppositionFilingPayableAmount,2) }}</span> & Continue</button></div></form>
                @elseif($actionType==='draft')
                    <h3>Review Notice of Opposition Draft</h3>
                    <p class="flowb-muted">Review the admin-prepared draft and supporting documents, then approve it or request a corrected reupload.</p>
                    @if($draftClientNote !== '')
                        <div class="flowb-global-admin-note">
                            <strong>Admin Note</strong>
                            <p>{{ $draftClientNote }}</p>
                        </div>
                    @endif
                    <div class="flowb-doc-list">
                        <div class="flowb-doc">
                            <i class="bi bi-file-earmark-text"></i>
                            <div><strong>{{ $case->draft_display_name }}</strong><small>Notice of Opposition Draft · Sent by admin</small></div>
                            <a href="{{ route('trademark-opposition.oppose.file.view',[$case,'draft']) }}" target="_blank">View</a>
                        </div>
                        @foreach($draftAdditionalDocuments as $document)
                            <div class="flowb-doc">
                                <i class="bi bi-file-earmark-check"></i>
                                <div>
                                    <strong>{{ $documentDisplayName($document) }}</strong>
                                    <small>Supporting Document · Sent by admin</small>
                                </div>
                                <a href="{{ route('trademark-opposition.oppose.document.view', [$case, 'evidence', $document->id]) }}" target="_blank">View</a>
                            </div>
                        @endforeach
                    </div>
                    <div class="flowb-actions"><button class="flowb-btn green" type="button" data-flowb-modal="approve">Approve Draft</button><button class="flowb-btn gray" type="button" data-flowb-modal="changes">Request Changes</button></div>
                @elseif($actionType==='filed')
                    <h3>Notice of Opposition Filed</h3>
                    <p class="flowb-muted">Your opposition document has been filed successfully.</p>
                    <div class="flowb-doc-list">
                        <div class="flowb-doc">
                            <i class="bi bi-file-earmark-check"></i>
                            <div>
                                <strong>Filing Acknowledgment</strong>
                                <small>Filing Acknowledgment · Sent by admin</small>
                            </div>
                            <a href="{{ route('trademark-opposition.oppose.file.view',[$case,'filing-acknowledgment']) }}" target="_blank">View</a>
                        </div>
                        @foreach($filingAdditionalDocuments as $document)
                            <div class="flowb-doc">
                                <i class="bi bi-file-earmark-check"></i>
                                <div>
                                    <strong>{{ $documentDisplayName($document) }}</strong>
                                    <small>Supporting Document · Sent by admin</small>
                                </div>
                                <a href="{{ route('trademark-opposition.oppose.document.view', [$case, 'evidence', $document->id]) }}" target="_blank">View</a>
                            </div>
                        @endforeach
                    </div>
                @elseif($actionType==='third-party-evidence')
                    <h3>Additional Evidence Required</h3>
                    @if($case->counter_statement_deadline)
                        <div class="flowb-warning">
                            <strong>Deadline:</strong> {{ $case->counter_statement_deadline->format('d M Y') }}
                        </div>
                    @endif
                    @if(filled($case->third_party_evidence_message))
                        <div class="flowb-admin-note">
                            <i class="bi bi-chat-left-text"></i>
                            <div>
                                <strong>Admin Note</strong>
                                <p>{{ $case->third_party_evidence_message }}</p>
                            </div>
                        </div>
                    @endif
                    @if($trackingAdditionalDocuments->isNotEmpty())
                        <div class="flowb-request-documents">
                            <h4>Additional Documents Attached</h4>
                            <div class="flowb-doc-list">
                                @foreach($trackingAdditionalDocuments as $document)
                                    <div class="flowb-doc">
                                        <i class="bi bi-file-earmark-text"></i>
                                        <div>
                                            <strong>{{ $documentDisplayName($document) }}</strong>
                                            <small>{{ strtoupper($document->file_type ?: 'FILE') }} · Sent by admin</small>
                                        </div>
                                        <a href="{{ route('trademark-opposition.oppose.document.view', [$case, 'evidence', $document->id]) }}" target="_blank">View</a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <form class="flowb-evidence-form" method="POST" action="{{ route('trademark-opposition.oppose.third-party-evidence', $case) }}" enctype="multipart/form-data" data-max-file-bytes="10485760" data-max-request-bytes="39845888">
                        @csrf
                        <div class="flowb-upload-error" data-flowb-upload-error role="alert"></div>
                        <div class="flowb-grid flowb-evidence-grid">
                            @foreach($thirdPartyEvidenceRequestLabels as $requestIndex => $requestLabel)
                                <div class="flowb-upload-card">
                                    <div class="flowb-upload-title">
                                        <span class="flowb-upload-icon"><i class="bi bi-file-earmark-arrow-up"></i></span>
                                        <span>{{ $requestLabel }} <span class="flowb-required-mark" aria-label="Required">*</span></span>
                                    </div>
                                    <label class="flowb-file-picker">
                                        <input class="flowb-file-native" type="file" name="requested_documents[{{ $requestIndex }}][]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                                        <span class="flowb-file-choose"><i class="bi bi-cloud-arrow-up"></i> Choose files</span>
                                        <span class="flowb-file-name" data-flowb-file-name>No file chosen</span>
                                    </label>
                                    <small class="flowb-upload-state"><i class="bi bi-info-circle"></i> Accepted: PDF, JPG, PNG, DOC, DOCX.</small>
                                </div>
                            @endforeach
                        </div>
                        <div class="flowb-note-field">
                            <label for="thirdPartyEvidenceNote">Note to Admin <span>(optional)</span></label>
                            <textarea id="thirdPartyEvidenceNote" class="flowb-input" name="note_to_admin" rows="4" placeholder="Add any context for the admin reviewing these evidence files.">{{ old('note_to_admin') }}</textarea>
                        </div>
                        <div class="flowb-actions flowb-upload-actions">
                            <button class="flowb-btn" type="submit"><i class="bi bi-cloud-upload"></i> Submit Evidence</button>
                            <span class="flowb-secure-note"><i class="bi bi-shield-check"></i> All requested items are required.</span>
                        </div>
                    </form>
                @else
                    @if($showHearingDetails)
                        <div class="flowb-hearing-details">
                            <div class="flowb-hearing-detail">
                                <span class="flowb-hearing-icon"><i class="bi bi-file-earmark-text"></i></span>
                                <div class="flowb-hearing-copy">
                                    <span>Hearing Status</span>
                                    <strong>{{ $hearingDisplayStatus }}</strong>
                                </div>
                            </div>
                            @if($case->hearing_date)
                                <div class="flowb-hearing-detail">
                                    <span class="flowb-hearing-icon"><i class="bi bi-calendar-event"></i></span>
                                    <div class="flowb-hearing-copy">
                                        <span>{{ in_array($effectiveAdminStatus, [$workflow::ADMIN_HEARING_ADJOURNED], true) || $case->final_outcome === 'Further Hearing Required' ? 'New Hearing Date' : 'Hearing Date' }}</span>
                                        <strong>{{ $case->hearing_date->format('d F Y') }}</strong>
                                    </div>
                                </div>
                            @endif
                            <div class="flowb-hearing-detail">
                                <span class="flowb-hearing-icon"><i class="bi bi-file-earmark-arrow-up"></i></span>
                                <div class="flowb-hearing-copy">
                                    <span>Hearing Notice</span>
                                    @if($latestHearingNotice)
                                        @php
                                            $hearingNoticeUrl = route('trademark-opposition.oppose.document.view', [$case, 'evidence', $latestHearingNotice->id]);
                                        @endphp
                                        <strong><a href="{{ $hearingNoticeUrl }}" target="_blank">{{ $latestHearingNotice->review_note ?: 'Hearing Notice' }}</a></strong>
                                        <div class="flowb-hearing-meta">
                                            <span>{{ strtoupper($latestHearingNotice->file_type ?: 'PDF') }} Document</span>
                                            <span class="flowb-hearing-chip is-muted">{{ $formatBytes($latestHearingNotice->file_size) }}</span>
                                        </div>
                                    @else
                                        <strong>Not uploaded</strong>
                                    @endif
                                </div>
                                @if($latestHearingNotice)
                                    <a class="flowb-hearing-download" href="{{ $hearingNoticeUrl }}?download=1" aria-label="Download hearing notice"><i class="bi bi-download"></i></a>
                                @endif
                            </div>
                            @if(filled($case->final_outcome) || filled($hearingOutcomeFromNote))
                                <div class="flowb-hearing-detail">
                                    <span class="flowb-hearing-icon is-green"><i class="bi bi-check2-circle"></i></span>
                                    <div class="flowb-hearing-copy">
                                        <span>Hearing Outcome</span>
                                        <strong>{{ $hearingOutcomeFromNote ?: \App\Support\TrademarkOppositionWorkflow::finalOutcomeLabel($case->final_outcome) }}</strong>
                                        <span class="flowb-hearing-chip"><i class="bi bi-check-circle"></i> Outcome Recorded</span>
                                    </div>
                                </div>
                            @endif
                            @if(filled($hearingAdminNote) || filled($hearingAdjournmentReason))
                                <div class="flowb-hearing-detail is-wide">
                                    <span class="flowb-hearing-icon is-gold"><i class="bi bi-pencil-square"></i></span>
                                    <div class="flowb-hearing-copy">
                                        <span>Hearing Notes</span>
                                        <p>{{ filled($hearingAdminNote) ? $hearingAdminNote : $hearingAdjournmentReason }}</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="flowb-empty-state">
                            <span class="flowb-empty-icon"><i class="bi bi-bell"></i></span>
                            <div>
                                <strong>{{ $waitingTitle }}</strong>
                                <p>{!! nl2br(e($waitingMessage)) !!}</p>
                            </div>
                        </div>
                        @if($isMatterClosed && $matterClosedAdminDocuments->isNotEmpty())
                            <div class="flowb-request-documents">
                                <h4>Documents Sent by Admin</h4>
                                <div class="flowb-doc-list">
                                    @foreach($matterClosedAdminDocuments as $document)
                                        @php
                                            $documentKind = data_get($document, 'kind', 'evidence');
                                            $documentUrl = in_array($documentKind, ['filing-acknowledgment', 'counter-statement'], true)
                                                ? route('trademark-opposition.oppose.file.view', [$case, $documentKind])
                                                : route('trademark-opposition.oppose.document.view', [$case, 'evidence', data_get($document, 'id')]);
                                            $documentLabel = $documentDisplayName($document);
                                            $documentType = strtoupper(data_get($document, 'file_type') ?: 'FILE');
                                            $documentSize = $formatBytes(data_get($document, 'file_size'));
                                            $documentDate = $formatDateTime(data_get($document, 'created_at'));
                                        @endphp
                                        <div class="flowb-doc">
                                            <i class="bi bi-file-earmark-arrow-down"></i>
                                            <div>
                                                <strong>{{ $documentLabel }}</strong>
                                                <small>{{ $documentType }} · {{ $documentSize }} · Sent {{ $documentDate }}</small>
                                            </div>
                                            <a href="{{ $documentUrl }}" target="_blank">View</a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                @endif
                @if($visibleLegalReviewPoints->isNotEmpty())
                    <div class="flowb-client-legal-points">
                        <h4>Legal Points Identified</h4>
                        <ul>
                            @foreach($visibleLegalReviewPoints as $legalPoint)
                                <li><i class="bi bi-check-circle-fill"></i><span>{{ $legalPoint }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div></section>
        </div>
    </div>

    @foreach($stageDocuments as $stageIndex => $documents)
        @php
            $clientStageDocuments = $documents->where('uploaded_by', 'client');
            $adminStageDocuments = $documents->where('uploaded_by', 'admin');
        @endphp
        <div class="flowb-stage-doc-modal" data-flowb-stage-documents-modal="{{ $stageIndex }}" role="dialog" aria-modal="true" aria-labelledby="flowbStageDocumentsTitle{{ $stageIndex }}">
            <div class="flowb-stage-doc-dialog">
                <div class="flowb-stage-doc-head">
                    <div>
                        <h3 id="flowbStageDocumentsTitle{{ $stageIndex }}">{{ $statusOrder[$stageIndex][0] }} Documents</h3>
                        <p>Documents exchanged during this stage</p>
                    </div>
                    <button class="flowb-stage-doc-close" type="button" data-close-flowb-stage-documents aria-label="Close">&times;</button>
                </div>
                <div class="flowb-stage-doc-body">
                    @if($clientStageDocuments->isNotEmpty())
                        <section class="flowb-stage-doc-group">
                            <h4>Documents You Sent</h4>
                            <div class="flowb-stage-doc-list">
                                @foreach($clientStageDocuments as $document)
                                    @php
                                        $documentUrl = route('trademark-opposition.oppose.document.view', [$case, 'evidence', data_get($document, 'id')]);
                                        $documentLabel = $documentDisplayName($document);
                                        $documentType = strtoupper(data_get($document, 'file_type') ?: 'FILE');
                                        $documentSize = $formatBytes(data_get($document, 'file_size'));
                                        $documentDate = $formatDateTime(data_get($document, 'created_at'));
                                    @endphp
                                    <div class="flowb-stage-doc-row">
                                        <span class="flowb-stage-doc-icon"><i class="bi bi-file-earmark-check"></i></span>
                                        <div class="flowb-stage-doc-copy">
                                            <strong>{{ $documentLabel }}</strong>
                                            <span>{{ $documentType }} · {{ $documentSize }} · Sent {{ $documentDate }}</span>
                                        </div>
                                        <div class="flowb-stage-doc-actions">
                                            <a href="{{ $documentUrl }}" target="_blank"><i class="bi bi-eye"></i> View</a>
                                            <a href="{{ $documentUrl }}?download=1"><i class="bi bi-download"></i> Download</a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                    @if($adminStageDocuments->isNotEmpty())
                        <section class="flowb-stage-doc-group">
                            <h4>Documents Sent by Admin</h4>
                            <div class="flowb-stage-doc-list">
                                @foreach($adminStageDocuments as $document)
                                    @php
                                        $documentKind = data_get($document, 'kind', 'evidence');
                                        $documentUrl = in_array($documentKind, ['filing-acknowledgment', 'counter-statement'], true)
                                            ? route('trademark-opposition.oppose.file.view', [$case, $documentKind])
                                            : route('trademark-opposition.oppose.document.view', [$case, 'evidence', data_get($document, 'id')]);
                                        $documentLabel = $documentDisplayName($document);
                                        $documentType = strtoupper(data_get($document, 'file_type') ?: 'FILE');
                                        $documentSize = $formatBytes(data_get($document, 'file_size'));
                                        $documentDate = $formatDateTime(data_get($document, 'created_at'));
                                    @endphp
                                    <div class="flowb-stage-doc-row">
                                        <span class="flowb-stage-doc-icon"><i class="bi bi-file-earmark-arrow-down"></i></span>
                                        <div class="flowb-stage-doc-copy">
                                            <strong>{{ $documentLabel }}</strong>
                                            <span>{{ $documentType }} · {{ $documentSize }} · Sent {{ $documentDate }}</span>
                                        </div>
                                        <div class="flowb-stage-doc-actions">
                                            <a href="{{ $documentUrl }}" target="_blank"><i class="bi bi-eye"></i> View</a>
                                            <a href="{{ $documentUrl }}?download=1"><i class="bi bi-download"></i> Download</a>
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

    @if($actionType==='draft')
        <div class="flowb-modal" data-flowb-dialog="approve"><div class="flowb-dialog"><header><h3>Approve Notice Draft</h3><button type="button" data-flowb-close>&times;</button></header><form method="POST" action="{{ route('trademark-opposition.oppose.draft.approve',$case) }}">@csrf<label>Approval Note</label><textarea class="flowb-input" name="client_approval_note" rows="4" placeholder="Optional approval note"></textarea><div class="flowb-actions"><button class="flowb-btn gray" type="button" data-flowb-close>Cancel</button><button class="flowb-btn green" type="submit">Approve Draft</button></div></form></div></div>
        <div class="flowb-modal" data-flowb-dialog="changes"><div class="flowb-dialog"><header><h3>Request Draft Reupload</h3><button type="button" data-flowb-close>&times;</button></header><form method="POST" action="{{ route('trademark-opposition.oppose.draft.request-changes',$case) }}">@csrf<label>Changes Required</label><textarea class="flowb-input" name="client_change_request" rows="5" required placeholder="Describe every correction required"></textarea><div class="flowb-actions"><button class="flowb-btn gray" type="button" data-flowb-close>Cancel</button><button class="flowb-btn" type="submit">Request Reupload</button></div></form></div></div>
    @endif
    @if($actionType === 'payment')
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    @endif
    <script>
        document.querySelectorAll('.flowb-evidence-form').forEach((form) => {
            const maxFileBytes = Number(form.dataset.maxFileBytes || 10485760);
            const maxRequestBytes = Number(form.dataset.maxRequestBytes || 39845888);
            const errorBox = form.querySelector('[data-flowb-upload-error]');
            const formatMb = (bytes) => (bytes / 1048576).toFixed(1);

            form.addEventListener('submit', (event) => {
                const files = Array.from(form.querySelectorAll('input[type="file"]'))
                    .flatMap((input) => Array.from(input.files || []));
                const oversizedFile = files.find((file) => file.size > maxFileBytes);
                const requestBytes = files.reduce((total, file) => total + file.size, 0);
                let message = '';

                if (oversizedFile) {
                    message = `${oversizedFile.name} is ${formatMb(oversizedFile.size)} MB. Each file must be 10 MB or smaller.`;
                } else if (requestBytes > maxRequestBytes) {
                    message = `The selected files total ${formatMb(requestBytes)} MB. Please keep one submission below 38 MB or upload the documents in smaller batches.`;
                }

                if (message) {
                    event.preventDefault();
                    errorBox.textContent = message;
                    errorBox.classList.add('is-visible');
                    errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }

                errorBox.textContent = '';
                errorBox.classList.remove('is-visible');
            });
        });
    </script>
    <script>document.querySelectorAll('[data-open-flowb-stage-documents]').forEach(b=>b.addEventListener('click',()=>document.querySelector(`[data-flowb-stage-documents-modal="${b.dataset.openFlowbStageDocuments}"]`)?.classList.add('is-visible')));document.querySelectorAll('[data-flowb-stage-documents-modal]').forEach(m=>{m.querySelectorAll('[data-close-flowb-stage-documents]').forEach(b=>b.addEventListener('click',()=>m.classList.remove('is-visible')));m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('is-visible')})});document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('[data-flowb-stage-documents-modal].is-visible').forEach(m=>m.classList.remove('is-visible'))});document.querySelectorAll('[data-flowb-modal]').forEach(b=>b.addEventListener('click',()=>document.querySelector(`[data-flowb-dialog="${b.dataset.flowbModal}"]`)?.classList.add('open')));document.querySelectorAll('[data-flowb-dialog]').forEach(m=>{m.querySelectorAll('[data-flowb-close]').forEach(b=>b.addEventListener('click',()=>m.classList.remove('open')));m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open')})});document.querySelectorAll('.flowb-file-native').forEach(input=>{input.addEventListener('change',()=>{const card=input.closest('.flowb-upload-card');const label=card?.querySelector('[data-flowb-file-name]');const state=card?.querySelector('.flowb-upload-state');const count=input.files?.length||0;if(!label||!count)return;label.textContent=count===1?input.files[0].name:`${count} files selected`;card?.classList.add('has-file');if(state){state.classList.add('is-uploaded');state.innerHTML='<i class="bi bi-check-circle-fill"></i> Ready to upload.';}})});document.querySelectorAll('.js-flowb-payment-form').forEach(form=>{const button=form.querySelector('button[type="submit"]');const couponInputs=Array.from(form.querySelectorAll('input[name="opposition_filing_discount_coupon_id"]'));const amountText=form.querySelector('[data-flowb-pay-amount]');const selectedCoupon=()=>couponInputs.find(input=>input.checked);const updateAmount=()=>{const amount=selectedCoupon()?.dataset.payableAmount||form.dataset.amount||'0.00';if(amountText)amountText.textContent=Number(amount).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});};couponInputs.forEach(input=>input.addEventListener('change',updateAmount));updateAmount();form.addEventListener('submit',async event=>{event.preventDefault();if(!form.checkValidity()){form.reportValidity();return;}if(!window.Razorpay){alert('Razorpay checkout could not be loaded. Please refresh and try again.');return;}button.disabled=true;const oldHtml=button.innerHTML;button.innerHTML='Creating order...';try{const response=await fetch(form.dataset.createOrderUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':form.querySelector('input[name="_token"]')?.value||'','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({success_disclaimer:form.querySelector('input[name="success_disclaimer"]')?.checked?'1':'',discount_coupon_id:selectedCoupon()?.value||null})});const orderData=await response.json();if(!response.ok||orderData.status!=='success')throw new Error(orderData.message||'Unable to create payment order.');const razorpay=new Razorpay({key:orderData.key,amount:orderData.amount,currency:orderData.currency,name:'{{ config('app.name') }}',description:orderData.description,order_id:orderData.order_id,prefill:{name:orderData.user_name,email:orderData.user_email,contact:orderData.user_phone},theme:{color:'#2A9D8F'},handler:async paymentResponse=>{button.innerHTML='Verifying payment...';const verifyResponse=await fetch(form.dataset.verifyUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':form.querySelector('input[name="_token"]')?.value||'','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(paymentResponse)});const verifyData=await verifyResponse.json();if(!verifyResponse.ok||verifyData.status!=='success')throw new Error(verifyData.message||'Payment verification failed.');window.location.href=verifyData.redirect_url;},modal:{ondismiss:()=>{button.disabled=false;button.innerHTML=oldHtml;}}});razorpay.open();button.disabled=false;button.innerHTML=oldHtml;}catch(error){alert(error.message||'Unable to process payment.');button.disabled=false;button.innerHTML=oldHtml;}})});</script>
@endsection
