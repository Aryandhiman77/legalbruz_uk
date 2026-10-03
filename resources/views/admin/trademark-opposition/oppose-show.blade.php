@extends('layouts.app')

@section('content')
    @php
        $workflow = \App\Support\TrademarkOppositionWorkflow::class;
        $displayTimezone = config('app.timezone', 'Europe/London');
        $formatDateTime = fn ($timestamp, string $format = 'd M Y, h:i A T') => $timestamp
            ? \Illuminate\Support\Carbon::parse($timestamp)->timezone($displayTimezone)->format($format)
            : null;
        $currentStatus = $case->current_admin_status;
        if ($currentStatus === $workflow::ADMIN_NOTICE_FILED && filled($case->filing_acknowledgment_path)) {
            $currentStatus = $workflow::ADMIN_COUNTER_STATEMENT_AWAITED;
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
            $currentStatus = $workflow::ADMIN_EVIDENCE_BY_OPPONENT;
        }
        $counterStatementDeadline = $case->counter_statement_deadline;
        $counterStatementDaysRemaining = $counterStatementDeadline
            ? now()->startOfDay()->diffInDays($counterStatementDeadline->copy()->startOfDay(), false)
            : null;
        $thirdPartyStatus = old('third_party_status', $case->third_party_status ?: $workflow::THIRD_PARTY_AWAITING_RESPONSE);
        if ($counterStatementDaysRemaining !== null && $counterStatementDaysRemaining < 0
            && $thirdPartyStatus === $workflow::THIRD_PARTY_AWAITING_RESPONSE) {
            $thirdPartyStatus = $workflow::THIRD_PARTY_DEADLINE_EXPIRED;
        }
        $deadlineTone = $counterStatementDaysRemaining === null
            ? 'is-muted'
            : ($counterStatementDaysRemaining >= 30 ? '' : ($counterStatementDaysRemaining >= 15 ? 'is-warning' : 'is-danger'));
        $thirdPartyStatusOptions = [
            $workflow::THIRD_PARTY_AWAITING_RESPONSE => 'Awaiting Response',
            $workflow::THIRD_PARTY_COUNTER_STATEMENT_RECEIVED => 'Counter Statement Received',
            $workflow::THIRD_PARTY_NO_RESPONSE => 'No Response Received',
            $workflow::THIRD_PARTY_DEADLINE_EXPIRED => 'Deadline Expired',
        ];
        $thirdPartyActionValue = match ($thirdPartyStatus) {
            $workflow::THIRD_PARTY_COUNTER_STATEMENT_RECEIVED => 'move_to_evidence',
            $workflow::THIRD_PARTY_NO_RESPONSE, $workflow::THIRD_PARTY_DEADLINE_EXPIRED => 'close_matter',
            default => 'save',
        };
        $thirdPartyActionLabel = match ($thirdPartyActionValue) {
            'move_to_evidence' => 'Save & Move to Evidence Stage',
            'close_matter' => 'Close Matter',
            default => 'Save',
        };
        $thirdPartyActionClass = match ($thirdPartyActionValue) {
            'move_to_evidence' => 'admin-btn-secondary',
            'close_matter' => 'admin-btn-danger',
            default => '',
        };
        $clientEvidence = $case->evidence->where('file_path', '!=', 'metadata')->where('uploaded_by', 'client');
        $latestClientEvidence = $clientEvidence->sortByDesc('id')->unique('evidence_type');
        $latestEvidenceStageDocuments = $latestClientEvidence
            ->filter(fn ($evidence) => \Illuminate\Support\Str::startsWith($evidence->evidence_type, 'third_party_requested_evidence_'))
            ->values();
        $selectableEvidenceStatuses = ['pending', 'reuploaded'];
        $selectableLatestClientEvidence = $latestClientEvidence
            ->filter(fn ($evidence) => in_array($evidence->review_status, $selectableEvidenceStatuses, true))
            ->values();
        $allLatestEvidenceStageDocumentsReviewed = $latestEvidenceStageDocuments->isNotEmpty()
            && $latestEvidenceStageDocuments->every(fn ($evidence) => $evidence->review_status === 'reviewed');
        $isEvidenceStageActive = in_array($currentStatus, [
            $workflow::ADMIN_EVIDENCE_BY_OPPONENT,
            $workflow::ADMIN_EVIDENCE_BY_APPLICANT,
            $workflow::ADMIN_EVIDENCE_IN_REPLY,
            $workflow::ADMIN_EVIDENCE_FILED,
        ], true);
        $uploadedEvidenceTypes = $latestClientEvidence->reject(fn ($evidence) => $evidence->review_status === 'rejected')->pluck('evidence_type')->all();
        $requiredEvidenceComplete = collect(\App\Support\TrademarkOppositionWorkflow::opposeRequiredEvidenceGroups())->every(fn ($types) => count(array_intersect($types, $uploadedEvidenceTypes)) > 0);
        $allLatestClientEvidenceReviewed = $latestClientEvidence->isNotEmpty()
            && $latestClientEvidence->every(fn ($evidence) => $evidence->review_status === 'reviewed');
        $activeAction = 'tracking';
        $isMatterClosed = in_array($currentStatus, [
            $workflow::ADMIN_MATTER_CLOSED,
            $workflow::ADMIN_OPPOSITION_ALLOWED,
            $workflow::ADMIN_OPPOSITION_DISMISSED,
            $workflow::ADMIN_SETTLEMENT_CLOSED,
        ], true);
        $decisionOutcomeOptions = \App\Support\TrademarkOppositionWorkflow::decisionOutcomeOptions();
        $withdrawnByOptions = \App\Support\TrademarkOppositionWorkflow::withdrawnByOptions();
        $decisionFormState = \App\Support\TrademarkOppositionWorkflow::decisionFormState($case->final_outcome, $case->withdrawn_by);
        $selectedDecisionStatus = old('decision_status', $decisionFormState['decision_status'] ?? null);
        $selectedWithdrawnBy = old('withdrawn_by', $decisionFormState['withdrawn_by'] ?? null);
        $decisionPreview = \App\Support\TrademarkOppositionWorkflow::decisionPreview($selectedDecisionStatus, $selectedWithdrawnBy);
        $decisionPreviewMap = [
            $workflow::ADMIN_OPPOSITION_ALLOWED => \App\Support\TrademarkOppositionWorkflow::decisionPreview($workflow::ADMIN_OPPOSITION_ALLOWED),
            $workflow::ADMIN_OPPOSITION_DISMISSED => \App\Support\TrademarkOppositionWorkflow::decisionPreview($workflow::ADMIN_OPPOSITION_DISMISSED),
            $workflow::ADMIN_SETTLEMENT_CLOSED => \App\Support\TrademarkOppositionWorkflow::decisionPreview($workflow::ADMIN_SETTLEMENT_CLOSED),
            'withdrawn_opponent' => \App\Support\TrademarkOppositionWorkflow::decisionPreview($workflow::ADMIN_WITHDRAWN, $workflow::WITHDRAWN_BY_OPPONENT),
            'withdrawn_applicant' => \App\Support\TrademarkOppositionWorkflow::decisionPreview($workflow::ADMIN_WITHDRAWN, $workflow::WITHDRAWN_BY_APPLICANT),
            $workflow::ADMIN_OTHER => \App\Support\TrademarkOppositionWorkflow::decisionPreview($workflow::ADMIN_OTHER),
        ];
        $opposeCaseOutcomeLabel = \App\Support\TrademarkOppositionWorkflow::opposeOutcomeLabel($case->oppose_case_status);
        $finalOutcomeLabel = \App\Support\TrademarkOppositionWorkflow::finalOutcomeLabel($case->final_outcome);
        $latestMatterClosedHistory = $case->statusHistories
            ->filter(fn ($history) => $history->new_status === $workflow::ADMIN_MATTER_CLOSED)
            ->sortByDesc('id')
            ->first();
        $matterClosedAdminDocuments = $case->evidence
            ->where('uploaded_by', 'admin')
            ->whereIn('evidence_type', ['decision_order_document', 'decision_additional_document'])
            ->sortByDesc('id')
            ->values();
        if (!$requiredEvidenceComplete || in_array($currentStatus, [
            \App\Support\TrademarkOppositionWorkflow::ADMIN_APPLICATION_RECEIVED,
            \App\Support\TrademarkOppositionWorkflow::ADMIN_DOCUMENTS_PENDING,
        ], true)) {
            $activeAction = 'request-evidence';
        } elseif (!$allLatestClientEvidenceReviewed || $currentStatus === \App\Support\TrademarkOppositionWorkflow::ADMIN_EVIDENCE_COLLECTION) {
            $activeAction = 'evidence-review';
        } elseif (!$case->legalReviewPoints->count() || !$case->recommendation_level || !$case->package_price || $currentStatus === \App\Support\TrademarkOppositionWorkflow::ADMIN_LEGAL_ANALYSIS) {
            $activeAction = 'review';
        } elseif ($case->payment_status !== 'paid') {
            $activeAction = 'await-payment';
        } elseif (!$case->draft_path || in_array($currentStatus, [\App\Support\TrademarkOppositionWorkflow::ADMIN_NOTICE_DRAFTING, \App\Support\TrademarkOppositionWorkflow::ADMIN_DRAFT_UNDER_LEGAL_REVIEW, \App\Support\TrademarkOppositionWorkflow::ADMIN_CLIENT_APPROVAL_PENDING], true)) {
            $activeAction = 'draft';
        } elseif (!$case->filing_acknowledgment_path && ($currentStatus === \App\Support\TrademarkOppositionWorkflow::ADMIN_READY_FOR_FILING || $case->client_approval_status === 'approved')) {
            $activeAction = 'filing';
        } elseif ($currentStatus === $workflow::ADMIN_COUNTER_STATEMENT_AWAITED) {
            $activeAction = 'third-party';
        } elseif ($isEvidenceStageActive && $allLatestEvidenceStageDocumentsReviewed) {
            $activeAction = 'evidence-file';
        } elseif (in_array($currentStatus, [
            $workflow::ADMIN_HEARING_PREPARATION,
            $workflow::ADMIN_HEARING_SCHEDULED,
            $workflow::ADMIN_HEARING_COMPLETED,
            $workflow::ADMIN_HEARING_ADJOURNED,
        ], true)) {
            $activeAction = 'hearing';
        } elseif ($currentStatus === $workflow::ADMIN_DECISION_AWAITED) {
            $activeAction = 'decision';
        } elseif ($isMatterClosed) {
            $activeAction = 'closed';
        }
        $legalReviewRows = old('legal_points');
        if (!is_array($legalReviewRows)) {
            $legalReviewRows = $case->legalReviewPoints->map(fn ($point) => [
                'review_point' => $point->review_point,
                'admin_note' => $point->admin_note,
                'is_client_visible' => $point->is_client_visible,
            ])->values()->all();
        }
        if ($legalReviewRows === []) {
            $legalReviewRows = [['review_point' => '', 'admin_note' => '', 'is_client_visible' => false]];
        }
        $oppositionTimelineRank = match ($currentStatus) {
            $workflow::ADMIN_NOTICE_FILED, $workflow::ADMIN_COUNTER_STATEMENT_AWAITED => 1,
            $workflow::ADMIN_EVIDENCE_BY_OPPONENT => 3,
            $workflow::ADMIN_EVIDENCE_BY_APPLICANT => 4,
            $workflow::ADMIN_EVIDENCE_IN_REPLY => 5,
            $workflow::ADMIN_EVIDENCE_FILED => 5,
            $workflow::ADMIN_HEARING_PREPARATION,
            $workflow::ADMIN_HEARING_SCHEDULED,
            $workflow::ADMIN_HEARING_ADJOURNED,
            $workflow::ADMIN_HEARING_COMPLETED => 6,
            $workflow::ADMIN_DECISION_AWAITED, $workflow::ADMIN_MATTER_CLOSED,
            $workflow::ADMIN_OPPOSITION_ALLOWED, $workflow::ADMIN_OPPOSITION_DISMISSED,
            $workflow::ADMIN_SETTLEMENT_CLOSED => 7,
            default => 0,
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
        $adminNoteEntries = collect([
            filled($case->admin_internal_notes) ? [
                'title' => 'Internal Admin Note',
                'body' => $case->admin_internal_notes,
                'meta' => 'Visible to admin only',
            ] : null,
            filled($case->third_party_evidence_message) ? [
                'title' => 'Evidence Stage Request Note',
                'body' => $case->third_party_evidence_message,
                'meta' => 'Shown to client',
            ] : null,
            filled($case->evidence_request_note) ? [
                'title' => 'Evidence Collection Request Note',
                'body' => $case->evidence_request_note,
                'meta' => 'Shown to client',
            ] : null,
            filled($case->draft_client_note) ? [
                'title' => 'Draft Client Note',
                'body' => $case->draft_client_note,
                'meta' => 'Shown to client',
            ] : null,
        ])->filter()->values();
        $clientNoteEntries = collect([
            filled($case->client_approval_note) ? [
                'title' => 'Draft Change Request',
                'body' => $case->client_approval_note,
                'meta' => 'Submitted by client',
            ] : null,
        ])->filter()->values()->merge($case->statusHistories
            ->filter(fn ($history) => $history->changed_by === 'client'
                && \Illuminate\Support\Str::contains((string) $history->note, 'Note to admin:'))
            ->map(fn ($history) => [
                'title' => $history->new_status,
                'body' => trim(\Illuminate\Support\Str::after((string) $history->note, 'Note to admin:')),
                'meta' => $formatDateTime($history->created_at),
            ])
            ->filter(fn ($entry) => $entry['body'] !== '')
            ->values());
        $parseHistoryNote = static function (?string $note): array {
            $note = trim((string) $note);
            if ($note === '') {
                return ['summary' => null, 'details' => collect()];
            }

            $tokens = [
                ' Note to admin: ' => 'Client Note',
                ' Client note: ' => 'Client-facing Note',
                ' Internal note: ' => 'Internal Note',
                ' Adjournment reason: ' => 'Adjournment Reason',
                ' Hearing outcome: ' => 'Hearing Outcome',
            ];

            $matches = collect($tokens)
                ->map(function ($label, $token) use ($note) {
                    $position = strpos($note, $token);

                    return $position === false ? null : [
                        'token' => $token,
                        'label' => $label,
                        'position' => $position,
                    ];
                })
                ->filter()
                ->sortBy('position')
                ->values();

            if ($matches->isEmpty()) {
                return ['summary' => $note, 'details' => collect()];
            }

            $summary = trim(substr($note, 0, $matches->first()['position']));
            $details = $matches->map(function ($match, $index) use ($matches, $note) {
                $start = $match['position'] + strlen($match['token']);
                $end = $matches[$index + 1]['position'] ?? strlen($note);
                $value = trim(substr($note, $start, $end - $start));

                return $value === '' ? null : [
                    'label' => $match['label'],
                    'value' => $value,
                ];
            })->filter()->values();

            return [
                'summary' => $summary !== '' ? $summary : null,
                'details' => $details,
            ];
        };
        $formattedStatusHistories = $case->statusHistories
            ->sortByDesc('created_at')
            ->values()
            ->map(function ($history) use ($parseHistoryNote) {
                $parsedNote = $parseHistoryNote($history->note);

                return [
                    'status' => $history->new_status,
                    'actor' => $history->changed_by === 'client'
                        ? 'Client'
                        : ($history->changed_by === 'admin' ? 'Admin' : ucfirst((string) $history->changed_by)),
                    'timestamp' => $history->created_at,
                    'summary' => $parsedNote['summary'],
                    'details' => $parsedNote['details'],
                ];
            });
    @endphp

    <style>
        .admin-opp{max-width:1440px;margin:-12px auto 32px;padding:0 18px;color:#22324a;font-size:.95rem}.admin-shell{display:grid;margin-top:-18px;grid-template-columns:minmax(0,1fr) minmax(0,460px);gap:20px;align-items:start}.admin-main{min-width:0;margin-top:-36px}.admin-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:18px}.admin-head h1{font-size:1.6rem;line-height:1.2;margin:0;color:#102a4c;font-weight:900}.admin-sub{margin:.35rem 0 0;color:#607089;font-weight:700}.admin-badge{display:inline-flex;border-radius:999px;padding:7px 12px;background:#eaf1ff;color:#174ea6;font-size:.78rem;font-weight:900;white-space:nowrap}.admin-card{background:#fff;padding:0;border:1px solid #dfe8f4;border-radius:9px;box-shadow:0 12px 26px rgba(8,36,90,.06);margin-bottom:18px;overflow:hidden}.admin-card-head{background:#203e68;color:#fff;padding:18px 24px}.admin-card-head h2{font-size:1.2rem;margin:0;font-weight:900;color:#fff!important;line-height:1.25}.admin-card-body{padding:22px 24px}.admin-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.admin-field{padding:13px;border:1px solid #edf1f7;border-radius:7px;background:#fbfdff;min-width:0}.admin-field span{display:block;color:#66758b;font-size:.78rem;font-weight:900;text-transform:uppercase;margin-bottom:4px}.admin-field strong{display:block;color:#1d2b41;font-size:.95rem;line-height:1.45;overflow-wrap:anywhere}.admin-table{width:100%;border-collapse:collapse}.admin-table th{background:#f7f9fc;color:#202633;font-size:.78rem;text-transform:uppercase;letter-spacing:0;font-weight:900;text-align:left;padding:12px}.admin-table td{border-top:1px solid #e6ebf2;padding:12px;vertical-align:middle;color:#3f4b5d;font-size:.95rem}.admin-table td:nth-child(2),.admin-table th:nth-child(2){text-align:center}.admin-table td:last-child,.admin-table th:last-child{text-align:right}.admin-doc-type{font-weight:900;color:#202633}.admin-status-pill{display:inline-flex;align-items:center;justify-content:center;min-width:132px;white-space:nowrap;border-radius:999px;padding:7px 14px;background:#eaf8f0;color:#16814a;font-size:.78rem;font-weight:900}.admin-status-pill.is-muted{background:#f3f5f8;color:#66758b}.admin-link{color:#2a9d8f;font-weight:900;text-decoration:none}.admin-link:hover{color:#207d72}.admin-timeline{list-style:none;margin:0;padding:0}.admin-timeline li{border-bottom:1px solid #eef2f7;padding:12px 0}.admin-timeline li:first-child{padding-top:0}.admin-timeline li:last-child{border-bottom:0;padding-bottom:0}.admin-timeline strong{color:#203e68;font-size:.95rem}.admin-timeline small{display:block;color:#6b7890;margin-top:3px;font-size:.84rem}.admin-history-summary{margin:.45rem 0 0;color:#31435d;line-height:1.5;font-weight:650;white-space:pre-line}.admin-history-details{display:grid;gap:.45rem;margin-top:.7rem}.admin-history-detail{padding:.7rem .8rem;border:1px solid #e2eaf4;border-radius:8px;background:#fbfdff}.admin-history-detail span{display:block;margin-bottom:.2rem;color:#66758b;font-size:.75rem;font-weight:900;text-transform:uppercase}.admin-history-detail p{margin:0;color:#20324d;line-height:1.5;font-weight:700;white-space:pre-line}.stage-panel{position:sticky;top:16px;width:100%;min-width:0;box-sizing:border-box;background:#fff;border:1px solid #dfe8f4;border-radius:9px;box-shadow:0 16px 34px rgba(8,36,90,.1);overflow:hidden;overflow-x:hidden}.stage-panel-head{display:flex;align-items:center;justify-content:space-between;gap:10px;background:#203e68;color:#fff;padding:12px 16px}.stage-panel-head h2{font-size:1rem;margin:0;font-weight:900;color:#fff!important;line-height:1.2}.stage-note-btn{min-height:30px;border:1px solid rgba(255,255,255,.55);border-radius:7px;background:rgba(255,255,255,.12);color:#fff;font-size:.76rem;font-weight:900;padding:0 10px;display:inline-flex;align-items:center;gap:5px;white-space:nowrap}.stage-note-btn:hover{background:rgba(255,255,255,.22)}.stage-panel-body{width:100%;min-width:0;box-sizing:border-box;padding:14px}.stage-panel-body>form,.stage-panel-body>.third-party-section,.stage-panel-body>div:not(.stage-note){margin-top:0}.stage-note{border-left:4px solid #2a9d8f;background:#f2fbf8;border-radius:7px;padding:10px 11px;margin:0 0 12px;color:#203e68;line-height:1.4;font-size:.82rem;font-weight:700}.admin-input{width:100%;max-width:100%;min-height:42px;border:1px solid #d9e0ea;border-radius:7px;padding:9px 11px;color:#202633;background:#fff;font-size:.95rem;box-sizing:border-box}.admin-input:focus{outline:2px solid rgba(42,157,143,.18);border-color:#2a9d8f}.admin-label{display:block;color:#263c5c;font-size:.95rem;font-weight:900;margin:0 0 8px}.admin-btn{max-width:100%;min-height:42px;border:0;border-radius:7px;background:#2a9d8f;color:#fff;font-size:.95rem;font-weight:900;padding:0 16px;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:7px}.admin-btn:hover{color:#fff;background:#23867a}.admin-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.admin-checks{display:grid;grid-template-columns:1fr;gap:8px}.admin-checks label{border:1px solid #edf1f7;border-radius:7px;padding:9px 10px;font-size:.95rem;font-weight:800;background:#fbfdff}.admin-visible{display:block;margin-top:7px;color:#607089;font-size:.84rem}.admin-empty{margin:0;color:#66758b}.admin-current-file{font-size:.86rem;margin:.7rem 0 0}.mt-2{margin-top:.5rem}.mb-2{margin-bottom:.5rem}@media(max-width:1360px){.admin-shell{grid-template-columns:1fr}.admin-main{margin-top:0}.stage-panel{position:static}}@media(max-width:1100px){.admin-detail-grid{grid-template-columns:1fr}}@media(max-width:640px){.admin-opp{padding:0 12px}.admin-head{display:block}.admin-head h1{font-size:1.35rem}.admin-badge{margin-top:10px}.admin-card-body,.stage-panel-body{padding:16px}.admin-card-head,.stage-panel-head{padding:14px 16px}.admin-card-head h2,.stage-panel-head h2{font-size:1.05rem}.stage-note-btn{width:38px;padding:0;justify-content:center}.stage-note-btn span{display:none}.admin-table{display:block;overflow-x:auto;white-space:nowrap}}
        .admin-registry-steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;counter-reset:registry}.admin-registry-step{position:relative;border:1px solid #dfe8f4;border-radius:10px;background:#fbfdff;padding:15px 14px 14px 46px;color:#607089;font-weight:850;min-height:68px}.admin-registry-step::before{counter-increment:registry;content:counter(registry);position:absolute;left:14px;top:14px;width:24px;height:24px;border-radius:50%;display:grid;place-items:center;background:#e7edf6;color:#607089;font-size:.78rem;font-weight:900}.admin-registry-step.is-complete{border-color:#a8e6bf;background:#f0fbf5;color:#137747}.admin-registry-step.is-complete::before{background:#159447;color:#fff}.admin-registry-step.is-current{border-color:#8fb3ff;background:#f3f7ff;color:#174ea6;box-shadow:0 0 0 2px rgba(23,78,166,.08)}@media(max-width:900px){.admin-registry-steps{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:560px){.admin-registry-steps{grid-template-columns:1fr}}
    </style>
    <style>
        .legal-point-builder{display:grid;gap:12px}.legal-point-row{position:relative;border:1px solid #dfe8f4;border-radius:9px;background:#fbfdff;padding:13px}.legal-point-row-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}.legal-point-row-head strong{color:#203e68;font-weight:900}.legal-point-remove{border:0;background:#fee2e2;color:#991b1b;border-radius:7px;min-height:32px;padding:0 10px;font-weight:900}.legal-point-remove[hidden]{display:none}.legal-point-grid{display:grid;gap:10px}.legal-point-recommendation{border-top:1px solid #e4eaf2;margin-top:16px;padding-top:16px}.legal-point-visible{display:flex;gap:8px;align-items:center;color:#607089;font-weight:800;font-size:.88rem;line-height:1.35}.legal-point-visible input{margin-top:0}.admin-btn-outline{background:#fff;color:#203e68;border:1px solid #b9c6d8}.admin-btn-outline:hover{background:#eef3f8;color:#203e68}
        .admin-additional-docs{border-top:1px solid #e4eaf2;margin-top:18px;padding-top:18px}.admin-additional-docs h3{margin:0 0 6px;color:#203e68;font-size:1rem;font-weight:900}.admin-additional-docs p{margin:0 0 12px;color:#607089;font-size:.88rem;line-height:1.45;font-weight:650}
        .case-notes-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.case-note-group h3{margin:0 0 10px;color:#203e68;font-size:.95rem;font-weight:900}.case-note-list{display:grid;gap:10px}.case-note{border:1px solid #dfe8f4;border-radius:9px;background:#fbfdff;padding:12px}.case-note.is-client{border-color:#bfdbfe;background:#f7fbff}.case-note strong{display:block;color:#1d2b41;font-size:.94rem;font-weight:900}.case-note small{display:block;margin-top:3px;color:#66758b;font-size:.78rem;font-weight:800}.case-note p{margin:8px 0 0;color:#3f4b5d;line-height:1.45;font-weight:650;white-space:pre-line}.case-note-empty{margin:0;color:#66758b;font-weight:650}@media(max-width:760px){.case-notes-grid{grid-template-columns:1fr}}
        .admin-btn-secondary{background:#203e68}.admin-btn-secondary:hover{background:#183154;color:#fff}.admin-document-empty{margin:.25rem 0 0;color:#607089;font-size:.86rem;font-weight:650}.admin-document-row{padding:12px;border:1px solid #dfe8f4;border-radius:9px;background:#fbfdff;margin-bottom:10px}.admin-document-row-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}.admin-document-row-head strong{color:#203e68;font-weight:900}.admin-document-remove{border:1px solid #fecaca;background:#fff;color:#b91c1c;border-radius:7px;min-height:32px;padding:0 10px;font-weight:900}
        .admin-table{table-layout:fixed}.admin-table th:nth-child(1),.admin-table td:nth-child(1){width:18%}.admin-table th:nth-child(2),.admin-table td:nth-child(2){width:18%}.admin-table th:nth-child(3),.admin-table td:nth-child(3){width:auto;font-size:.86rem;line-height:1.35;overflow-wrap:anywhere;word-break:break-word}.admin-table th:last-child,.admin-table td:last-child{width:92px;white-space:nowrap}
        .admin-review-form{margin-top:16px;padding:14px;border:1px solid #dfe8f4;border-radius:9px;background:#fbfdff}.admin-review-list{display:grid;gap:8px;margin-bottom:12px}.admin-review-list strong{color:#203e68;font-weight:900}.admin-review-list label{display:flex;gap:8px;align-items:flex-start;margin:0;padding:8px 10px;border:1px solid #edf1f7;border-radius:7px;background:#fff;color:#26364f;font-weight:750;line-height:1.35}.admin-btn-danger{background:#ef4444}.admin-btn-danger:hover{background:#dc2626;color:#fff}
        [data-evidence-card] .admin-card-head{display:flex;align-items:center;justify-content:space-between;gap:12px}.admin-select-btn{min-height:34px;border:1px solid rgba(255,255,255,.55);border-radius:7px;background:rgba(255,255,255,.12);color:#fff;font-weight:900;padding:0 14px}.admin-select-btn:hover{background:rgba(255,255,255,.2)}.admin-doc-select{display:none;margin-right:10px;transform:scale(1.15)}[data-evidence-card].is-selecting .admin-doc-select{display:inline-block}.admin-doc-bulk-actions{display:none;align-items:center;gap:10px;justify-content:flex-end;margin-top:16px}.admin-doc-bulk-actions.is-visible{display:flex}.admin-review-modal{position:fixed;inset:0;z-index:3050;display:none;align-items:center;justify-content:center;background:rgba(15,35,60,.45);padding:18px}.admin-review-modal.is-visible{display:flex}.admin-review-dialog{width:min(520px,100%);border-radius:9px;background:#fff;box-shadow:0 24px 60px rgba(15,35,60,.28);overflow:hidden}.admin-review-dialog header{background:#203e68;color:#fff;padding:16px 20px}.admin-review-dialog header h3{margin:0;color:#fff!important;font-size:1.1rem;font-weight:900}.admin-review-dialog-body{padding:20px}.admin-review-dialog-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:14px}.admin-btn-light{background:#e5eaf2;color:#203e68}.admin-btn-light:hover{background:#d8e0eb;color:#203e68}
        .admin-status-pill.is-warning{background:#fff7ed;color:#b45309}.admin-status-pill.is-info{background:#e0f2fe;color:#0369a1}.admin-status-pill.is-reuploaded{background:#ffedd5;color:#c2410c}.admin-status-pill.is-danger{background:#fee2e2;color:#b91c1c}
        .third-party-section{width:100%;min-width:0;box-sizing:border-box;padding-top:10px;margin-top:10px;border-top:1px solid #e4eaf2}.third-party-section:first-child{padding-top:0;margin-top:0;border-top:0}.third-party-section h3{margin:0 0 8px;color:#203e68;font-size:1rem;font-weight:900}.third-party-status-card,.third-party-deadline-card{width:100%;min-width:0;box-sizing:border-box;border:1px solid #dfe8f4;border-radius:8px;background:#fbfdff;padding:11px}.third-party-deadline-card{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;margin-top:8px}.third-party-deadline-card span{display:block;color:#66758b;font-size:.78rem;font-weight:900;text-transform:uppercase}.third-party-deadline-card strong{display:block;margin-top:3px;color:#203e68}.third-party-file{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:8px;padding:10px;border:1px solid #dfe8f4;border-radius:7px;background:#fff}.stage-panel [hidden]{display:none!important}.stage-panel form>.admin-actions{margin-top:8px}.stage-panel form>.admin-actions+.third-party-section{margin-top:10px}.stage-panel .admin-additional-documents-header{margin:0 0 8px}.stage-panel .admin-document-empty{margin:0;line-height:1.35}.stage-panel .admin-document-row{padding:10px;margin-bottom:8px}.third-party-request-list{display:grid;gap:8px;width:100%;min-width:0}.third-party-request-row{width:100%;min-width:0;display:flex;gap:8px;align-items:center}.third-party-request-row .admin-input{min-width:0}.third-party-history{list-style:none;margin:10px 0 0;padding:0}.third-party-history li{padding:10px 0;border-top:1px solid #e4eaf2}.third-party-history strong{display:block;color:#203e68}.third-party-history small{display:block;color:#66758b;margin-top:3px}.third-party-history p{margin:5px 0 0;color:#3f4b5d;line-height:1.45}.admin-btn-danger{background:#dc2626}.admin-btn-danger:hover{background:#b91c1c;color:#fff}@media(max-width:640px){.third-party-deadline-card{grid-template-columns:1fr}.third-party-file,.third-party-request-row{align-items:stretch;flex-direction:column}}
        .evidence-request-panel,.evidence-attach-panel{padding-top:22px!important;margin-top:22px!important}.evidence-section-head{display:grid;grid-template-columns:54px minmax(0,1fr);gap:14px;align-items:center;margin-bottom:16px}.evidence-section-icon{width:54px;height:54px;border-radius:50%;display:grid;place-items:center;background:#eef5ff;color:#2563eb;font-size:1.45rem}.evidence-section-head h3{margin:0 0 6px!important;color:#183a68!important;font-size:1.25rem!important;line-height:1.25;font-weight:700!important}.evidence-section-head p{margin:0;color:#66758b;font-size:1rem;line-height:1.45;font-weight:500}.evidence-message-head{margin-top:24px;padding-top:22px;border-top:1px solid #e3eaf4}.evidence-request-panel .third-party-request-list{gap:14px}.evidence-request-panel .third-party-request-row{display:grid;grid-template-columns:34px 46px minmax(0,1fr) 132px;gap:10px;align-items:center}.request-row-grip{display:grid;place-items:center;color:#64748b;font-size:1.2rem}.request-row-icon{height:46px;border:1px solid #dce6f2;border-radius:10px;display:grid;place-items:center;background:#fff;color:#334155;font-size:1.2rem}.evidence-request-panel .admin-input{min-height:54px;border-radius:10px;font-size:1.05rem;font-weight:400;color:#1f2937}.evidence-request-panel textarea.admin-input{min-height:122px;padding:16px}.evidence-request-panel .admin-actions{margin-top:14px}.evidence-request-panel .admin-btn-outline{min-height:54px;border:1px dashed #7aa2f7;background:#fff;color:#2563eb;border-radius:10px;font-size:1rem;font-weight:650;padding:0 22px}.request-remove{min-height:54px;border-color:#fecaca!important;background:#fff!important;color:#dc2626!important;border-radius:10px!important;font-size:.98rem!important;font-weight:650!important}.request-remove i{font-size:1rem}.evidence-info-note{display:flex;align-items:center;gap:12px;margin-top:20px;padding:16px 18px;border:1px solid #bfdbfe;border-radius:10px;background:#eff6ff;color:#2563eb;font-size:.98rem;font-weight:500;line-height:1.45}.evidence-info-note i{font-size:1.2rem}.evidence-attach-panel .admin-additional-documents-header{display:grid!important;grid-template-columns:minmax(0,1fr) 170px;gap:14px;align-items:start;margin-bottom:18px!important}.evidence-attach-panel .admin-label{font-size:1.16rem;font-weight:700;color:#183a68}.evidence-attach-panel .admin-label span{font-size:.95rem;font-weight:500;color:#66758b}.evidence-attach-panel .admin-document-empty{font-size:.95rem;font-weight:500;color:#66758b;margin-top:6px!important}.evidence-attach-panel .admin-additional-documents-header:before{content:"\\F4B1";font-family:"bootstrap-icons";width:54px;height:54px;border-radius:50%;display:grid;place-items:center;background:#eef5ff;color:#2563eb;font-size:1.45rem;grid-row:1 / span 2}.evidence-attach-panel .admin-additional-documents-header>div{display:grid;grid-template-columns:54px minmax(0,1fr);column-gap:14px}.evidence-attach-panel .admin-additional-documents-header>div .admin-label,.evidence-attach-panel .admin-additional-documents-header>div .admin-document-empty{grid-column:2}.evidence-attach-panel .admin-btn-secondary{min-height:46px;background:#fff;color:#2563eb;border:1px solid #7aa2f7;border-radius:10px;font-size:.98rem;font-weight:650}.third-party-primary-action{justify-content:flex-end;margin-top:24px!important;padding-top:22px;border-top:1px solid #e3eaf4}.third-party-primary-action .admin-btn{min-width:210px;min-height:54px;border-radius:10px;font-size:1.05rem;font-weight:650}@media(max-width:640px){.evidence-section-head{grid-template-columns:46px minmax(0,1fr)}.evidence-section-icon{width:46px;height:46px}.evidence-request-panel .third-party-request-row{grid-template-columns:28px 42px minmax(0,1fr)}.request-remove{grid-column:3;width:max-content}.evidence-attach-panel .admin-additional-documents-header{grid-template-columns:1fr}.evidence-attach-panel .admin-btn-secondary{width:100%}.third-party-primary-action{justify-content:stretch}.third-party-primary-action .admin-btn{width:100%}}
        .evidence-attach-panel .admin-additional-documents-header{grid-template-columns:54px minmax(0,1fr) 170px!important}.evidence-attach-panel .admin-additional-documents-header:before{content:none!important}.evidence-attach-panel .admin-additional-documents-icon{width:54px;height:54px;border-radius:50%;display:grid;place-items:center;background:#eef5ff;color:#2563eb;font-size:1.45rem}.evidence-attach-panel .admin-additional-documents-header>div{display:block!important}.evidence-attach-panel .admin-additional-documents-header>div .admin-label,.evidence-attach-panel .admin-additional-documents-header>div .admin-document-empty{grid-column:auto!important}@media(max-width:640px){.evidence-attach-panel .admin-additional-documents-header{grid-template-columns:46px minmax(0,1fr)!important}.evidence-attach-panel .admin-additional-documents-icon{width:46px;height:46px}.evidence-attach-panel .admin-btn-secondary{grid-column:1 / -1}}
        .evidence-request-panel,.evidence-attach-panel{padding-top:14px!important;margin-top:14px!important}.evidence-section-head{display:block!important;margin-bottom:10px!important}.evidence-section-head h3{font-size:1.02rem!important;font-weight:650!important;margin:0!important}.evidence-section-head p,.evidence-section-icon,.request-row-grip,.request-row-icon,.admin-additional-documents-icon{display:none!important}.evidence-request-panel .third-party-request-list{gap:8px!important}.evidence-request-panel .third-party-request-row{display:grid!important;grid-template-columns:minmax(0,1fr) 92px!important;gap:8px!important}.evidence-request-panel .admin-input{min-height:42px!important;border-radius:8px!important;font-size:.9rem!important;padding:8px 10px!important}.request-remove{width:92px!important;min-width:92px!important;min-height:42px!important;border-radius:8px!important;font-size:.82rem!important;padding:0 8px!important;overflow:hidden!important}.evidence-request-panel .admin-btn-outline{min-height:40px!important;border-radius:8px!important;font-size:.86rem!important;font-weight:600!important;padding:0 14px!important}.evidence-message-head{margin-top:16px!important;padding-top:14px!important}.evidence-request-panel textarea.admin-input{min-height:96px!important;font-size:.9rem!important;padding:10px!important}.evidence-attach-panel .admin-additional-documents-header{grid-template-columns:minmax(0,1fr) auto!important;gap:10px!important;align-items:center!important}.evidence-attach-panel .admin-label{font-size:1.02rem!important;font-weight:650!important}.evidence-attach-panel .admin-label span{font-size:.82rem!important;font-weight:500!important}.evidence-attach-panel .admin-document-empty{font-size:.82rem!important}.evidence-attach-panel .admin-btn-secondary{min-height:40px!important;border-radius:8px!important;font-size:.86rem!important;font-weight:600!important;padding:0 12px!important}.evidence-attach-panel .admin-btn-secondary:before{content:none!important}.third-party-primary-action .admin-btn{min-height:46px!important;font-size:.94rem!important}
        .stage-panel .evidence-request-panel{border-top:0!important;margin-top:4px!important;padding-top:4px!important}.stage-panel .evidence-message-head{border-top:0!important;margin-top:12px!important;padding-top:0!important}.stage-panel .evidence-info-note{margin-top:10px!important;padding:9px 12px!important;border-radius:8px!important;font-size:.78rem!important;line-height:1.3!important;gap:8px!important}.stage-panel .evidence-info-note i{font-size:.95rem!important}.stage-panel .evidence-attach-panel{margin-top:10px!important;padding-top:10px!important}.stage-panel .evidence-attach-panel .admin-additional-documents-header{margin-bottom:6px!important}.stage-panel .evidence-attach-panel .admin-document-empty{display:none!important}.stage-panel .evidence-attach-panel [data-oppose-tracking-document-list]{margin-bottom:0!important}.stage-panel .third-party-primary-action{margin-top:12px!important;padding-top:12px!important}
        .stage-panel form[data-third-party-status-form]{display:grid!important;gap:0!important}.stage-panel form[data-third-party-status-form]>.third-party-section{margin-top:8px!important;padding-top:8px!important}.stage-panel form[data-third-party-status-form]>.third-party-section:first-of-type{margin-top:0!important;padding-top:0!important}.stage-panel form[data-third-party-status-form]>.third-party-section.evidence-request-panel{margin-top:0!important;padding-top:0!important}.stage-panel form[data-third-party-status-form] .third-party-deadline-card{margin-bottom:0!important}.stage-panel form[data-third-party-status-form] .evidence-section-head{margin-bottom:8px!important}.stage-panel form[data-third-party-status-form] .admin-actions{margin-top:8px!important}.stage-panel form[data-third-party-status-form] .evidence-info-note{margin:8px 0 0!important;padding:7px 10px!important;min-height:0!important}.stage-panel form[data-third-party-status-form]>.evidence-attach-panel{border-top:0!important;margin-top:6px!important;padding-top:6px!important}.stage-panel form[data-third-party-status-form]>.evidence-attach-panel .admin-additional-documents-header{margin-bottom:0!important}.stage-panel form[data-third-party-status-form] [data-oppose-tracking-document-list]{display:block!important;min-height:0!important;height:auto!important;line-height:0!important}.stage-panel form[data-third-party-status-form] [data-oppose-tracking-document-empty]{display:none!important}.stage-panel form[data-third-party-status-form]>.third-party-primary-action{border-top:0!important;margin-top:6px!important;padding-top:6px!important}
        .stage-panel form[data-third-party-status-form] .third-party-status-card{min-width:0!important;padding:10px 9px 8px!important}.stage-panel form[data-third-party-status-form] .third-party-status-card .admin-label{font-size:.78rem!important;margin-bottom:6px!important}.stage-panel form[data-third-party-status-form] .third-party-status-card .admin-input{min-width:0!important;max-width:100%!important;min-height:34px!important;padding:5px 32px 5px 8px!important;font-size:.78rem!important;text-overflow:ellipsis!important;white-space:nowrap!important;overflow:hidden!important}.stage-panel form[data-third-party-status-form] .third-party-status-card .admin-status-pill{display:inline-flex!important;min-width:0!important;max-width:100%!important;padding:4px 8px!important;font-size:.66rem!important;white-space:normal!important;overflow-wrap:anywhere!important;word-break:break-word!important;line-height:1.2!important;text-align:center!important}.stage-panel form[data-third-party-status-form] .third-party-status-card .mt-2{margin-top:.3rem!important}
        .stage-panel form[data-third-party-status-form]>.evidence-attach-panel{margin-top:0!important;padding-top:0!important}.stage-panel form[data-third-party-status-form] .admin-additional-documents-list-empty{display:none!important}.stage-panel form[data-third-party-status-form]>.evidence-attach-panel+.third-party-primary-action{margin-top:4px!important;padding-top:4px!important}.stage-panel form[data-third-party-status-form] .evidence-attach-panel .admin-additional-documents-header{margin:0!important}
        .stage-panel form[data-third-party-status-form] .compact-attach-header{display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;align-items:center!important;gap:6px!important;margin:0!important;padding:0!important}.stage-panel form[data-third-party-status-form] .compact-attach-header .admin-label{margin:0!important;font-size:.92rem!important;font-weight:650!important;line-height:1.2!important}.stage-panel form[data-third-party-status-form] .compact-attach-header .admin-label span{display:block!important;margin-top:2px!important;font-size:.72rem!important;font-weight:500!important;color:#66758b!important}.stage-panel form[data-third-party-status-form] .compact-attach-header .admin-btn{min-height:34px!important;padding:0 10px!important;border-radius:8px!important;font-size:.78rem!important;font-weight:600!important}.stage-panel form[data-third-party-status-form] .compact-attach-list{margin:5px 0 0!important;padding:0!important;line-height:normal!important}.stage-panel form[data-third-party-status-form] .compact-attach-list.is-empty{display:none!important;margin:0!important;height:0!important;overflow:hidden!important}.stage-panel form[data-third-party-status-form] .compact-attach-list [data-oppose-tracking-document-empty]{display:none!important}.stage-panel form[data-third-party-status-form] .evidence-info-note+.evidence-attach-panel{margin-top:6px!important}.stage-panel form[data-third-party-status-form] .evidence-attach-panel+.third-party-primary-action{margin-top:6px!important;padding-top:4px!important}
        .stage-panel form[data-third-party-status-form]>.third-party-section:first-of-type{padding-top:10px!important}.stage-panel form[data-third-party-status-form]>.third-party-section:first-of-type h3{margin-bottom:9px!important}.stage-panel form[data-third-party-status-form]>.third-party-section.evidence-request-panel{margin-top:-2px!important;padding-top:0!important}
        .stage-panel .third-party-section h3{font-size:.92rem!important;line-height:1.2!important}.stage-panel .third-party-deadline-card{padding:9px!important;gap:8px!important;grid-template-columns:minmax(0,1fr) auto!important}.stage-panel .third-party-deadline-card span{font-size:.7rem!important}.stage-panel .third-party-deadline-card strong{font-size:.82rem!important;line-height:1.25!important}.stage-panel .third-party-deadline-card>.admin-status-pill{min-width:0!important;max-width:128px!important;padding:5px 8px!important;font-size:.66rem!important;white-space:normal!important;overflow-wrap:anywhere!important;word-break:break-word!important;line-height:1.2!important;text-align:center!important;justify-self:end!important}.stage-panel .admin-label{font-size:.82rem!important;margin-bottom:6px!important}.stage-panel .admin-input{min-height:36px!important;padding:7px 9px!important;font-size:.84rem!important}.stage-panel textarea.admin-input{min-height:84px!important;padding:9px!important}.stage-panel .admin-actions{gap:8px!important;margin-top:10px!important}.stage-panel .admin-btn{min-height:36px!important;padding:0 12px!important;font-size:.82rem!important;border-radius:8px!important}.stage-panel .evidence-attach-panel .admin-btn-secondary{min-height:30px!important;border-radius:7px!important;padding:0 8px!important;font-size:.64rem!important;font-weight:800!important;line-height:1.05!important;white-space:normal!important;max-width:132px!important;justify-self:end!important}.stage-panel form:not([data-third-party-status-form])>.evidence-attach-panel{margin-top:20px!important;padding-top:20px!important}.stage-panel form:not([data-third-party-status-form])>.evidence-attach-panel .admin-additional-documents-header{margin-bottom:14px!important}.stage-panel form:not([data-third-party-status-form])>.evidence-attach-panel [data-oppose-tracking-document-list]{margin-top:12px!important;line-height:1.35!important}.stage-panel form:not([data-third-party-status-form])>.admin-draft-actions{margin-top:22px!important}.admin-draft-actions{position:relative!important;flex-wrap:nowrap!important;align-items:center!important;gap:8px!important;padding-bottom:16px}.admin-draft-actions .admin-btn{min-height:38px!important;border-radius:8px!important;padding:0 10px!important;font-size:.78rem!important;font-weight:850!important;line-height:1.1!important;white-space:nowrap!important}.admin-draft-actions .admin-draft-btn{flex:0 0 auto}.admin-draft-actions .admin-draft-btn~.admin-btn{flex:1 1 auto;min-width:0}.admin-draft-btn{border-style:solid!important;border-color:#cbd5e1!important;background:#fff!important;color:#203e68!important}.admin-draft-btn:hover{background:#f8fafc!important;color:#203e68!important}.admin-draft-status{position:absolute;left:0;bottom:0;color:#64748b;font-size:.68rem;font-weight:800;line-height:1}
    </style>
    <style>
        .admin-opp { margin-top: 0; }
        .admin-opp > .admin-shell { margin-top: 0; }
        .admin-opp > .admin-shell > .admin-main { margin-top: 0; }
        .admin-opp > .admin-shell > .stage-panel { top: 90px; }
    </style>

    <div class="admin-opp">
        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

        <div class="admin-head">
            <div>
                <h1>{{ $case->case_number }} · {{ $case->trademark_to_oppose }}</h1>
                <p class="admin-sub">Flow B: Oppose a Trademark</p>
            </div>
            <span class="admin-badge">{{ $case->current_admin_status }}</span>
        </div>

        <div class="admin-shell">
            <div class="admin-main">
                <section class="admin-card">
                    <header class="admin-card-head"><h2>Opposition Application</h2></header>
                    <div class="admin-card-body">
                        <div class="admin-detail-grid">
                            <div class="admin-field"><span>User / Business</span><strong>{{ $case->user_business_name }}</strong></div>
                            <div class="admin-field"><span>Email / Mobile</span><strong>{{ $case->email }} · {{ $case->mobile_number }}</strong></div>
                            <div class="admin-field"><span>Trademark Owned</span><strong>{{ $case->trademark_you_own }}</strong></div>
                            <div class="admin-field"><span>Trademark To Oppose</span><strong>{{ $case->trademark_to_oppose }}</strong></div>
                            <div class="admin-field"><span>Opposed Application</span><strong>{{ $case->opposed_application_number }} · Class {{ $case->trademark_class }}</strong></div>
                            <div class="admin-field"><span>Opposed Applicant</span><strong>{{ $case->opposed_applicant_name ?: 'Not provided' }}</strong></div>
                            <div class="admin-field"><span>Client Stage</span><strong>{{ $case->current_client_stage }}</strong></div>
                            <div class="admin-field"><span>Recommendation</span><strong>{{ $case->recommendation_level ?: 'Not completed' }}</strong></div>
                            @if($case->admin_internal_notes)
                                <div class="admin-field" style="grid-column:1 / -1"><span>Admin Internal Notes</span><strong>{{ $case->admin_internal_notes }}</strong></div>
                            @endif
                            <div class="admin-field" style="grid-column:1 / -1"><span>Conflict Reason</span><strong>{{ $case->conflict_reason }}</strong></div>
                        </div>
                    </div>
                </section>

                <section class="admin-card">
                    <header class="admin-card-head"><h2>Opposition Timeline</h2></header>
                    <div class="admin-card-body">
                        <div class="admin-registry-steps">
                            @foreach ($oppositionTimelineSteps as $timelineIndex => $timelineLabel)
                                @php
                                    $timelineState = $timelineIndex < $oppositionTimelineRank ? 'is-complete' : ($timelineIndex === $oppositionTimelineRank ? 'is-current' : '');
                                    if ($isMatterClosed && $timelineIndex === array_key_last($oppositionTimelineSteps)) {
                                        $timelineState = 'is-complete';
                                    }
                                @endphp
                                <div class="admin-registry-step {{ $timelineState }}">
                                    {{ $timelineLabel }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="admin-card" id="documents-evidence" data-evidence-card>
                    <header class="admin-card-head">
                        <h2>Documents &amp; Evidence</h2>
                        @if($selectableLatestClientEvidence->isNotEmpty())
                            <button class="admin-select-btn" type="button" data-evidence-select-toggle>Select</button>
                        @endif
                    </header>
                    <div class="admin-card-body">
                        <form method="POST" action="{{ route('admin.trademark-opposition.oppose.evidence.review', $case) }}" data-evidence-review-form>
                            @csrf
                            <input type="hidden" name="action" data-evidence-review-action>
                            <input type="hidden" name="note" data-evidence-review-note>
                            <table class="admin-table">
                                <thead><tr><th>Type</th><th>Status</th><th>File</th><th>Actions</th></tr></thead>
                                <tbody>
                                    @foreach($case->evidence->where('uploaded_by', 'admin')->sortByDesc('id') as $evidence)
                                        @php
                                            $adminEvidenceDisplayName = $evidence->review_note ?: $evidence->file_name;
                                        @endphp
                                        <tr>
                                            <td class="admin-doc-type">Admin Additional Document</td>
                                            <td>
                                                <span class="admin-status-pill is-info">Admin sent</span>
                                                @if($evidence->review_note)<small class="admin-visible">{{ $evidence->review_note }}</small>@endif
                                            </td>
                                            <td>{{ $adminEvidenceDisplayName }}</td>
                                            <td><a class="admin-link" href="{{ route('admin.trademark-opposition.document.view', [$case, 'evidence', $evidence->id]) }}" target="_blank">View</a></td>
                                        </tr>
                                    @endforeach
                                    @forelse($latestClientEvidence as $evidence)
                                        @php
                                            $statusClass = match($evidence->review_status) {
                                                'reviewed' => '',
                                                'rejected' => 'is-danger',
                                                'reuploaded' => 'is-reuploaded',
                                                default => 'is-warning',
                                            };
                                            $statusLabel = match($evidence->review_status) {
                                                'reviewed' => 'Reviewed',
                                                'rejected' => 'Reupload required',
                                                'reuploaded' => 'Reuploaded',
                                                default => 'Pending review',
                                            };
                                            $isThirdPartyEvidence = \Illuminate\Support\Str::startsWith($evidence->evidence_type, 'third_party_requested_evidence_');
                                            $evidenceLabel = $isThirdPartyEvidence
                                                ? \Illuminate\Support\Str::headline(\Illuminate\Support\Str::after($evidence->evidence_type, 'third_party_requested_evidence_'))
                                                : ($evidenceTypes[$evidence->evidence_type] ?? \Illuminate\Support\Str::headline(str_replace('_', ' ', $evidence->evidence_type)));
                                            $fileDisplayName = $isThirdPartyEvidence ? $evidenceLabel : $evidence->file_name;
                                            $isSelectableEvidence = in_array($evidence->review_status, $selectableEvidenceStatuses, true);
                                        @endphp
                                        <tr>
                                            <td class="admin-doc-type">
                                                @if($isSelectableEvidence)
                                                    <input class="admin-doc-select" type="checkbox" name="evidence_ids[]" value="{{ $evidence->id }}" data-evidence-checkbox>
                                                @endif
                                                {{ $evidenceLabel }}
                                            </td>
                                            <td><span class="admin-status-pill {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                            <td>{{ $fileDisplayName }}</td>
                                            <td><a class="admin-link" href="{{ route('admin.trademark-opposition.document.view', [$case, 'evidence', $evidence->id]) }}" target="_blank">View</a></td>
                                        </tr>
                                    @empty
                                        @if($case->evidence->where('uploaded_by', 'admin')->isEmpty())
                                            <tr><td colspan="4">No evidence uploaded yet.</td></tr>
                                        @endif
                                    @endforelse
                                </tbody>
                            </table>
                            @if($selectableLatestClientEvidence->isNotEmpty())
                                <div class="admin-doc-bulk-actions" data-evidence-bulk-actions>
                                    <button class="admin-btn" type="button" data-evidence-open-modal="reviewed"><i class="bi bi-check-circle"></i> Mark as Reviewed</button>
                                    <button class="admin-btn admin-btn-danger" type="button" data-evidence-open-modal="rejected"><i class="bi bi-arrow-repeat"></i> Ask for Reupload</button>
                                </div>
                            @endif
                        </form>
                    </div>
                </section>

                <section class="admin-card">
                    <header class="admin-card-head"><h2>Case Notes</h2></header>
                    <div class="admin-card-body">
                        <div class="case-notes-grid">
                            <div class="case-note-group">
                                <h3>Admin Notes</h3>
                                @if($adminNoteEntries->isNotEmpty())
                                    <div class="case-note-list">
                                        @foreach($adminNoteEntries as $noteEntry)
                                            <article class="case-note">
                                                <strong>{{ $noteEntry['title'] }}</strong>
                                                <small>{{ $noteEntry['meta'] }}</small>
                                                <p>{{ $noteEntry['body'] }}</p>
                                            </article>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="case-note-empty">No admin notes saved yet.</p>
                                @endif
                            </div>
                            <div class="case-note-group">
                                <h3>Client Notes</h3>
                                @if($clientNoteEntries->isNotEmpty())
                                    <div class="case-note-list">
                                        @foreach($clientNoteEntries as $noteEntry)
                                            <article class="case-note is-client">
                                                <strong>{{ $noteEntry['title'] }}</strong>
                                                <small>{{ $noteEntry['meta'] }}</small>
                                                <p>{{ $noteEntry['body'] }}</p>
                                            </article>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="case-note-empty">No client notes submitted yet.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </section>

                <section class="admin-card">
                    <header class="admin-card-head"><h2>Status History</h2></header>
                    <div class="admin-card-body">
                        <ul class="admin-timeline">
                            @forelse ($formattedStatusHistories as $history)
                                <li>
                                    <strong>{{ $history['status'] }}</strong>
                                    <small>{{ $formatDateTime($history['timestamp']) }} · {{ $history['actor'] }}</small>
                                    @if($history['summary'])
                                        <p class="admin-history-summary">{{ $history['summary'] }}</p>
                                    @endif
                                    @if($history['details']->isNotEmpty())
                                        <div class="admin-history-details">
                                            @foreach($history['details'] as $detail)
                                                <div class="admin-history-detail">
                                                    <span>{{ $detail['label'] }}</span>
                                                    <p>{{ $detail['value'] }}</p>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </li>
                            @empty
                                <li><small>No status history recorded yet.</small></li>
                            @endforelse
                        </ul>
                    </div>
                </section>
            </div>

            <aside class="stage-panel">
                <header class="stage-panel-head">
                    <h2>Stage Actions</h2>
                    @unless($activeAction === 'third-party')
                        <button class="stage-note-btn" type="button" data-internal-note-open>
                            <i class="bi bi-journal-text"></i>
                            <span>Internal Note</span>
                        </button>
                    @endunless
                </header>
                <div class="stage-panel-body">
                    @if ($activeAction === 'review')
                        <div class="stage-note">Legal Review Underway. Select legal points, add internal admin notes, publish the recommendation, and set the client payment amount.</div>
                        <form method="POST" action="{{ route('admin.trademark-opposition.oppose.review', $case) }}" data-legal-review-form>
                            @csrf
                            <div class="legal-point-builder" data-legal-point-list>
                                @foreach ($legalReviewRows as $rowIndex => $row)
                                    <div class="legal-point-row" data-legal-point-row>
                                        <div class="legal-point-row-head">
                                            <strong>Legal Point</strong>
                                            <button class="legal-point-remove" type="button" data-remove-legal-point @if(count($legalReviewRows) === 1) hidden @endif>Remove</button>
                                        </div>
                                        <div class="legal-point-grid">
                                            <div>
                                                <label class="admin-label">Select Legal Point</label>
                                                <select class="admin-input" name="legal_points[{{ $rowIndex }}][review_point]" required>
                                                    <option value="">Select legal point</option>
                                                    @foreach ($reviewPoints as $point)
                                                        <option value="{{ $point }}" @selected(($row['review_point'] ?? '') === $point)>{{ $point }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="admin-label">Admin Internal Note</label>
                                                <textarea class="admin-input" name="legal_points[{{ $rowIndex }}][admin_note]" rows="3" placeholder="Write admin internal note">{{ $row['admin_note'] ?? '' }}</textarea>
                                                <label class="legal-point-visible mt-2">
                                                    <input type="checkbox" name="legal_points[{{ $rowIndex }}][is_client_visible]" value="1" @checked(!empty($row['is_client_visible']))>
                                                    <span>Visible to Client <small>(legal point only; internal note stays private)</small></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="admin-actions">
                                <button class="admin-btn admin-btn-outline" type="button" data-add-legal-point>+ Add Legal Point</button>
                            </div>
                            <div class="legal-point-recommendation">
                                <label class="admin-label">Recommendation</label>
                                <select class="admin-input mb-2" name="recommendation_level" required>
                                    <option value="">Select recommendation</option>
                                    @foreach ($recommendations as $level => $copy)
                                        <option value="{{ $level }}" @selected(old('recommendation_level', $case->recommendation_level) === $level)>{{ $level }} - {{ $copy }}</option>
                                    @endforeach
                                </select>
                                <textarea class="admin-input mb-2" name="recommendation_note" rows="4" placeholder="Recommendation note" required>{{ old('recommendation_note', $case->recommendation_note) }}</textarea>
                                <label class="legal-point-visible">
                                    <input type="checkbox" name="recommendation_note_visible" value="1" @checked(old('recommendation_note_visible', $case->recommendation_note_visible) == '1')>
                                    <span>Show note to client</span>
                                </label>
                            </div>
                            <div class="legal-point-recommendation">
                                <label class="admin-label">Pricing</label>
                                <input class="admin-input mb-2" name="package_name" placeholder="Package Name" value="{{ old('package_name', $case->package_name ?: 'Notice of Opposition Filing Package') }}" required>
                                <input class="admin-input mb-2" type="number" min="1" step="0.01" name="package_price" placeholder="Package Price" value="{{ old('package_price', $case->package_price) }}" required>
                                <textarea class="admin-input" name="included_services" rows="4" placeholder="Included services, one per line" required>{{ old('included_services', implode("\n", $case->included_services ?? ['Legal Analysis','Notice of Opposition Drafting','Notice Filing','Case Monitoring'])) }}</textarea>
                            </div>
                            <div class="admin-actions">
                                <button class="admin-btn" type="submit">Save Review &amp; Pricing</button>
                            </div>
                        </form>
                    @elseif ($activeAction === 'evidence-review')
                        <div class="stage-note">Evidence has been submitted. Review the files in the Documents &amp; Evidence section and mark every latest document as reviewed before legal review can begin.</div>
                        <div class="admin-actions">
                            <button class="admin-btn" type="button" data-scroll-to-evidence>
                                <i class="bi bi-folder2-open"></i> View Evidences
                            </button>
                        </div>
                    @elseif ($activeAction === 'request-evidence')
                        <div class="stage-note">Ask the applicant for additional material needed to support the Notice of Opposition.</div>
                        <form method="POST" action="{{ route('admin.trademark-opposition.oppose.request-evidence', $case) }}" enctype="multipart/form-data" data-evidence-request-form>
                            @csrf
                            <label class="admin-label">Request Note</label>
                            <textarea class="admin-input" name="note" rows="4" required placeholder="Tell client what evidence is required">{{ old('note') }}</textarea>
                            @error('note')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror

                            @include('admin.trademark-opposition.partials.additional-documents', [
                                'case' => $case,
                                'documents' => collect(),
                                'addButtonAttribute' => 'data-add-evidence-request-document',
                                'listAttribute' => 'data-evidence-request-document-list',
                                'templateAttribute' => 'data-evidence-request-document-template',
                                'rowAttribute' => 'data-evidence-request-document-row',
                                'removeAttribute' => 'data-remove-evidence-request-document',
                                'emptyAttribute' => 'data-evidence-request-document-empty',
                            ])
                            @error('optional_documents')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            @error('optional_documents.*')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            <div class="admin-actions"><button class="admin-btn" type="submit">Request Evidence &amp; Notify Client</button></div>
                        </form>
                    @elseif ($activeAction === 'recommendation')
                        <div class="stage-note">Publish the internal recommendation after legal review is complete.</div>
                        <form method="POST" action="{{ route('admin.trademark-opposition.oppose.recommendation', $case) }}">
                            @csrf
                            <label class="admin-label">Recommendation</label>
                            <select class="admin-input mb-2" name="recommendation_level" required>
                                <option value="">Select recommendation</option>
                                @foreach ($recommendations as $level => $copy)
                                    <option value="{{ $level }}" @selected($case->recommendation_level === $level)>{{ $level }} - {{ $copy }}</option>
                                @endforeach
                            </select>
                            <textarea class="admin-input mb-2" name="recommendation_note" rows="3" placeholder="Recommendation note">{{ old('recommendation_note', $case->recommendation_note) }}</textarea>
                            <label class="admin-visible"><input type="checkbox" name="recommendation_note_visible" value="1" @checked($case->recommendation_note_visible)> Show note to client</label>
                            <div class="admin-actions"><button class="admin-btn" type="submit">Save Recommendation</button></div>
                        </form>
                    @elseif ($activeAction === 'pricing')
                        <div class="stage-note">Assign pricing and included services for the Notice of Opposition filing package.</div>
                        <form method="POST" action="{{ route('admin.trademark-opposition.oppose.pricing', $case) }}">
                            @csrf
                            <input class="admin-input mb-2" name="package_name" placeholder="Package Name" value="{{ old('package_name', $case->package_name ?: 'Notice of Opposition Filing Package') }}" required>
                            <input class="admin-input mb-2" type="number" min="1" step="0.01" name="package_price" placeholder="Price" value="{{ old('package_price', $case->package_price) }}" required>
                            <input class="admin-input mb-2" type="number" min="1" step="0.01" name="total_amount" placeholder="Total Amount" value="{{ old('total_amount', $case->total_amount ?: $case->package_price) }}" required>
                            <textarea class="admin-input mb-2" name="included_services" rows="4" placeholder="Included services, one per line">{{ old('included_services', implode("\n", $case->included_services ?? ['Legal Analysis','Notice of Opposition Drafting','Notice Filing','Case Monitoring'])) }}</textarea>
                            <textarea class="admin-input" name="add_ons" rows="3" placeholder="Add-ons, one per line">{{ old('add_ons', implode("\n", $case->add_ons ?? [])) }}</textarea>
                            <div class="admin-actions"><button class="admin-btn" type="submit">Assign Pricing</button></div>
                        </form>
                    @elseif ($activeAction === 'await-payment')
                        <div class="stage-note">Pricing has been published. Waiting for the client to accept the disclaimer and complete payment.</div>
                    @elseif ($activeAction === 'draft')
                        <div class="stage-note">Upload the Notice of Opposition draft and notify the client for approval.</div>
                        <form method="POST" action="{{ route('admin.trademark-opposition.oppose.draft', $case) }}" enctype="multipart/form-data">
                            @csrf
                            <label class="admin-label">Notice Draft</label>
                            <input class="admin-input" type="file" name="draft" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                            @if ($case->draft_path)<p class="admin-current-file"><a class="admin-link" href="{{ route('admin.trademark-opposition.file.view', [$case, 'draft']) }}" target="_blank">Current draft: {{ $case->draft_display_name }}</a></p>@endif
                            @if ($case->client_approval_status)<p class="text-muted mb-0">Client approval: {{ str_replace('_', ' ', $case->client_approval_status) }}</p>@endif
                            @if ($case->client_change_request)<div class="alert alert-warning mt-2">{{ $case->client_change_request }}</div>@endif
                            <label class="admin-label mt-2">Admin Internal Note</label>
                            <textarea class="admin-input mb-2" name="admin_internal_note" rows="3" placeholder="Internal drafting note visible only to admin">{{ old('admin_internal_note', $case->admin_internal_notes) }}</textarea>
                            <label class="admin-label">Client Note</label>
                            <textarea class="admin-input" name="client_note" rows="3" placeholder="Optional note included in the draft-ready email"></textarea>
                            @include('admin.trademark-opposition.partials.additional-documents', [
                                'case' => $case,
                                'documents' => collect(),
                                'addButtonAttribute' => 'data-add-oppose-draft-document',
                                'listAttribute' => 'data-oppose-draft-document-list',
                                'templateAttribute' => 'data-oppose-draft-document-template',
                                'rowAttribute' => 'data-oppose-draft-document-row',
                                'removeAttribute' => 'data-remove-oppose-draft-document',
                                'emptyAttribute' => 'data-oppose-draft-document-empty',
                            ])
                            @error('optional_documents')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            @error('optional_documents.*')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            <div class="admin-actions"><button class="admin-btn" type="submit">Upload Draft &amp; Notify Client</button></div>
                        </form>
                    @elseif ($activeAction === 'filing')
                        <div class="stage-note">Upload the filing acknowledgment after the Notice of Opposition is filed.</div>
                        <form method="POST" action="{{ route('admin.trademark-opposition.oppose.file', $case) }}" enctype="multipart/form-data">
                            @csrf
                            <label class="admin-label">Filing Acknowledgment</label>
                            <input class="admin-input" type="file" name="filing_acknowledgment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                            @if ($case->filing_acknowledgment_path)<p class="admin-current-file"><a class="admin-link" href="{{ route('admin.trademark-opposition.file.view', [$case, 'filing-acknowledgment']) }}" target="_blank">Current acknowledgment</a></p>@endif
                            @include('admin.trademark-opposition.partials.additional-documents', [
                                'case' => $case,
                                'documents' => collect(),
                                'addButtonAttribute' => 'data-add-oppose-filing-document',
                                'listAttribute' => 'data-oppose-filing-document-list',
                                'templateAttribute' => 'data-oppose-filing-document-template',
                                'rowAttribute' => 'data-oppose-filing-document-row',
                                'removeAttribute' => 'data-remove-oppose-filing-document',
                                'emptyAttribute' => 'data-oppose-filing-document-empty',
                            ])
                            @error('optional_documents')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            @error('optional_documents.*')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            <div class="admin-actions"><button class="admin-btn" type="submit">Mark Notice Filed</button></div>
                        </form>
                    @elseif ($activeAction === 'third-party')
                        <div class="stage-note">Monitor the applicant's Counter Statement and request any supporting evidence from the client.</div>

                        <form method="POST" action="{{ route('admin.trademark-opposition.oppose.third-party-action', $case) }}" enctype="multipart/form-data" data-third-party-status-form>
                            @csrf
                            <section class="third-party-section">
                                <h3>Counter Statement Status</h3>
                                <div class="third-party-status-card">
                                    <label class="admin-label">Status</label>
                                    <select class="admin-input" name="third_party_status" required data-third-party-status>
                                        @foreach($thirdPartyStatusOptions as $value => $label)
                                            <option value="{{ $value }}" @selected($thirdPartyStatus === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <div class="mt-2">
                                        <span class="admin-status-pill {{ $thirdPartyStatus === $workflow::THIRD_PARTY_DEADLINE_EXPIRED ? 'is-warning' : 'is-info' }}" data-third-party-badge>
                                            {{ $thirdPartyStatusOptions[$thirdPartyStatus] ?? 'Awaiting Response' }}
                                        </span>
                                    </div>
                                </div>

                                <div class="third-party-deadline-card">
                                    <div>
                                        <span>Counter Statement Due Date</span>
                                        <strong>{{ $counterStatementDeadline?->format('d M Y') ?? 'Not available' }}</strong>
                                    </div>
                                    <span class="admin-status-pill {{ $deadlineTone }}">
                                        @if($counterStatementDaysRemaining === null)
                                            Date pending
                                        @elseif($counterStatementDaysRemaining < 0)
                                            Deadline expired
                                        @elseif($counterStatementDaysRemaining === 0)
                                            Due today
                                        @else
                                            {{ $counterStatementDaysRemaining }} days remaining
                                        @endif
                                    </span>
                                </div>
                            </section>

                            <section class="third-party-section evidence-request-panel">
                                <div class="evidence-section-head">
                                    <div>
                                        <h3>Additional Evidence Required</h3>
                                    </div>
                                </div>
                                <div class="third-party-request-list" data-third-party-request-list>
                                    @php
                                        $requestItems = old('evidence_items', $case->third_party_evidence_requests ?: ['']);
                                    @endphp
                                    @foreach($requestItems as $requestItem)
                                        <div class="third-party-request-row">
                                            <input class="admin-input" name="evidence_items[]" value="{{ $requestItem }}" placeholder="e.g. Sales invoices">
                                            <button class="admin-document-remove request-remove" type="button" data-remove-third-party-request><i class="bi bi-trash"></i> Remove</button>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="admin-actions">
                                    <button class="admin-btn admin-btn-outline" type="button" data-add-third-party-request>+ Add Requested Document</button>
                                </div>
                                <div class="evidence-section-head evidence-message-head">
                                    <div>
                                        <h3>Message To Client</h3>
                                    </div>
                                </div>
                                <textarea class="admin-input" name="message_to_client" rows="4" placeholder="Explain what is needed and why.">{{ old('message_to_client', $case->third_party_evidence_message) }}</textarea>
                                <div class="evidence-info-note"><i class="bi bi-info-circle"></i> Be specific so the client can upload the correct documents quickly.</div>
                            </section>

                            <div class="evidence-attach-panel">
                                @php
                                    $trackingDocuments = collect();
                                @endphp
                                <div class="compact-attach-header">
                                    <label class="admin-label mb-0">Attach Additional Documents <span>(Optional)</span></label>
                                    <button class="admin-btn admin-btn-secondary" type="button" data-add-oppose-tracking-document>Attach Files</button>
                                </div>
                                <div class="compact-attach-list {{ $trackingDocuments->isEmpty() ? 'is-empty' : '' }}" data-oppose-tracking-document-list data-empty-text="">
                                    @foreach($trackingDocuments as $additionalDocument)
                                        <div class="admin-document-row is-existing" data-oppose-tracking-document-row>
                                            <div class="admin-document-row-head">
                                                <strong>{{ $additionalDocument->review_note ?: $additionalDocument->file_name }}</strong>
                                                <div class="admin-document-actions">
                                                    <a class="admin-link" href="{{ route('admin.trademark-opposition.document.view', [$case, 'evidence', $additionalDocument->id]) }}" target="_blank">View</a>
                                                    <button
                                                        class="admin-document-remove"
                                                        type="button"
                                                        data-additional-document-delete
                                                        data-delete-action="{{ route('admin.trademark-opposition.additional-document.destroy', [$case, $additionalDocument]) }}"
                                                        data-delete-token="{{ csrf_token() }}"
                                                    >Remove</button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <template data-oppose-tracking-document-template>
                                    <div class="admin-document-row is-new" data-oppose-tracking-document-row>
                                        <div class="admin-document-row-head">
                                            <strong>Additional Document</strong>
                                            <button class="admin-document-remove" type="button" data-remove-oppose-tracking-document>Remove</button>
                                        </div>
                                        <input class="admin-input mb-2" type="text" name="optional_document_names[]" maxlength="255" placeholder="Document name (optional)">
                                        <input class="admin-input" type="file" name="optional_documents[]" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                                    </div>
                                </template>
                                @error('optional_documents')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                                @error('optional_documents.*')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            </div>

                            <div class="admin-actions third-party-primary-action">
                                <button class="admin-btn {{ $thirdPartyActionClass }}" type="submit" name="action" value="{{ $thirdPartyActionValue }}" data-third-party-status-action>{{ $thirdPartyActionLabel }}</button>
                            </div>
                        </form>
                    @elseif ($activeAction === 'evidence-file')
                        <div class="stage-note">All requested Evidence Stage documents have been reviewed. Attach any filing proof or supporting documents, then move the matter to Hearing Stage.</div>
                        <form method="POST" action="{{ route('admin.trademark-opposition.oppose.evidence-filed', $case) }}" enctype="multipart/form-data">
                            @csrf
                            <label class="admin-label">Evidence Filing Note <span class="admin-visible">Optional</span></label>
                            <textarea class="admin-input" name="note" rows="3" placeholder="Optional note about the evidence filed before the Trademark Registry.">{{ old('note') }}</textarea>
                            @include('admin.trademark-opposition.partials.additional-documents', [
                                'case' => $case,
                                'documents' => collect(),
                                'addButtonAttribute' => 'data-add-oppose-tracking-document',
                                'listAttribute' => 'data-oppose-tracking-document-list',
                                'templateAttribute' => 'data-oppose-tracking-document-template',
                                'rowAttribute' => 'data-oppose-tracking-document-row',
                                'removeAttribute' => 'data-remove-oppose-tracking-document',
                                'emptyAttribute' => 'data-oppose-tracking-document-empty',
                            ])
                            @error('optional_documents')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            @error('optional_documents.*')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            <div class="admin-actions">
                                <button class="admin-btn admin-btn-secondary" type="submit">Evidence Filed &amp; Move to Hearing Stage</button>
                            </div>
                        </form>
                    @elseif ($activeAction === 'hearing')
                        <div class="stage-note">Update hearing status and Registry notice details. Hearing notes are admin-only.</div>
                        <form method="POST" action="{{ route('admin.trademark-opposition.oppose.tracking', $case) }}" enctype="multipart/form-data" data-hearing-stage-form>
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
                                <textarea class="admin-input mb-2" name="adjournment_reason" rows="3" placeholder="Briefly mention why the hearing was adjourned.">{{ old('adjournment_reason') }}</textarea>
                                @error('adjournment_reason')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            </div>

                            <div class="admin-hearing-field" data-hearing-notes-field hidden>
                                <label class="admin-label">Hearing Notes <span class="admin-visible">Admin only</span></label>
                                <textarea class="admin-input mb-2" name="note" rows="4" placeholder="Hearing arguments prepared. Adjourned to next hearing. Registry requested additional clarification.">{{ old('note') }}</textarea>
                                @error('note')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            </div>

                            <div class="admin-hearing-field" data-hearing-outcome-field hidden>
                                <label class="admin-label">Hearing Outcome</label>
                                <select class="admin-input mb-2" name="hearing_outcome" data-hearing-outcome>
                                    <option value="">Select hearing outcome</option>
                                    @foreach(['Further Hearing Required', 'Matter Concluded'] as $outcome)
                                        <option value="{{ $outcome }}" @selected(old('hearing_outcome', $case->final_outcome) === $outcome)>{{ $outcome }}</option>
                                    @endforeach
                                </select>
                                @error('hearing_outcome')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            </div>

                            <div class="admin-actions">
                                <button class="admin-btn admin-btn-secondary" type="submit" data-hearing-submit>Save</button>
                            </div>
                        </form>
                    @elseif ($activeAction === 'decision')
                        <div class="stage-note">Record the final outcome and close the matter. Once submitted, the matter will move to the closed stage.</div>
                        <form method="POST" action="{{ route('admin.trademark-opposition.oppose.tracking', $case) }}" enctype="multipart/form-data" data-decision-stage-form>
                            @csrf
                            <input type="hidden" name="status" value="{{ $workflow::ADMIN_DECISION_AWAITED }}">

                            <label class="admin-label">Final Outcome</label>
                            <select class="admin-input mb-2" name="decision_status" data-decision-status required>
                                <option value="">Select Outcome</option>
                                @foreach($decisionOutcomeOptions as $decisionStatus)
                                    <option value="{{ $decisionStatus }}" @selected($selectedDecisionStatus === $decisionStatus)>{{ $decisionStatus }}</option>
                                @endforeach
                            </select>
                            @error('decision_status')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror

                            <div data-withdrawn-by-field hidden>
                                <label class="admin-label">Withdrawn By</label>
                                <select class="admin-input mb-2" name="withdrawn_by" data-withdrawn-by>
                                    <option value="">Select who withdrew</option>
                                    @foreach($withdrawnByOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($selectedWithdrawnBy === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('withdrawn_by')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            </div>

                            <div class="admin-field mb-2" data-decision-preview>
                                <span>System Result Preview</span>
                                <strong data-preview-oppose-status>{{ $decisionPreview ? 'Oppose Case Status: ' . $decisionPreview['oppose_case_status_label'] : 'Select final outcome' }}</strong>
                                <small class="d-block mt-1" data-preview-defence-status>Defence Case Status: {{ $decisionPreview['defence_case_status_label'] ?? 'Pending selection' }}</small>
                                <small class="d-block mt-1" data-preview-filing-result>Trademark Filing Result: {{ $decisionPreview['trademark_filing_result_label'] ?? 'Pending selection' }}</small>
                            </div>

                            <div data-decision-final-fields hidden>
                                <label class="admin-label">Final Order Date</label>
                                <input class="admin-input mb-2" type="date" name="decision_date" value="{{ old('decision_date') }}" data-decision-date>
                                @error('decision_date')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror

                                <label class="admin-label">Upload Final Order <span class="admin-visible">Registry decision, settlement, withdrawal, or other closing document (PDF)</span></label>
                                <input class="admin-input mb-2" type="file" name="decision_order" accept=".pdf" data-decision-order>
                                @error('decision_order')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            </div>

                            <label class="admin-label">Final Notes</label>
                            <textarea class="admin-input" name="decision_note" rows="4" placeholder="Add client-facing context for the final outcome.">{{ old('decision_note') }}</textarea>
                            @error('decision_note')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror

                            @include('admin.trademark-opposition.partials.additional-documents', [
                                'case' => $case,
                                'documents' => collect(),
                                'addButtonAttribute' => 'data-add-decision-document',
                                'listAttribute' => 'data-decision-document-list',
                                'templateAttribute' => 'data-decision-document-template',
                                'rowAttribute' => 'data-decision-document-row',
                                'removeAttribute' => 'data-remove-decision-document',
                                'emptyAttribute' => 'data-decision-document-empty',
                                'label' => 'Additional Documents',
                                'labelSuffix' => '(optional)',
                                'description' => 'Attach any extra client-facing documents related to closing the matter.',
                                'addButtonText' => 'Attach Document',
                            ])
                            @error('optional_documents')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            @error('optional_documents.*')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror

                            <div class="admin-actions">
                                <button class="admin-btn admin-btn-danger" type="submit" data-decision-submit>Close Matter</button>
                            </div>
                        </form>
                    @elseif ($activeAction === 'closed')
                        <div class="stage-note">This matter is closed. Final outcome details are shown below for admin reference.</div>
                        <div class="admin-field">
                            <span>Final Outcome</span>
                            <strong>{{ $finalOutcomeLabel ?: 'Matter Closed' }}</strong>
                        </div>
                        @if($latestMatterClosedHistory?->created_at)
                            <div class="admin-field mt-2">
                                <span>Closed On</span>
                                <strong>{{ $formatDateTime($latestMatterClosedHistory->created_at) }}</strong>
                            </div>
                        @endif
                        @if(filled($latestMatterClosedHistory?->note))
                            <div class="admin-field mt-2">
                                <span>Final Notes</span>
                                <strong>{{ $latestMatterClosedHistory->note }}</strong>
                            </div>
                        @endif
                        @if($matterClosedAdminDocuments->isNotEmpty())
                            <div class="third-party-section">
                                <h3>Final Documents</h3>
                                @foreach($matterClosedAdminDocuments as $document)
                                    @php
                                        $documentUrl = route('trademark-opposition.oppose.document.view', [$case, 'evidence', $document->id]);
                                    @endphp
                                    <div class="third-party-file">
                                        <div>
                                            <strong>{{ $document->review_note ?: $document->file_name }}</strong>
                                            <small>{{ strtoupper($document->file_type ?: 'FILE') }} · Sent {{ $formatDateTime($document->created_at) }}</small>
                                        </div>
                                        <a class="admin-link" href="{{ $documentUrl }}" target="_blank">View</a>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @elseif (in_array($currentStatus, [
                        $workflow::ADMIN_EVIDENCE_BY_OPPONENT,
                        $workflow::ADMIN_EVIDENCE_BY_APPLICANT,
                        $workflow::ADMIN_EVIDENCE_IN_REPLY,
                    ], true))
                        <div class="stage-note">Use the dedicated Evidence section below to review uploaded files and request any additional evidence from the client. The generic tracking form is not used during the evidence stages.</div>
                        <div class="admin-actions">
                            <button class="admin-btn" type="button" data-scroll-to-evidence>
                                <i class="bi bi-folder2-open"></i> View Evidences
                            </button>
                        </div>
                    @else
                        <div class="stage-note">Update post-filing monitoring as Registry events happen.</div>
                        <form method="POST" action="{{ route('admin.trademark-opposition.oppose.tracking', $case) }}" enctype="multipart/form-data">
                            @csrf
                            <label class="admin-label">Tracking Status</label>
                            <select class="admin-input mb-2" name="status" required>
                                @foreach ($trackingStatuses as $status)
                                    <option value="{{ $status }}" @selected($case->current_admin_status === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                            <input class="admin-input mb-2" type="date" name="hearing_date" value="{{ optional($case->hearing_date)->format('Y-m-d') }}">
                            <input class="admin-input mb-2" name="final_outcome" placeholder="Final outcome" value="{{ $case->final_outcome }}">
                            <textarea class="admin-input" name="note" rows="2" placeholder="Tracking note"></textarea>
                            @include('admin.trademark-opposition.partials.additional-documents', [
                                'case' => $case,
                                'documents' => collect(),
                                'addButtonAttribute' => 'data-add-oppose-tracking-document',
                                'listAttribute' => 'data-oppose-tracking-document-list',
                                'templateAttribute' => 'data-oppose-tracking-document-template',
                                'rowAttribute' => 'data-oppose-tracking-document-row',
                                'removeAttribute' => 'data-remove-oppose-tracking-document',
                                'emptyAttribute' => 'data-oppose-tracking-document-empty',
                            ])
                            @error('optional_documents')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            @error('optional_documents.*')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            <div class="admin-actions"><button class="admin-btn" type="submit">Update Tracking</button></div>
                        </form>
                    @endif

                </div>
            </aside>
        </div>

        <div class="admin-review-modal" data-internal-note-modal>
            <div class="admin-review-dialog">
                <header><h3>Internal Case Note</h3></header>
                <form class="admin-review-dialog-body" method="POST" action="{{ route('admin.trademark-opposition.oppose.internal-note', $case) }}">
                    @csrf
                    <label class="admin-label">Notes</label>
                    <textarea class="admin-input" name="admin_internal_notes" rows="6" placeholder="Visible only to admin users.">{{ old('admin_internal_notes', $case->admin_internal_notes) }}</textarea>
                    <div class="admin-review-dialog-actions">
                        <button class="admin-btn admin-btn-light" type="button" data-internal-note-close>Cancel</button>
                        <button class="admin-btn" type="submit">Save Note</button>
                    </div>
                </form>
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
        (() => {
            const openButton = document.querySelector('[data-internal-note-open]');
            const modal = document.querySelector('[data-internal-note-modal]');
            const closeButtons = modal ? modal.querySelectorAll('[data-internal-note-close]') : [];

            const closeModal = () => modal?.classList.remove('is-visible');

            openButton?.addEventListener('click', () => {
                modal?.classList.add('is-visible');
                modal?.querySelector('textarea')?.focus();
            });
            closeButtons.forEach((button) => button.addEventListener('click', closeModal));
            modal?.addEventListener('click', (event) => {
                if (event.target === modal) closeModal();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeModal();
            });
        })();

        (() => {
            const statusSelect = document.querySelector('[data-third-party-status]');
            const badge = document.querySelector('[data-third-party-badge]');
            const statusAction = document.querySelector('[data-third-party-status-action]');
            const labels = {
                awaiting_response: 'Awaiting Response',
                counter_statement_received: 'Counter Statement Received',
                no_response_received: 'No Response Received',
                deadline_expired: 'Deadline Expired',
            };

            const refreshStatusControls = () => {
                if (!statusSelect) return;
                const value = statusSelect.value;
                if (badge) {
                    badge.textContent = labels[value] || labels.awaiting_response;
                    badge.classList.toggle('is-warning', value === 'deadline_expired');
                    badge.classList.toggle('is-info', value !== 'deadline_expired');
                }
                if (statusAction) {
                    if (value === 'counter_statement_received') {
                        statusAction.value = 'move_to_evidence';
                        statusAction.textContent = 'Save & Move to Evidence Stage';
                        statusAction.classList.remove('admin-btn-danger');
                        statusAction.classList.add('admin-btn-secondary');
                    } else if (['no_response_received', 'deadline_expired'].includes(value)) {
                        statusAction.value = 'close_matter';
                        statusAction.textContent = 'Close Matter';
                        statusAction.classList.remove('admin-btn-secondary');
                        statusAction.classList.add('admin-btn-danger');
                    } else {
                        statusAction.value = 'save';
                        statusAction.textContent = 'Save';
                        statusAction.classList.remove('admin-btn-secondary', 'admin-btn-danger');
                    }
                }
            };

            statusSelect?.addEventListener('change', refreshStatusControls);
            refreshStatusControls();

            const requestList = document.querySelector('[data-third-party-request-list]');
            const addRequest = document.querySelector('[data-add-third-party-request]');
            const requestRow = () => {
                const row = document.createElement('div');
                row.className = 'third-party-request-row';
                row.innerHTML = `
                    <input class="admin-input" name="evidence_items[]" placeholder="e.g. Website screenshots">
                    <button class="admin-document-remove request-remove" type="button" data-remove-third-party-request><i class="bi bi-trash"></i> Remove</button>
                `;
                return row;
            };

            addRequest?.addEventListener('click', () => {
                if (!requestList || requestList.children.length >= 10) return;
                requestList.appendChild(requestRow());
            });

            requestList?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-remove-third-party-request]');
                if (!button) return;
                if (requestList.children.length === 1) {
                    requestList.querySelector('input')?.focus();
                    return;
                }
                button.closest('.third-party-request-row')?.remove();
            });
        })();
    </script>

    <script>
        (() => {
            const form = document.querySelector('[data-legal-review-form]');
            if (!form) return;

            const list = form.querySelector('[data-legal-point-list]');
            const addButton = form.querySelector('[data-add-legal-point]');
            const reviewPoints = @json(array_values($reviewPoints));

            const escapeHtml = (value) => String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const optionHtml = (selected = '', selectedElsewhere = []) => [
                '<option value="">Select legal point</option>',
                ...reviewPoints
                    .filter((point) => point === selected || !selectedElsewhere.includes(point))
                    .map((point) => `<option value="${escapeHtml(point)}" ${point === selected ? 'selected' : ''}>${escapeHtml(point)}</option>`)
            ].join('');

            const rowHtml = (index) => `
                <div class="legal-point-row" data-legal-point-row>
                    <div class="legal-point-row-head">
                        <strong>Legal Point</strong>
                        <button class="legal-point-remove" type="button" data-remove-legal-point>Remove</button>
                    </div>
                    <div class="legal-point-grid">
                        <div>
                            <label class="admin-label">Select Legal Point</label>
                            <select class="admin-input" name="legal_points[${index}][review_point]" required>${optionHtml()}</select>
                        </div>
                        <div>
                            <label class="admin-label">Admin Internal Note</label>
                            <textarea class="admin-input" name="legal_points[${index}][admin_note]" rows="3" placeholder="Write admin internal note"></textarea>
                            <label class="legal-point-visible mt-2">
                                <input type="checkbox" name="legal_points[${index}][is_client_visible]" value="1">
                                <span>Visible to Client <small>(legal point only; internal note stays private)</small></span>
                            </label>
                        </div>
                    </div>
                </div>
            `;

            const refreshRows = () => {
                const rows = [...list.querySelectorAll('[data-legal-point-row]')];
                const usedValues = new Set();
                const normalizedSelections = rows.map((row) => {
                    const value = row.querySelector('select')?.value || '';
                    if (!value || usedValues.has(value)) return '';
                    usedValues.add(value);
                    return value;
                });
                const selectedValues = normalizedSelections.filter(Boolean);

                rows.forEach((row, index) => {
                    row.querySelectorAll('[name]').forEach((field) => {
                        field.name = field.name.replace(/legal_points\[\d+\]/, `legal_points[${index}]`);
                    });
                    const select = row.querySelector('select');
                    const selected = normalizedSelections[index] || '';
                    if (select) {
                        const selectedElsewhere = selectedValues.filter((value) => value !== selected);
                        select.innerHTML = optionHtml(selected, selectedElsewhere);
                        select.value = selected;
                    }
                    const remove = row.querySelector('[data-remove-legal-point]');
                    if (remove) remove.hidden = rows.length === 1;
                });

                if (addButton) {
                    addButton.disabled = selectedValues.length >= reviewPoints.length;
                    addButton.style.opacity = addButton.disabled ? '.55' : '';
                    addButton.style.cursor = addButton.disabled ? 'not-allowed' : '';
                }
            };

            addButton?.addEventListener('click', () => {
                list.insertAdjacentHTML('beforeend', rowHtml(list.querySelectorAll('[data-legal-point-row]').length));
                refreshRows();
            });

            list.addEventListener('click', (event) => {
                const removeButton = event.target.closest('[data-remove-legal-point]');
                if (!removeButton) return;
                removeButton.closest('[data-legal-point-row]')?.remove();
                refreshRows();
            });

            list.addEventListener('change', (event) => {
                if (event.target.matches('select')) refreshRows();
            });

            refreshRows();
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
            const closeModal = () => {
                modal?.classList.remove('is-visible');
                pendingAction = null;
            };

            toggle?.addEventListener('click', () => {
                const isSelecting = card.classList.toggle('is-selecting');
                toggle.textContent = isSelecting ? 'Cancel' : 'Select';
                if (!isSelecting) {
                    checkboxes.forEach((checkbox) => { checkbox.checked = false; });
                    refreshBulkActions();
                }
            });

            checkboxes.forEach((checkbox) => checkbox.addEventListener('change', refreshBulkActions));

            card.querySelectorAll('[data-evidence-open-modal]').forEach((button) => {
                button.addEventListener('click', () => {
                    if (selectedCount() === 0) return;
                    pendingAction = button.dataset.evidenceOpenModal;
                    const isRejected = pendingAction === 'rejected';
                    modalTitle.textContent = isRejected ? 'Ask for Evidence Reupload' : 'Mark Evidence as Reviewed';
                    modalCopy.textContent = isRejected
                        ? 'Add the changes the client must make before reuploading the selected evidence.'
                        : 'Confirm that the selected evidence has been reviewed. An approval note is optional.';
                    modalLabel.textContent = isRejected ? 'Changes Required' : 'Approval Note';
                    modalNote.placeholder = isRejected ? 'Describe the corrections or replacement evidence required' : 'Optional approval note';
                    modalNote.value = '';
                    modal?.classList.add('is-visible');
                    modalNote.focus();
                });
            });

            modalCancel?.addEventListener('click', closeModal);
            modal?.addEventListener('click', (event) => {
                if (event.target === modal) closeModal();
            });

            modalSubmit?.addEventListener('click', () => {
                const note = modalNote.value.trim();
                if (pendingAction === 'rejected' && note === '') {
                    modalNote.setCustomValidity('Please describe the changes required.');
                    modalNote.reportValidity();
                    modalNote.focus();
                    return;
                }

                modalNote.setCustomValidity('');
                const confirmed = window.confirm(
                    pendingAction === 'rejected'
                        ? 'Ask the client to reupload the selected evidence?'
                        : 'Mark the selected evidence as reviewed?'
                );
                if (!confirmed) return;

                actionInput.value = pendingAction;
                noteInput.value = note;
                modalSubmit.disabled = true;
                modalSubmit.textContent = 'Submitting...';
                HTMLFormElement.prototype.submit.call(form);
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeModal();
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
            form.addEventListener('admin-draft-restored', refreshHearingFields);
            refreshHearingFields();
        })();

        (() => {
            const form = document.querySelector('[data-decision-stage-form]');
            if (!form) return;

            const status = form.querySelector('[data-decision-status]');
            const withdrawnByField = form.querySelector('[data-withdrawn-by-field]');
            const withdrawnBySelect = form.querySelector('[data-withdrawn-by]');
            const finalFields = form.querySelector('[data-decision-final-fields]');
            const dateInput = form.querySelector('[data-decision-date]');
            const orderInput = form.querySelector('[data-decision-order]');
            const submit = form.querySelector('[data-decision-submit]');
            const decisionNote = form.querySelector('textarea[name="decision_note"]');
            const previewOppose = form.querySelector('[data-preview-oppose-status]');
            const previewDefence = form.querySelector('[data-preview-defence-status]');
            const previewFiling = form.querySelector('[data-preview-filing-result]');
            const previews = @json($decisionPreviewMap);

            const refreshDecisionFields = () => {
                const hasFinalDecision = Boolean(status?.value);
                const isWithdrawn = status?.value === '{{ \App\Support\TrademarkOppositionWorkflow::ADMIN_WITHDRAWN }}';
                const isOther = status?.value === '{{ \App\Support\TrademarkOppositionWorkflow::ADMIN_OTHER }}';
                const withdrawnKey = isWithdrawn && withdrawnBySelect?.value ? `withdrawn_${withdrawnBySelect.value}` : null;
                const preview = previews[withdrawnKey || status?.value] || null;

                if (finalFields) {
                    finalFields.hidden = !hasFinalDecision;
                }
                if (withdrawnByField) {
                    withdrawnByField.hidden = !isWithdrawn;
                }
                if (withdrawnBySelect) {
                    withdrawnBySelect.required = isWithdrawn;
                }
                if (dateInput) {
                    dateInput.required = hasFinalDecision;
                }
                if (orderInput) {
                    orderInput.required = hasFinalDecision;
                }
                if (decisionNote) {
                    decisionNote.required = isOther;
                }
                if (previewOppose) {
                    previewOppose.textContent = preview ? `Oppose Case Status: ${preview.oppose_case_status_label}` : 'Select final outcome';
                }
                if (previewDefence) {
                    previewDefence.textContent = `Defence Case Status: ${preview?.defence_case_status_label || 'Pending selection'}`;
                }
                if (previewFiling) {
                    previewFiling.textContent = `Trademark Filing Result: ${preview?.trademark_filing_result_label || 'Pending selection'}`;
                }
                if (submit) {
                    submit.textContent = hasFinalDecision ? 'Close Matter' : 'Select Outcome';
                }
            };

            status?.addEventListener('change', refreshDecisionFields);
            withdrawnBySelect?.addEventListener('change', refreshDecisionFields);
            form.addEventListener('admin-draft-restored', refreshDecisionFields);
            refreshDecisionFields();
        })();

        (() => {
            const forms = Array.from(document.querySelectorAll('.stage-panel form'));
            const storagePrefix = 'legalbruz:oppose-admin-stage-draft:{{ $case->id }}:';
            const skipNames = new Set(['_token', 'action', 'optional_documents[]', 'optional_document_names[]']);
            const fieldSelector = 'input[name]:not([type="file"]), textarea[name], select[name]';
            const fileStoreName = 'legalbruzOpposeAdminDraftFiles';

            const keyFor = (form, index) => `${storagePrefix}${form.action || location.pathname}:${index}`;
            const fieldsFor = (form) => Array.from(form.querySelectorAll(fieldSelector))
                .filter((field) => !skipNames.has(field.name));
            const openFileStore = () => new Promise((resolve, reject) => {
                if (!('indexedDB' in window)) {
                    reject(new Error('IndexedDB is not available.'));
                    return;
                }

                const request = indexedDB.open(fileStoreName, 1);
                request.onupgradeneeded = () => request.result.createObjectStore('drafts');
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
            const withFileStore = async (mode, callback) => {
                const db = await openFileStore();
                return new Promise((resolve, reject) => {
                    const transaction = db.transaction('drafts', mode);
                    const store = transaction.objectStore('drafts');
                    const result = callback(store);
                    transaction.oncomplete = () => {
                        db.close();
                        resolve(result);
                    };
                    transaction.onerror = () => {
                        db.close();
                        reject(transaction.error);
                    };
                });
            };
            const saveDraftFiles = async (storageKey, documents) => {
                await withFileStore('readwrite', (store) => store.put(documents, storageKey));
            };
            const getDraftFiles = async (storageKey) => {
                let documents = [];
                await withFileStore('readonly', (store) => {
                    const request = store.get(storageKey);
                    request.onsuccess = () => {
                        const result = request.result;
                        documents = Array.isArray(result)
                            ? { additionalDocuments: result, hearingNotice: null }
                            : {
                                additionalDocuments: Array.isArray(result?.additionalDocuments) ? result.additionalDocuments : [],
                                hearingNotice: result?.hearingNotice || null,
                            };
                    };
                });
                return documents;
            };
            const deleteDraftFiles = async (storageKey) => {
                try {
                    await withFileStore('readwrite', (store) => store.delete(storageKey));
                } catch (error) {
                    // Text drafts should still clear even if the browser file store is unavailable.
                }
            };

            const collectFormDraft = (form) => {
                const draft = {};
                fieldsFor(form).forEach((field) => {
                    if (field.type === 'radio') {
                        if (field.checked) draft[field.name] = field.value;
                        return;
                    }

                    if (field.type === 'checkbox') {
                        if (!Array.isArray(draft[field.name])) draft[field.name] = [];
                        if (field.checked) draft[field.name].push(field.value || 'on');
                        return;
                    }

                    if (!Array.isArray(draft[field.name])) draft[field.name] = [];
                    draft[field.name].push(field.value);
                });

                if (form.matches('[data-hearing-stage-form]')) {
                    draft.__hearing_note = form.querySelector('textarea[name="note"]')?.value || '';
                    draft.__adjournment_reason = form.querySelector('textarea[name="adjournment_reason"]')?.value || '';
                }

                return draft;
            };
            const collectDocumentDrafts = (form) => {
                const additionalDocuments = Array.from(form.querySelectorAll('input[type="file"][name="optional_documents[]"]')).map((fileInput) => {
                    const row = fileInput.closest('.admin-document-row');
                    const nameInput = row?.querySelector('input[name="optional_document_names[]"]');
                    const file = fileInput.files?.[0] || null;

                    return file ? {
                        documentName: nameInput?.value || '',
                        file,
                    } : null;
                })
                .filter(Boolean);
                const hearingNoticeInput = form.querySelector('input[type="file"][name="hearing_notice"]');
                const hearingNotice = hearingNoticeInput?.files?.[0] ? { file: hearingNoticeInput.files[0] } : null;

                return { additionalDocuments, hearingNotice };
            };

            const ensureEvidenceRows = (form, count) => {
                const addButton = form.querySelector('[data-add-third-party-request]');
                if (!addButton) return;

                while (form.querySelectorAll('[name="evidence_items[]"]').length < count) {
                    addButton.click();
                }
            };
            const addDocumentButtonFor = (form) => form.querySelector([
                '[data-add-draft-document]',
                '[data-add-evidence-request-document]',
                '[data-add-oppose-draft-document]',
                '[data-add-filing-document]',
                '[data-add-oppose-filing-document]',
                '[data-add-oppose-tracking-document]',
            ].join(','));
            const restoreFileInput = (fileInput, file) => {
                if (!fileInput || !file || typeof DataTransfer === 'undefined') return false;

                const transfer = new DataTransfer();
                transfer.items.add(file);
                fileInput.files = transfer.files;
                fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                return true;
            };
            const restoreDocumentDrafts = (form, fileDraft) => {
                const documents = Array.isArray(fileDraft)
                    ? fileDraft
                    : (Array.isArray(fileDraft?.additionalDocuments) ? fileDraft.additionalDocuments : []);
                const hearingNotice = Array.isArray(fileDraft) ? null : fileDraft?.hearingNotice;
                let restored = false;

                if (hearingNotice?.file) {
                    restored = restoreFileInput(form.querySelector('input[type="file"][name="hearing_notice"]'), hearingNotice.file) || restored;
                }

                if (!Array.isArray(documents) || documents.length === 0) return restored;

                const addButton = addDocumentButtonFor(form);
                if (!addButton || typeof DataTransfer === 'undefined') return restored;

                form.querySelectorAll('.admin-document-row.is-new').forEach((row) => row.remove());
                documents.forEach(() => addButton.click());

                const rows = Array.from(form.querySelectorAll('.admin-document-row.is-new')).slice(-documents.length);
                rows.forEach((row, index) => {
                    const document = documents[index];
                    const nameInput = row.querySelector('input[name="optional_document_names[]"]');
                    const fileInput = row.querySelector('input[type="file"][name="optional_documents[]"]');
                    const title = row.querySelector('.admin-document-row-head strong');

                    if (nameInput) nameInput.value = document.documentName || '';
                    if (title) title.textContent = document.documentName || document.file?.name || 'Additional Document';
                    restored = restoreFileInput(fileInput, document.file) || restored;
                });

                return restored;
            };

            const restoreFormDraft = (form, draft) => {
                if (Array.isArray(draft['evidence_items[]'])) {
                    ensureEvidenceRows(form, draft['evidence_items[]'].length);
                }

                window.requestAnimationFrame(() => {
                    Object.entries(draft).forEach(([name, value]) => {
                        const fields = fieldsFor(form).filter((field) => field.name === name);
                        if (fields.length === 0) return;

                        if (fields[0].type === 'radio') {
                            fields.forEach((field) => { field.checked = field.value === value; });
                            return;
                        }

                        if (fields[0].type === 'checkbox') {
                            const selected = Array.isArray(value) ? value : [value];
                            fields.forEach((field) => {
                                field.checked = selected.includes(field.value || 'on');
                            });
                            return;
                        }

                        const values = Array.isArray(value) ? value : [value];
                        fields.forEach((field, fieldIndex) => {
                            field.value = values[fieldIndex] ?? '';
                            field.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                    });

                    if (form.matches('[data-hearing-stage-form]')) {
                        const hearingNote = form.querySelector('textarea[name="note"]');
                        const adjournmentReason = form.querySelector('textarea[name="adjournment_reason"]');

                        if (hearingNote && Object.prototype.hasOwnProperty.call(draft, '__hearing_note')) {
                            hearingNote.value = draft.__hearing_note || '';
                        }
                        if (adjournmentReason && Object.prototype.hasOwnProperty.call(draft, '__adjournment_reason')) {
                            adjournmentReason.value = draft.__adjournment_reason || '';
                        }
                    }

                    form.dispatchEvent(new CustomEvent('admin-draft-restored', { bubbles: true }));
                });
            };

            forms.forEach((form, index) => {
                const actionGroups = Array.from(form.querySelectorAll('.admin-actions'));
                const actions = actionGroups[actionGroups.length - 1] || form;
                const draftButton = document.createElement('button');
                const status = document.createElement('span');
                const storageKey = keyFor(form, index);

                actions.classList.add('admin-draft-actions');
                draftButton.type = 'button';
                draftButton.className = 'admin-btn admin-btn-outline admin-draft-btn';
                draftButton.textContent = 'Save as Draft';
                status.className = 'admin-draft-status';

                actions.prepend(status);
                actions.prepend(draftButton);

                try {
                    const savedDraft = JSON.parse(localStorage.getItem(storageKey) || 'null');
                    if (savedDraft && typeof savedDraft === 'object') {
                        restoreFormDraft(form, savedDraft);
                        status.textContent = 'Draft restored';
                    }
                } catch (error) {
                    localStorage.removeItem(storageKey);
                }

                getDraftFiles(storageKey).then((documents) => {
                    if (restoreDocumentDrafts(form, documents)) {
                        status.textContent = 'Draft restored';
                    }
                }).catch(() => {});

                draftButton.addEventListener('click', async () => {
                    localStorage.setItem(storageKey, JSON.stringify(collectFormDraft(form)));
                    try {
                        await saveDraftFiles(storageKey, collectDocumentDrafts(form));
                    } catch (error) {
                        status.textContent = 'Draft saved, but files could not be stored by this browser';
                        return;
                    }

                    status.textContent = 'Draft saved locally';
                    window.setTimeout(() => {
                        if (status.textContent === 'Draft saved locally') status.textContent = '';
                    }, 2600);
                });

                form.addEventListener('submit', () => {
                    localStorage.removeItem(storageKey);
                    deleteDraftFiles(storageKey);
                });
            });
        })();

        document.querySelectorAll('[data-scroll-to-evidence]').forEach((button) => {
            button.addEventListener('click', () => {
                document.querySelector('#documents-evidence')?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
            });
        });
    </script>
@endsection
