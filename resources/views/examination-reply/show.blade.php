@extends('layouts.app')

@section('content')
    @php
        $workflow = \App\Support\ExaminationReportReplyWorkflow::class;
        $displayTimezone = config('app.timezone', 'Europe/London');
        $formatDateTime = fn ($timestamp, string $format = 'd M Y, h:i A T') => $timestamp
            ? \Illuminate\Support\Carbon::parse($timestamp)->timezone($displayTimezone)->format($format)
            : null;
        $objectionReplyOriginalAmount = (float) ($case->original_package_price ?: $case->package_price ?: 0);
        $objectionReplyAutoCoupon = $autoApplyCoupon ?? null;
        $objectionReplyPayableAmount = $case->payment_status === 'paid'
            ? (float) ($case->paid_amount ?: max($objectionReplyOriginalAmount - (float) $case->discount_amount, 0))
            : ($objectionReplyAutoCoupon ? $objectionReplyAutoCoupon->discountedAmountFor($objectionReplyOriginalAmount) : $objectionReplyOriginalAmount);
        $objectionReplyPayableAmountForJs = number_format($objectionReplyPayableAmount, 2, '.', '');
        $objectionReplyDiscountAmount = $case->payment_status === 'paid'
            ? (float) ($case->discount_amount ?: 0)
            : max($objectionReplyOriginalAmount - $objectionReplyPayableAmount, 0);
        $allVisibleDocuments = $case->documents->where('visibility', 'client')->sortByDesc('id')->values();
        $visibleDocuments = $allVisibleDocuments
            ->reject(function ($document) use ($allVisibleDocuments) {
                if ($document->uploaded_by !== 'client') {
                    return false;
                }

                return $allVisibleDocuments->contains(function ($candidate) use ($document) {
                    return $candidate->id > $document->id
                        && $candidate->uploaded_by === 'client'
                        && $candidate->document_type === $document->document_type;
                });
            })
            ->values();
        $latestDraft = $case->drafts->first();
        $latestDraftDocument = $visibleDocuments->firstWhere('document_type', 'reply_draft');
        $draftSupportingDocuments = $visibleDocuments
            ->filter(fn ($document) => $document->stage_key === 'draft' && $document->document_type !== 'reply_draft')
            ->values();
        $formatDocumentSize = static function (?int $bytes): string {
            $bytes = max(0, (int) $bytes);

            return $bytes >= 1048576
                ? number_format($bytes / 1048576, 1) . ' MB'
                : number_format(max(1, (int) ceil($bytes / 1024))) . ' KB';
        };
        $documentDisplayName = static function ($document): string {
            $title = trim((string) $document->document_title);
            $originalName = trim((string) $document->original_name);
            if ($title !== '' && strcasecmp($title, $originalName) !== 0) {
                return $title;
            }

            $typeLabel = trim((string) ($document->metadata['document_type_label'] ?? ''));
            if ($typeLabel !== '') {
                return $typeLabel;
            }

            return \Illuminate\Support\Str::headline($document->document_type ?: 'document');
        };
        $filingAcknowledgment = $visibleDocuments->firstWhere('document_type', 'filing_acknowledgment');
        $registryClientDocuments = $visibleDocuments
            ->where('uploaded_by', 'admin')
            ->filter(fn ($document) => in_array($document->stage_key, ['registry_update', 'hearing_required', 'close', 'matter_closed'], true)
                || in_array($document->document_type, ['registry_update', 'hearing_notice', 'final_registry_document'], true))
            ->values();
        $hearingNotice = $registryClientDocuments->firstWhere('document_type', 'hearing_notice');
        $finalDocuments = $registryClientDocuments;
        $clarificationRequestedDocuments = $case->requestedDocuments
            ->filter(fn ($requested) => $requested->stageRequest?->to_stage === $workflow::CLIENT_AWAITING_REGISTRY_UPDATE);
        $latestClarificationRequestId = $clarificationRequestedDocuments->max('stage_request_id');
        $clarificationRequestedDocuments = $latestClarificationRequestId
            ? $clarificationRequestedDocuments->where('stage_request_id', $latestClarificationRequestId)->values()
            : collect();
        $pendingClarificationDocuments = $clarificationRequestedDocuments->where('is_uploaded_by_client', false)->values();
        $uploadedClarificationDocuments = $clarificationRequestedDocuments->where('is_uploaded_by_client', true)->values();
        $pendingRequestedDocuments = $case->requestedDocuments
            ->where('is_uploaded_by_client', false)
            ->reject(fn ($requested) => $requested->stageRequest?->to_stage === $workflow::CLIENT_AWAITING_REGISTRY_UPDATE)
            ->values();
        $uploadedRequestedDocuments = $case->requestedDocuments
            ->where('is_uploaded_by_client', true)
            ->reject(fn ($requested) => $requested->stageRequest?->to_stage === $workflow::CLIENT_AWAITING_REGISTRY_UPDATE)
            ->values();
        $reuploadRequestedDocuments = $pendingRequestedDocuments
            ->filter(function ($requested) use ($case) {
                $documentType = \Illuminate\Support\Str::slug($requested->document_name, '_');

                return $case->documents
                    ->where('uploaded_by', 'client')
                    ->where('document_type', $documentType)
                    ->whereIn('review_status', ['reupload_requested', 'needs_better_copy'])
                    ->isNotEmpty();
            })
            ->values();
        $evidencePendingDocuments = $reuploadRequestedDocuments->isNotEmpty()
            ? $reuploadRequestedDocuments
            : $pendingRequestedDocuments;
        $hasReuploadRequest = $case->documents
            ->where('uploaded_by', 'client')
            ->whereIn('review_status', ['reupload_requested', 'needs_better_copy'])
            ->isNotEmpty();
        $isWaitingForRequestedDocumentReview = $pendingRequestedDocuments->isEmpty()
            && $uploadedRequestedDocuments->isNotEmpty()
            && $hasReuploadRequest
            && in_array($case->current_admin_status, [
                $workflow::ADMIN_APPLICATION_RECEIVED,
                $workflow::ADMIN_EVIDENCE_SUBMITTED,
            ], true);
        $deadlineClass = match ($case->deadline_status) {
            'green' => 'green',
            'yellow' => 'yellow',
            default => 'red',
        };
        $currentTimeline = collect($timeline)->firstWhere('status', 'active') ?: $timeline[0];
        $actionTitle = $currentTimeline['stage_label'];
        $actionDescription = $currentTimeline['description'];
        $actionBadge = $case->current_client_stage;
        $registryOutcomeType = match ($case->current_admin_status) {
            $workflow::ADMIN_ACCEPTED => 'Accepted',
            $workflow::ADMIN_ACCEPTED_ADVERTISED => 'Accepted & Advertised',
            $workflow::ADMIN_HEARING_ISSUED => 'Hearing Required',
            $workflow::ADMIN_FURTHER_ACTION_REQUIRED => 'Further Clarification Required',
            $workflow::ADMIN_APPLICATION_ABANDONED => 'Application Abandoned',
            $workflow::ADMIN_MATTER_CLOSED => $case->final_outcome,
            default => null,
        };
        if ($registryOutcomeType === 'Accepted') {
            $actionBadge = 'Accepted';
            $actionTitle = 'Trademark Reply Accepted';
            $actionDescription = 'Your objection reply has been accepted by the Trademark Registry.';
        } elseif ($registryOutcomeType === 'Accepted & Advertised') {
            $actionBadge = 'Accepted & Advertised';
            $actionTitle = 'Trademark Accepted & Advertised';
            $actionDescription = 'Your trademark has been accepted and advertised in the Trademark Journal.';
        } elseif (in_array($registryOutcomeType, ['Hearing Issued', 'Hearing Required'], true)) {
            $actionBadge = 'Hearing Required';
            $actionTitle = 'Hearing Required';
            $actionDescription = 'The Trademark Registry has issued a hearing notice. This is a separate stage and requires hearing preparation and representation.';
        } elseif ($registryOutcomeType === 'Further Clarification Required') {
            $actionBadge = 'Further Clarification Required';
            $actionTitle = 'Further Clarification Required';
            $actionDescription = 'The Registry has requested further clarification for your Trademark Objection Reply.';
        } elseif ($registryOutcomeType === 'Application Abandoned') {
            $actionBadge = 'Application Abandoned';
            $actionTitle = 'Application Abandoned';
            $actionDescription = 'The application has been marked abandoned due to delay or previous non-compliance.';
        } elseif ($case->registry_update_type === 'Still Waiting' && $case->current_admin_status === $workflow::ADMIN_AWAITING_REGISTRY_REVIEW) {
            $actionTitle = 'Still Waiting for Registry Update';
            $actionDescription = 'Your reply has been filed with the Trademark Registry. The application is still waiting for review by the Registry.';
        }
        $isRegistryAction = $registryOutcomeType !== null
            || $case->current_admin_status === $workflow::ADMIN_AWAITING_REGISTRY_REVIEW;
        $actionClientNote = match ($registryOutcomeType) {
            'Accepted' => $case->final_note_to_client ?: $case->client_visible_note ?: 'Your objection reply has been accepted by the Trademark Registry.',
            'Accepted & Advertised' => $case->final_note_to_client ?: $case->client_visible_note ?: 'Your trademark has been accepted and advertised in the Trademark Journal.',
            'Hearing Issued', 'Hearing Required' => $case->final_note_to_client ?: $case->client_visible_note ?: 'The Trademark Registry has issued a hearing notice. Please review the notice and contact our team for hearing support.',
            'Application Abandoned', 'Further Clarification Required' => $case->final_note_to_client ?: $case->client_visible_note,
            default => $case->current_admin_status === $workflow::ADMIN_MATTER_CLOSED
                ? ($case->final_note_to_client ?: $case->client_visible_note)
                : $case->client_visible_note,
        };
        $actionRegistryDocuments = in_array($registryOutcomeType, ['Hearing Issued', 'Hearing Required'], true)
            ? $registryClientDocuments
            : $registryClientDocuments->reject(fn ($document) => $document->document_type === 'hearing_notice')->values();
        $finalDocuments = $actionRegistryDocuments;
        $matterClosedStageDocuments = collect(collect($timeline)->firstWhere('stage_key', 'matter_closed')['documents'] ?? []);
        $finalDocumentsModalStage = $matterClosedStageDocuments->isNotEmpty()
            ? 'matter_closed'
            : 'awaiting_registry_update';
        $requestedDocumentNote = function ($requested) use ($case) {
            $stageMessage = trim((string) ($requested->stageRequest?->client_message ?? ''));
            if ($stageMessage !== '') {
                return $stageMessage;
            }

            $requestedName = \Illuminate\Support\Str::lower(trim((string) $requested->document_name));
            $reuploadNote = $case->documents
                ->where('uploaded_by', 'client')
                ->whereIn('review_status', ['reupload_requested', 'needs_better_copy'])
                ->first(function ($document) use ($requestedName) {
                    return \Illuminate\Support\Str::lower(\Illuminate\Support\Str::headline($document->document_type)) === $requestedName;
                })?->review_note;

            return trim((string) $reuploadNote);
        };
        $requestedDocumentNotes = $pendingRequestedDocuments
            ->map(fn ($requested) => $requestedDocumentNote($requested))
            ->filter()
            ->unique()
            ->values();
        $reuploadDocumentNotes = $reuploadRequestedDocuments
            ->map(fn ($requested) => $requestedDocumentNote($requested))
            ->filter()
            ->unique()
            ->values();
        $section11Details = $case->section_11_details ?? [];
        $objectionDetailRows = collect([
            'Portal Label' => $case->portal_label,
            'Objection Type' => !empty($case->objection_types) ? implode(', ', $case->objection_types) : null,
            'Section 9 Reasons' => !empty($case->section_9_reasons) ? implode(', ', $case->section_9_reasons) : null,
            'Similar Earlier Mark' => $section11Details['similar_mark_name'] ?? null,
            'Earlier Application Number' => $section11Details['earlier_application_number'] ?? null,
            'Similarity Note' => $section11Details['similarity_note'] ?? $case->section_11_note,
            'Formal Objection Reasons' => !empty($case->formal_objection_reasons) ? implode(', ', $case->formal_objection_reasons) : null,
        ])->filter(fn ($value) => filled($value));
    @endphp

    <style>
        .flowb-page{max-width:1180px;margin:-18px auto 34px;padding:0 18px;color:#26364f;font-size:.9rem}.flowb-layout{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:.75rem;align-items:start}.flowb-card{background:#fff;border:1px solid #e6ebf2;border-radius:12px;box-shadow:0 8px 20px rgba(15,36,68,.07);overflow:hidden;padding:0!important}.flowb-status{grid-column:1}.flowb-head,.flowb-action-head{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:.8rem 1rem;background:linear-gradient(135deg,#294d78,#2d4a73);color:#fff;margin-top:0!important}.flowb-head h1,.flowb-action-head h2{margin:0;color:#fff!important;font-size:1.15rem;font-weight:900}.flowb-head p{margin:.2rem 0 0;color:rgba(255,255,255,.8)}.flowb-pill{display:inline-flex;align-items:center;border-radius:7px;padding:.4rem .7rem;background:#fff;color:#174ea6;font-weight:900;white-space:nowrap}.flowb-body{padding:.8rem 1rem}.flowb-timeline{list-style:none;margin:0;padding:0}.flowb-step{display:grid;grid-template-columns:42px 1fr auto;gap:.75rem;align-items:center;min-height:62px;padding:.55rem 0;border-bottom:1px solid #e5eaf2}.flowb-step:last-child{border-bottom:0}.flowb-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:50%;background:#f1f4f8;color:#7b8794;font-size:1.05rem}.flowb-step.done .flowb-icon{background:#eafaf2;color:#11915c}.flowb-step.active .flowb-icon{background:#eaf1ff;color:#1464f6}.flowb-copy h3{margin:0 0 .15rem;color:#14294b;font-size:1rem;font-weight:900}.flowb-copy p{margin:0;color:#536176}.flowb-state{text-align:right;min-width:115px}.flowb-badge{display:inline-flex;border-radius:7px;padding:.3rem .55rem;background:#eef2f7;color:#64748b;font-weight:900}.done .flowb-badge{background:#ddf8e9;color:#137747}.active .flowb-badge{background:#eaf1ff;color:#1464f6}.flowb-step-date{display:block;margin-top:.25rem;color:#7a8798;font-size:.78rem;font-weight:750}.flowb-stage-title{display:flex;align-items:center;gap:.7rem;flex-wrap:wrap;margin-bottom:.15rem}.flowb-stage-doc-link{display:inline-flex;align-items:center;gap:.35rem;border:0;padding:0;background:transparent;color:#149b7e;font-size:.8rem;font-weight:900;white-space:nowrap;text-decoration:none}.flowb-sidebar{grid-column:2;grid-row:1 / span 2}.flowb-sidebar h2{margin:0!important;padding:.9rem 1rem;background:#294d78;color:#fff!important;font-size:1rem}.flowb-side-body{padding:1rem}.flowb-field{padding:.8rem;border:1px solid #e6edf7;border-radius:8px;background:#fbfdff;margin-bottom:.65rem}.flowb-field span{display:block;color:#66758b;font-size:.72rem;font-weight:900;text-transform:uppercase}.flowb-field strong{display:block;margin-top:.2rem;color:#14294b;overflow-wrap:anywhere}.flowb-deadline.green{background:#ddf8e9;color:#137747}.flowb-deadline.yellow{background:#fff3d6;color:#895710}.flowb-deadline.red{background:#fee2e2;color:#b91c1c}.flowb-action{grid-column:1;margin-top:.75rem}.flowb-action-body{padding:1.25rem}.flowb-action-body h3{margin:0 0 .3rem;color:#14294b;font-size:1.08rem;font-weight:900}.flowb-muted{color:#607089;line-height:1.55}.flowb-warning{padding:.8rem;border:1px solid #f1cb72;border-radius:8px;background:#fff8e7;color:#67460d;margin:.8rem 0}.flowb-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.8rem}.flowb-upload{padding:.8rem;border:1px solid #e0e7f0;border-radius:8px;background:#fbfdff}.flowb-upload label{display:block;color:#203e68;font-weight:900;margin-bottom:.45rem}.flowb-input{width:100%;min-height:42px;border:1px solid #cad6e8;border-radius:7px;padding:.55rem .7rem}.flowb-btn{min-height:42px;border:0;border-radius:7px;padding:0 1rem;background:#2a9d8f;color:#fff!important;font-weight:900;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:.4rem}.flowb-btn:hover{color:#fff;background:#23867a}.flowb-actions{display:flex;gap:.65rem;flex-wrap:wrap;margin-top:1rem}.flowb-btn.green{background:#159447}.flowb-btn.gray{background:#6b7280}.flowb-btn.light{background:#e5eaf2;color:#203e68!important}.flowb-doc-list{border:1px solid #dfe6ef;border-radius:9px;overflow:hidden}.flowb-doc{display:flex;align-items:center;gap:.8rem;padding:.8rem;border-bottom:1px solid #e7ecf3}.flowb-doc:last-child{border-bottom:0}.flowb-doc i{color:#16814a;font-size:1.2rem}.flowb-doc div{flex:1;min-width:0}.flowb-doc strong,.flowb-doc small{display:block;overflow-wrap:anywhere}.flowb-doc a{color:#1464f6;font-weight:900;text-decoration:none}.flowb-empty{display:flex;gap:.75rem;align-items:flex-start;padding:.9rem;border:1px dashed #cbd5e1;border-radius:9px;background:#f8fafc;color:#607089}.flowb-empty i{font-size:1.2rem;color:#94a3b8}.risk-card{border:1px solid #dfe8f4;border-radius:8px;background:#fbfdff;padding:.9rem;margin-top:.8rem}.risk-card strong{display:block;color:#203e68;font-weight:900}.risk-card p{margin:.35rem 0 0;color:#536176;line-height:1.55}@media(max-width:1050px){.flowb-layout{display:block}.flowb-sidebar,.flowb-action{margin-top:.75rem}}@media(max-width:640px){.flowb-page{padding:0 12px}.flowb-grid{grid-template-columns:1fr}.flowb-step{grid-template-columns:38px 1fr}.flowb-state{grid-column:2;text-align:left}.flowb-head,.flowb-action-head{align-items:flex-start;flex-direction:column}.flowb-doc{align-items:flex-start;flex-wrap:wrap}.flowb-doc a{margin-right:.7rem}}
    </style>
    <style>
        .flowb-upload-card{border:1px solid #dbe4f0;border-radius:8px;background:#fff;padding:.9rem;box-shadow:0 8px 18px rgba(15,36,68,.04);transition:border-color .18s ease,background .18s ease,box-shadow .18s ease}.flowb-upload-card.has-file{border-color:#8ed8a3;background:#f0fbf4;box-shadow:0 10px 22px rgba(22,101,52,.08)}.flowb-upload-card.is-reupload{border-color:#f4c052;background:#fff8e7}.flowb-upload-card.is-reupload .flowb-file-picker{border-color:#f4c052;background:#fffdf5}.flowb-upload-card.is-reupload.has-file{border-color:#8ed8a3;background:#f0fbf4}.flowb-upload-card>label{display:block;color:#14294b;font-size:.88rem;font-weight:900;line-height:1.25;margin-bottom:.7rem}.flowb-file-native{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}.flowb-file-picker{min-height:44px;width:100%;display:flex;align-items:center;flex-wrap:wrap;gap:.45rem .75rem;border:1px dashed #b9c8dc;border-radius:7px;background:#fbfdff;padding:.55rem .8rem;cursor:pointer;color:#26364f;font-weight:400;transition:border-color .18s ease,background .18s ease}.flowb-upload-card.has-file .flowb-file-picker,.flowb-upload-card.is-reupload.has-file .flowb-file-picker{border-color:#8ed8a3;background:#f7fef9}.flowb-file-picker i{color:#0757c7;font-size:1.05rem;flex:0 0 auto}.flowb-file-choose{color:#0757c7;font-size:.9rem;font-weight:800;white-space:nowrap}.flowb-file-name{flex:1 1 180px;min-width:0;max-width:100%;overflow-wrap:anywhere;word-break:break-word;color:#26364f;font-size:.9rem;font-weight:500;line-height:1.3;white-space:normal}.flowb-upload-card.has-file .flowb-file-picker i,.flowb-upload-card.has-file .flowb-file-choose,.flowb-upload-card.is-reupload.has-file .flowb-file-picker i,.flowb-upload-card.is-reupload.has-file .flowb-file-choose{color:#12814a}.flowb-upload-card.is-reupload .flowb-file-picker i,.flowb-upload-card.is-reupload .flowb-file-choose{color:#b7791f}.flowb-upload-card.is-reupload.has-file .flowb-file-picker i,.flowb-upload-card.is-reupload.has-file .flowb-file-choose{color:#12814a}.flowb-upload-state{display:block;margin-top:.45rem;color:#607089;font-size:.78rem;font-weight:750;line-height:1.35}.flowb-upload-state.is-uploaded{color:#12814a}.flowb-upload-state.is-reupload{color:#9a5b00}.flowb-upload-state i{margin-right:.25rem}.flowb-admin-note{display:flex;gap:.5rem;margin:.8rem 0 1rem;padding:.7rem .8rem;border:1px solid #f1cb72;border-radius:8px;background:#fff8e7;color:#67460d;font-size:.86rem;font-weight:750;line-height:1.4}.flowb-admin-note i{flex:0 0 auto;margin-top:.1rem;color:#b7791f}.flowb-admin-note strong{display:block;margin-bottom:.15rem;color:#67460d}.flowb-admin-note p{margin:.15rem 0 0;white-space:pre-line}.flowb-message-field{margin-top:1rem}.flowb-message-field label{display:block;color:#203e68;font-weight:900;margin-bottom:.45rem}.flowb-message-field textarea{min-height:86px;resize:vertical}.flowb-objection-details{margin:1rem 0;border:1px solid #dbe4f0;border-radius:9px;background:#fbfdff;padding:1rem}.flowb-objection-details h4{margin:0 0 .75rem;color:#14294b;font-size:1rem;font-weight:900}.flowb-objection-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem}.flowb-objection-item{border:1px solid #e5edf7;border-radius:8px;background:#fff;padding:.7rem}.flowb-objection-item span{display:block;color:#66758b;font-size:.7rem;font-weight:900;text-transform:uppercase}.flowb-objection-item strong{display:block;margin-top:.2rem;color:#203e68;line-height:1.35;overflow-wrap:anywhere}.flowb-sidebar .flowb-objection-details{margin:0;border-width:1px 0 0;border-radius:0;background:#fff}.flowb-sidebar .flowb-objection-grid{grid-template-columns:1fr}@media(max-width:640px){.flowb-objection-grid{grid-template-columns:1fr}}
    </style>
    <style>
        .flowb-pay-shell{display:grid;gap:1.05rem}.flowb-pay-intro{display:grid;grid-template-columns:54px minmax(0,1fr);gap:1rem;align-items:center;margin-bottom:.25rem}.flowb-pay-icon{width:54px;height:54px;border-radius:50%;display:grid;place-items:center;background:#e6fbf7;color:#0f9f86;font-size:1.55rem}.flowb-pay-intro h3{font-size:1.35rem!important;margin:0 0 .2rem!important;color:#102a4c!important}.flowb-pay-intro p{margin:0;color:#5f6f89;font-weight:700}.flowb-pay-card{display:grid;grid-template-columns:56px minmax(0,1fr);gap:1rem;align-items:center;border:1px solid #dce9f8;border-radius:10px;background:#fbfdff;padding:1.1rem 1.25rem}.flowb-pay-card h4{margin:0 0 .25rem;color:#0b58c7;font-size:1rem;font-weight:900}.flowb-pay-card p{margin:0;color:#223555;font-weight:650;line-height:1.55}.flowb-pay-card.note{border-color:#f4cf7d;background:#fffaf0}.flowb-pay-card.note h4,.flowb-pay-card.note p{color:#743a14}.flowb-pay-card.risk{background:#fbfdff}.flowb-pay-card.recommend{border-color:#cceedd;background:#f8fffb}.flowb-pay-card.recommend h4{color:#078657}.flowb-pay-card.package h4{color:#5b35d5}.flowb-pay-card.fees h4,.flowb-pay-card.description h4{color:#0757c7}.flowb-pay-card.terms{border-style:dashed;border-color:#f3d5a5;background:#fffaf2}.flowb-pay-card.terms p{color:#743a14}.flowb-pay-card.disclaimer{grid-template-columns:auto minmax(0,1fr);border-style:dashed;background:#fff;color:#26364f;transition:border-color .18s ease,background .18s ease,box-shadow .18s ease}.flowb-pay-card.disclaimer input{width:20px;height:20px}.flowb-pay-card.disclaimer.is-error{border-color:#dc2626;background:#fff1f2;box-shadow:0 0 0 4px rgba(220,38,38,.12)}.flowb-pay-card.disclaimer.is-error span{color:#991b1b;font-weight:900}.flowb-pay-bubble{width:48px;height:48px;border-radius:50%;display:grid;place-items:center;font-size:1.35rem}.flowb-pay-bubble.note{background:#fff1c6;color:#d97706}.flowb-pay-bubble.risk{background:#eaf1ff;color:#0757c7}.flowb-pay-bubble.recommend{background:#dcfce7;color:#078657}.flowb-pay-bubble.package{background:#eee7ff;color:#5b35d5}.flowb-pay-bubble.fees{background:#eaf1ff;color:#0757c7;font-weight:900}.flowb-pay-bubble.description{background:#eaf1ff;color:#0757c7}.flowb-pay-bubble.terms{background:#ffedcc;color:#b45309}.flowb-risk-line{display:flex;gap:1.1rem;align-items:center;flex-wrap:wrap}.flowb-risk-pill{display:inline-flex;border-radius:7px;background:#eaf1ff;color:#0757c7;padding:.45rem .8rem;font-weight:900}.flowb-risk-sep{width:1px;height:28px;background:#b9c8dc}.flowb-pay-two{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.flowb-pay-actions{display:flex;gap:1rem;align-items:center;flex-wrap:wrap;margin-top:.35rem}.flowb-pay-actions .flowb-btn{min-width:210px;min-height:58px;border-radius:9px;background:#079b78;font-size:1rem;box-shadow:0 10px 20px rgba(7,155,120,.18)}.flowb-secure{display:inline-flex;align-items:center;gap:.45rem;color:#5f6f89;font-weight:800}.flowb-secure i{color:#12926d}@media(max-width:760px){.flowb-pay-two{grid-template-columns:1fr}.flowb-pay-card,.flowb-pay-intro{grid-template-columns:44px minmax(0,1fr);gap:.75rem}.flowb-pay-bubble,.flowb-pay-icon{width:44px;height:44px}.flowb-risk-sep{display:none}.flowb-pay-actions .flowb-btn{width:100%;min-width:0}}
    </style>
    <style>
        .flowb-main{display:grid;gap:.75rem;min-width:0;align-content:start}.flowb-main .flowb-status,.flowb-main .flowb-action{grid-column:auto;margin-top:0}.flowb-sidebar-stack{grid-column:2;grid-row:1;display:grid;gap:.75rem;align-content:start}.flowb-sidebar{grid-column:auto;grid-row:auto}.flowb-pricing h2{display:flex;align-items:center;gap:.5rem;margin:0!important;padding:.9rem 1rem;background:linear-gradient(135deg,#173f73,#235990);color:#fff!important;font-size:1rem}.flowb-price-body{padding:1rem}.flowb-price-package{padding-bottom:.9rem;border-bottom:1px solid #e5ebf3}.flowb-price-label{display:block;color:#66758b;font-size:.7rem;font-weight:900;letter-spacing:.03em;text-transform:uppercase}.flowb-price-package strong{display:block;margin-top:.25rem;color:#14294b;font-size:1.05rem;overflow-wrap:anywhere}.flowb-price-amount{display:block;margin-top:.45rem;color:#0757c7;font-size:1.55rem;font-weight:950}.flowb-price-description{margin:.8rem 0 0;color:#536176;line-height:1.5}.flowb-price-list{margin:.85rem 0 0;padding:0;list-style:none}.flowb-price-list li{display:flex;gap:.5rem;align-items:flex-start;padding:.35rem 0;color:#34445e}.flowb-price-list i{color:#159447;margin-top:.1rem}.flowb-price-status{display:flex;align-items:center;justify-content:space-between;gap:.6rem;margin-top:.9rem;padding:.7rem .8rem;border-radius:8px;background:#f2f5f9}.flowb-price-status strong{color:#64748b;text-transform:capitalize}.flowb-price-status.is-paid{background:#eaf9f0}.flowb-price-status.is-paid strong{color:#137747}.flowb-invoice-btn{width:100%;margin-top:.8rem;background:#159447}.flowb-invoice-btn:hover{background:#10793a}@media(max-width:1050px){.flowb-sidebar-stack{margin-top:.75rem}.flowb-sidebar-stack .flowb-sidebar{margin-top:0}}
    </style>
    <style>
        .flowb-draft-section{margin-top:1.25rem}.flowb-draft-section:first-child{margin-top:.5rem}.flowb-registry-documents{margin-bottom:1.4rem}.flowb-draft-section-title{display:block;margin:0 0 .65rem;color:#536176;font-size:.78rem;font-weight:900;letter-spacing:.025em;text-transform:uppercase}.flowb-sent-documents{overflow:hidden;border:1px solid #dfe6ef;border-radius:9px;background:#fff}.flowb-sent-document{display:flex;align-items:center;gap:1rem;padding:1rem 1.1rem;border-bottom:1px solid #e7ecf3}.flowb-sent-document:last-child{border-bottom:0}.flowb-sent-document-icon{width:46px;height:46px;flex:0 0 46px;display:grid;place-items:center;border-radius:9px;background:#edf9f1;color:#17813b;font-size:1.35rem}.flowb-sent-document-copy{min-width:0;flex:1}.flowb-sent-document-copy strong{display:block;color:#14294b;font-size:.92rem;font-weight:900;overflow-wrap:anywhere}.flowb-sent-document-copy span{display:block;margin-top:.2rem;color:#566780;font-size:.82rem}.flowb-sent-document-actions{display:flex;gap:.65rem;flex-wrap:wrap}.flowb-doc-action{min-height:40px;display:inline-flex;align-items:center;justify-content:center;gap:.45rem;padding:0 1rem;border:1px solid #9ebcff;border-radius:7px;color:#0755d9;background:#fff;font-weight:800;text-decoration:none}.flowb-doc-action:hover{color:#0647b4;background:#f4f7ff}.flowb-draft-decisions{display:flex;flex-wrap:wrap;gap:.8rem;margin-top:1.1rem}.flowb-decision-button{width:min(340px,100%);min-height:56px;display:flex;align-items:center;gap:.7rem;border-radius:8px;padding:.65rem .8rem;text-align:left;cursor:pointer}.flowb-decision-button i{width:30px;height:30px;flex:0 0 30px;display:grid;place-items:center;border-radius:999px;font-size:1rem}.flowb-decision-button strong,.flowb-decision-button span{display:block}.flowb-decision-button strong{font-size:.9rem}.flowb-decision-button span{margin-top:.1rem;font-size:.75rem}.flowb-decision-approve{border:1px solid #159447;background:#159447;color:#fff}.flowb-decision-approve i{background:rgba(255,255,255,.14)}.flowb-decision-change{border:1px solid #f26a2e;background:#fff;color:#e85217}.flowb-decision-change i{background:#fff0e9}.flowb-draft-modal{position:fixed;inset:0;z-index:3100;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(15,35,60,.5)}.flowb-draft-modal.is-visible{display:flex}.flowb-draft-modal-dialog{width:min(540px,100%);overflow:hidden;border-radius:12px;background:#fff;box-shadow:0 24px 70px rgba(15,35,60,.3)}.flowb-draft-modal-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.2rem;border-bottom:1px solid #e6ebf2}.flowb-draft-modal-head h3{margin:0;color:#14294b;font-size:1.08rem}.flowb-draft-modal-close{border:0;background:transparent;color:#607089;font-size:1.35rem}.flowb-draft-modal-body{padding:1.2rem}.flowb-draft-modal-body p{margin:0 0 1rem;color:#536176;line-height:1.5}.flowb-draft-modal-body label{display:block;margin-bottom:.45rem;color:#203e68;font-weight:900}.flowb-draft-modal-actions{display:flex;justify-content:flex-end;gap:.7rem;margin-top:1rem}@media(max-width:720px){.flowb-sent-document{align-items:flex-start;flex-wrap:wrap}.flowb-sent-document-actions{width:100%;padding-left:62px}.flowb-decision-button{width:100%}}
    </style>
    <style>
        .flowb-status .flowb-head{padding:1rem 1.25rem}.flowb-status .flowb-body{padding:.7rem 1.25rem 1rem}.flowb-status .flowb-step{position:relative;grid-template-columns:48px minmax(0,1fr) 160px;gap:1rem;min-height:82px;padding:.85rem 0;align-items:center}.flowb-status .flowb-step::before{content:"";position:absolute;z-index:0;left:23px;top:57px;bottom:-27px;border-left:2px dashed #d8e1ee}.flowb-status .flowb-step:last-child::before{display:none}.flowb-status .flowb-icon{position:relative;z-index:1;width:48px;height:48px;background:#f1f4f8;color:#7b8794;box-shadow:0 8px 22px rgba(15,36,68,.08)}.flowb-status .flowb-step.done .flowb-icon{background:#eafaf2;color:#11915c}.flowb-status .flowb-step.active .flowb-icon{background:#eaf1ff;color:#1367ff}.flowb-status .flowb-mini-check{position:absolute;right:-4px;top:1px;width:19px;height:19px;display:grid;place-items:center;border:2px solid #fff;border-radius:999px;background:#13a268;color:#fff;font-size:.66rem}.flowb-status .flowb-copy{min-width:0}.flowb-status .flowb-copy h3{font-size:1rem;line-height:1.25}.flowb-status .flowb-copy p{max-width:680px;color:#4d5b70;font-size:.87rem;line-height:1.45}.flowb-status .flowb-state{display:flex;flex-direction:column;align-items:flex-end;justify-content:center;gap:.35rem;min-width:0;text-align:right}.flowb-status .flowb-badge{border:1px solid #ffc66d;background:#fff4dc;color:#8a560e;font-size:.78rem;line-height:1.2}.flowb-status .done .flowb-badge{border-color:#97e2bd;background:#ddf8e9;color:#137747}.flowb-status .active .flowb-badge{border-color:#9dc0ff;background:#eaf1ff;color:#1367ff}.flowb-status .flowb-step-date{display:flex;align-items:center;justify-content:flex-end;gap:.3rem;margin:0;color:#6c7487;font-size:.76rem;font-weight:700}.flowb-status .flowb-stage-doc-link:hover{color:#0d765f;text-decoration:underline}@media(max-width:760px){.flowb-status .flowb-step{grid-template-columns:48px minmax(0,1fr);align-items:start;row-gap:.55rem}.flowb-status .flowb-state{grid-column:2;flex-direction:row;align-items:center;justify-content:flex-start;gap:.65rem;flex-wrap:wrap;text-align:left}.flowb-status .flowb-step-date{justify-content:flex-start}.flowb-status .flowb-step::before{bottom:-22px}}@media(max-width:640px){.flowb-status .flowb-head{padding:.9rem 1rem}.flowb-status .flowb-body{padding:.6rem 1rem .85rem}.flowb-status .flowb-step{grid-template-columns:42px minmax(0,1fr);gap:.75rem;padding:.8rem 0}.flowb-status .flowb-icon{width:42px;height:42px}.flowb-status .flowb-step::before{left:20px;top:51px;bottom:-20px}.flowb-status .flowb-mini-check{width:18px;height:18px}.flowb-status .flowb-stage-title{align-items:flex-start;gap:.35rem .65rem}}
    </style>

    <style>
        .flowb-state-top{display:flex;align-items:center;justify-content:flex-end;gap:.45rem}
        .flowb-stage-info{width:28px;height:28px;display:inline-grid;place-items:center;flex:0 0 28px;border:1px solid #8db5ff;border-radius:50%;background:#eef4ff;color:#0757d9;font-size:.92rem;cursor:pointer;box-shadow:0 3px 9px rgba(7,87,217,.1)}
        .flowb-stage-info:hover{border-color:#0757d9;background:#dfeaff}
        .flowb-conversation-modal{position:fixed;inset:0;z-index:3075;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(15,35,60,.55)}
        .flowb-conversation-modal.is-visible{display:flex}
        .flowb-conversation-dialog{width:min(620px,100%);max-height:min(680px,90vh);overflow:auto;border-radius:12px;background:#fff;box-shadow:0 24px 70px rgba(15,35,60,.3)}
        .flowb-conversation-head{position:sticky;top:0;z-index:2;display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1rem 1.2rem;border-bottom:1px solid #e3e9f2;background:#fff}
        .flowb-conversation-head h3{margin:0;color:#14294b;font-size:1.08rem;font-weight:900}
        .flowb-conversation-head p{margin:.2rem 0 0;color:#66758b;font-size:.82rem}
        .flowb-conversation-close{border:0;background:transparent;color:#607089;font-size:1.4rem;cursor:pointer}
        .flowb-conversation-body{display:grid;gap:.75rem;padding:1rem 1.2rem 1.2rem;background:#f7f9fc}
        .flowb-conversation-message{width:min(84%,480px);padding:.75rem .85rem;border:1px solid #dce5f0;border-radius:10px 10px 10px 3px;background:#fff;box-shadow:0 4px 12px rgba(15,36,68,.04)}
        .flowb-conversation-message.client{justify-self:end;border-color:#bcd2ff;border-radius:10px 10px 3px 10px;background:#eaf2ff}
        .flowb-conversation-meta{display:flex;align-items:center;justify-content:space-between;gap:.7rem;margin-bottom:.3rem;color:#607089;font-size:.7rem}
        .flowb-conversation-meta strong{color:#174ea6;font-size:.75rem;font-weight:900}
        .flowb-conversation-message.client .flowb-conversation-meta strong{color:#0b7a5e}
        .flowb-conversation-message p{margin:0;color:#26364f;line-height:1.5;white-space:pre-line;overflow-wrap:anywhere}
        .flowb-conversation-empty{padding:1.25rem;border:1px dashed #cbd7e6;border-radius:9px;background:#fff;color:#66758b;text-align:center}
        .flowb-conversation-empty i{display:block;margin-bottom:.4rem;color:#0757d9;font-size:1.35rem}
        @media(max-width:760px){.flowb-state-top{justify-content:flex-start}.flowb-status .flowb-copy{padding-right:30px}.flowb-status .flowb-stage-info{position:absolute;z-index:2;top:.7rem;right:0;width:23px;height:23px;min-width:23px;flex-basis:23px;font-size:.72rem;box-shadow:0 2px 6px rgba(7,87,217,.1)}}
        @media(max-width:640px){.flowb-conversation-head{padding:1rem}.flowb-conversation-body{padding:.8rem 1rem 1rem}.flowb-conversation-message{width:92%}}
    </style>

    <style>
        .flowb-price-original{display:block;margin-top:.45rem;color:#8a94a6;text-decoration:line-through;font-weight:800}.flowb-price-original+.flowb-price-amount{margin-top:.2rem}
        .flowb-stage-doc-link{cursor:pointer}
        .flowb-stage-doc-modal{position:fixed;inset:0;z-index:3050;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(15,35,60,.55)}
        .flowb-stage-doc-modal.is-visible{display:flex}
        .flowb-stage-doc-dialog{width:min(760px,100%);max-height:min(760px,90vh);overflow:auto;border-radius:12px;background:#fff;box-shadow:0 24px 70px rgba(15,35,60,.3)}
        .flowb-stage-doc-head{position:sticky;top:0;z-index:2;display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1rem 1.2rem;border-bottom:1px solid #e3e9f2;background:#fff}
        .flowb-stage-doc-head h3{margin:0;color:#14294b;font-size:1.08rem;font-weight:900}
        .flowb-stage-doc-head p{margin:.2rem 0 0;color:#66758b;font-size:.82rem}
        .flowb-stage-doc-close{border:0;background:transparent;color:#607089;font-size:1.4rem;cursor:pointer}
        .flowb-stage-doc-body{padding:.65rem 1.2rem 1.2rem}
        .flowb-stage-doc-tabs{display:flex;gap:.35rem;margin:0 0 .4rem;padding:0;border-bottom:1px solid #dfe6ef}
        .flowb-stage-doc-tab{position:relative;display:inline-flex;align-items:center;gap:.4rem;margin:0 0 -1px;padding:.7rem .85rem;border:0;border-bottom:3px solid transparent;background:transparent;color:#66758b;font-size:.8rem;font-weight:900;cursor:pointer}
        .flowb-stage-doc-tab:hover{color:#174ea6}
        .flowb-stage-doc-tab.is-active{border-bottom-color:#2a9d8f;color:#17406f}
        .flowb-stage-doc-count{display:inline-grid;min-width:20px;height:20px;padding:0 5px;place-items:center;border-radius:999px;background:#edf2f8;color:#536176;font-size:.68rem}
        .flowb-stage-doc-tab.is-active .flowb-stage-doc-count{background:#def7ef;color:#137760}
        .flowb-stage-doc-panel{margin:0!important;padding:0!important}
        .flowb-stage-doc-panel[hidden]{display:none}
        .flowb-stage-doc-list{overflow:hidden;border:1px solid #dfe6ef;border-radius:9px;background:#fff}
        .flowb-stage-doc-row{display:flex;align-items:center;gap:.8rem;padding:.85rem;border-bottom:1px solid #e7ecf3}
        .flowb-stage-doc-row:last-child{border-bottom:0}
        .flowb-stage-doc-icon{width:38px;height:38px;flex:0 0 38px;display:grid;place-items:center;border-radius:8px;background:#edf9f1;color:#16814a}
        .flowb-stage-doc-copy{min-width:0;flex:1}
        .flowb-stage-doc-copy strong{display:block;color:#14294b;font-size:.88rem;overflow-wrap:anywhere}
        .flowb-stage-doc-copy span{display:block;margin-top:.15rem;color:#66758b;font-size:.76rem}
        .flowb-stage-doc-actions{display:flex;gap:.45rem;flex-wrap:wrap}
        .flowb-stage-doc-actions a{display:inline-flex;align-items:center;gap:.35rem;min-height:34px;padding:0 .7rem;border:1px solid #a9c1ff;border-radius:6px;color:#0755d9;font-size:.78rem;font-weight:800;text-decoration:none}
        @media(max-width:640px){.flowb-stage-doc-row{align-items:flex-start;flex-wrap:wrap}.flowb-stage-doc-actions{width:100%;padding-left:46px}.flowb-stage-doc-dialog{max-height:92vh}.flowb-stage-doc-head{padding:1rem}.flowb-stage-doc-body{padding:.45rem 1rem 1rem}.flowb-stage-doc-tabs{overflow-x:auto}.flowb-stage-doc-tab{flex:0 0 auto;padding:.7rem .6rem}}
    </style>

    <div class="flowb-page">
        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

        <div class="flowb-layout">
            <div class="flowb-main">
            <section class="flowb-card flowb-status">
                <header class="flowb-head">
                    <div>
                        <h1>Trademark Objection Reply · Status Tracking</h1>
                        <p>{{ $case->case_number }} · {{ $case->trademark_name }} · Application {{ $case->application_number }}</p>
                    </div>
                    <span class="flowb-pill">{{ $case->current_client_stage }}</span>
                </header>
                <div class="flowb-body">
                    <ol class="flowb-timeline">
                        @foreach ($timeline as $index => $step)
                            @php
                                $state = $step['status'] === 'completed' ? 'done' : ($step['status'] === 'active' ? 'active' : 'pending');
                            @endphp
                            <li class="flowb-step {{ $state }}">
                                <span class="flowb-icon">
                                    <i class="bi {{ $step['icon'] }}"></i>
                                    @if ($step['status'] === 'completed')
                                        <span class="flowb-mini-check"><i class="bi bi-check-lg"></i></span>
                                    @endif
                                </span>
                                <div class="flowb-copy">
                                    <div class="flowb-stage-title">
                                        <h3>{{ $step['stage_label'] }}</h3>
                                        @if ($step['documents_available'])
                                            <button class="flowb-stage-doc-link" type="button" data-open-stage-documents="{{ $step['stage_key'] }}"><i class="bi bi-folder2-open"></i> View Documents</button>
                                        @endif
                                    </div>
                                    <p>{{ $step['description'] }}</p>
                                </div>
                                <div class="flowb-state">
                                    <div class="flowb-state-top">
                                        @if ($step['conversation_available'] ?? false)
                                            <button class="flowb-stage-info" type="button" data-open-stage-conversation="{{ $step['stage_key'] }}" aria-label="View {{ $step['stage_label'] }} conversation" title="View stage conversation">
                                                <i class="bi bi-chat-dots-fill"></i>
                                            </button>
                                        @endif
                                        <span class="flowb-badge">{{ $step['status_label'] ?? ($step['status'] === 'completed' ? 'Completed' : ($step['status'] === 'active' ? 'Active' : 'Pending')) }}</span>
                                    </div>
                                    @if ($step['completed_at'])
                                        <small class="flowb-step-date"><i class="bi bi-clock"></i>{{ $formatDateTime($step['completed_at']) }}</small>
                                    @elseif (($step['is_optional'] ?? false) && $step['status'] === 'pending')
                                        <small class="flowb-step-date">Only if issued</small>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>

            @foreach ($timeline as $step)
                @php
                    $stageDocuments = collect($step['documents'] ?? []);
                    $legalTeamStageDocuments = $stageDocuments->where('uploaded_by', 'admin')->values();
                    $clientStageDocuments = $stageDocuments->where('uploaded_by', 'client')->values();
                @endphp
                @if ($stageDocuments->isNotEmpty())
                    <div class="flowb-stage-doc-modal" data-stage-documents-modal="{{ $step['stage_key'] }}" role="dialog" aria-modal="true" aria-labelledby="stageDocumentsTitle-{{ $step['stage_key'] }}">
                        <div class="flowb-stage-doc-dialog">
                            <header class="flowb-stage-doc-head">
                                <div>
                                    <h3 id="stageDocumentsTitle-{{ $step['stage_key'] }}">{{ $step['stage_label'] }} Documents</h3>
                                    <p>Documents exchanged during this stage.</p>
                                </div>
                                <button class="flowb-stage-doc-close" type="button" data-close-stage-documents aria-label="Close">&times;</button>
                            </header>
                            <div class="flowb-stage-doc-body">
                                <div class="flowb-stage-doc-tabs" role="tablist" aria-label="Document sender">
                                    @if ($legalTeamStageDocuments->isNotEmpty())
                                        <button class="flowb-stage-doc-tab is-active" type="button" role="tab" aria-selected="true" data-stage-doc-tab="legal-team">
                                            <i class="bi bi-briefcase"></i>
                                            Documents Sent by Legal Team
                                            <span class="flowb-stage-doc-count">{{ $legalTeamStageDocuments->count() }}</span>
                                        </button>
                                    @endif
                                    @if ($clientStageDocuments->isNotEmpty())
                                        <button class="flowb-stage-doc-tab {{ $legalTeamStageDocuments->isEmpty() ? 'is-active' : '' }}" type="button" role="tab" aria-selected="{{ $legalTeamStageDocuments->isEmpty() ? 'true' : 'false' }}" data-stage-doc-tab="client">
                                            <i class="bi bi-person"></i>
                                            Documents Sent by You
                                            <span class="flowb-stage-doc-count">{{ $clientStageDocuments->count() }}</span>
                                        </button>
                                    @endif
                                </div>

                                @if ($legalTeamStageDocuments->isNotEmpty())
                                    <section class="flowb-stage-doc-panel" data-stage-doc-panel="legal-team">
                                        <div class="flowb-stage-doc-list">
                                            @foreach ($legalTeamStageDocuments as $document)
                                                <div class="flowb-stage-doc-row">
                                                    <span class="flowb-stage-doc-icon"><i class="bi bi-file-earmark-check"></i></span>
                                                    <div class="flowb-stage-doc-copy">
                                                        <strong>{{ $documentDisplayName($document) }}</strong>
                                                        <span>{{ strtoupper($document->file_type ?: 'FILE') }} · {{ $formatDocumentSize($document->file_size) }} · Uploaded {{ $formatDateTime($document->created_at) }}</span>
                                                    </div>
                                                    <div class="flowb-stage-doc-actions">
                                                        <a href="{{ route('examination-reply.document.view', $document) }}" target="_blank" rel="noopener"><i class="bi bi-eye"></i> View</a>
                                                        <a href="{{ route('examination-reply.document.download', $document) }}"><i class="bi bi-download"></i> Download</a>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </section>
                                @endif

                                @if ($clientStageDocuments->isNotEmpty())
                                    <section class="flowb-stage-doc-panel" data-stage-doc-panel="client" @if ($legalTeamStageDocuments->isNotEmpty()) hidden @endif>
                                        <div class="flowb-stage-doc-list">
                                            @foreach ($clientStageDocuments as $document)
                                                <div class="flowb-stage-doc-row">
                                                    <span class="flowb-stage-doc-icon"><i class="bi bi-file-earmark-check"></i></span>
                                                    <div class="flowb-stage-doc-copy">
                                                        <strong>{{ $documentDisplayName($document) }}</strong>
                                                        <span>{{ strtoupper($document->file_type ?: 'FILE') }} · {{ $formatDocumentSize($document->file_size) }} · Uploaded {{ $formatDateTime($document->created_at) }}</span>
                                                    </div>
                                                    <div class="flowb-stage-doc-actions">
                                                        <a href="{{ route('examination-reply.document.view', $document) }}" target="_blank" rel="noopener"><i class="bi bi-eye"></i> View</a>
                                                        <a href="{{ route('examination-reply.document.download', $document) }}"><i class="bi bi-download"></i> Download</a>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </section>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
                @if ($step['conversation_available'] ?? false)
                    <div class="flowb-conversation-modal" data-stage-conversation-modal="{{ $step['stage_key'] }}" role="dialog" aria-modal="true" aria-labelledby="stageConversationTitle-{{ $step['stage_key'] }}">
                        <div class="flowb-conversation-dialog">
                            <header class="flowb-conversation-head">
                                <div>
                                    <h3 id="stageConversationTitle-{{ $step['stage_key'] }}">{{ $step['stage_label'] }} Conversation</h3>
                                    <p>Messages exchanged between you and the legal team during this stage.</p>
                                </div>
                                <button class="flowb-conversation-close" type="button" data-close-stage-conversation aria-label="Close">&times;</button>
                            </header>
                            <div class="flowb-conversation-body">
                                @forelse ($step['conversation'] as $message)
                                    <article class="flowb-conversation-message {{ $message['sender'] === 'client' ? 'client' : 'admin' }}">
                                        <div class="flowb-conversation-meta">
                                            <strong>{{ $message['sender_label'] }}</strong>
                                            @if ($message['created_at'])
                                                <time datetime="{{ $message['created_at']->toIso8601String() }}">{{ $formatDateTime($message['created_at']) }}</time>
                                            @endif
                                        </div>
                                        <p>{{ $message['message'] }}</p>
                                    </article>
                                @empty
                                    <div class="flowb-conversation-empty">
                                        <i class="bi bi-info-circle"></i>
                                        No client or legal-team messages were recorded for this stage.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach

            <section class="flowb-card flowb-action" id="action-center">
                <header class="flowb-action-head"><h2>Action Center</h2><span class="flowb-pill">{{ $actionBadge }}</span></header>
                <div class="flowb-action-body">
                    @unless ($isWaitingForRequestedDocumentReview || $case->current_client_stage === $workflow::CLIENT_PRICING_PAYMENT)
                        <h3>{{ $actionTitle }}</h3>
                        <p class="flowb-muted">{{ $actionDescription }}</p>
                    @endunless
                    @if ($actionClientNote && $case->current_client_stage !== $workflow::CLIENT_PRICING_PAYMENT)
                        <div class="flowb-warning"><strong>{{ $isRegistryAction ? 'Legal Team Note' : 'Admin Note' }}:</strong> <span>{{ $actionClientNote }}</span></div>
                    @endif

                    @if ($isRegistryAction && $actionRegistryDocuments->isNotEmpty())
                        <div class="flowb-draft-section flowb-registry-documents">
                            <span class="flowb-draft-section-title">Documents Sent by Legal Team</span>
                            <div class="flowb-sent-documents">
                                @foreach ($actionRegistryDocuments as $registryDocument)
                                    <div class="flowb-sent-document">
                                        <span class="flowb-sent-document-icon"><i class="bi bi-file-earmark-check"></i></span>
                                        <div class="flowb-sent-document-copy">
                                            <strong>{{ $documentDisplayName($registryDocument) }}</strong>
                                            <span>{{ strtoupper($registryDocument->file_type ?: 'FILE') }} · {{ $formatDocumentSize($registryDocument->file_size) }} · Uploaded {{ $formatDateTime($registryDocument->created_at) }}</span>
                                        </div>
                                        <div class="flowb-sent-document-actions">
                                            <a class="flowb-doc-action" href="{{ route('examination-reply.document.view', $registryDocument) }}" target="_blank"><i class="bi bi-eye"></i> View</a>
                                            <a class="flowb-doc-action" href="{{ route('examination-reply.document.download', $registryDocument) }}"><i class="bi bi-download"></i> Download</a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (in_array($registryOutcomeType, ['Accepted', 'Accepted & Advertised'], true))
                        <div class="flowb-grid">
                            <div class="flowb-field"><span>Final Outcome</span><strong>{{ $registryOutcomeType }}</strong></div>
                            @if ($case->registry_update_date)
                                <div class="flowb-field"><span>Registry Update Date</span><strong>{{ $case->registry_update_date->format('d M Y') }}</strong></div>
                            @endif
                            @if ($case->closed_at)
                                <div class="flowb-field"><span>Matter Closed On</span><strong>{{ $formatDateTime($case->closed_at) }}</strong></div>
                            @endif
                        </div>
                        <div class="flowb-empty"><i class="bi bi-check-circle"></i><div><strong>No action is required right now.</strong></div></div>
                        @if ($finalDocuments->isNotEmpty())
                            <div class="flowb-actions"><button class="flowb-btn light" type="button" data-open-stage-documents="{{ $finalDocumentsModalStage }}"><i class="bi bi-folder2-open"></i> View Final Documents</button></div>
                        @endif
                    @elseif ($registryOutcomeType === 'Application Abandoned')
                        <div class="flowb-grid">
                            <div class="flowb-field"><span>Final Outcome</span><strong>Application Abandoned</strong></div>
                            @if ($case->registry_update_date)
                                <div class="flowb-field"><span>Registry Update Date</span><strong>{{ $case->registry_update_date->format('d M Y') }}</strong></div>
                            @endif
                            @if ($case->closed_at)
                                <div class="flowb-field"><span>Matter Closed On</span><strong>{{ $formatDateTime($case->closed_at) }}</strong></div>
                            @endif
                        </div>
                        <div class="flowb-empty"><i class="bi bi-info-circle"></i><div><strong>No action is required right now.</strong><br>Please review the final update shared by our team.</div></div>
                        @if ($finalDocuments->isNotEmpty())
                            <div class="flowb-actions"><button class="flowb-btn light" type="button" data-open-stage-documents="{{ $finalDocumentsModalStage }}"><i class="bi bi-folder2-open"></i> View Final Documents</button></div>
                        @endif
                    @elseif (in_array($registryOutcomeType, ['Hearing Issued', 'Hearing Required'], true))
                        @if ($case->hearing_date || $case->hearing_time)
                            <div class="flowb-field">
                                <span>Hearing Details</span>
                                <strong>
                                    @if ($case->hearing_date) Hearing Date: {{ $case->hearing_date->format('d M Y') }} @endif
                                    @if ($case->hearing_date && $case->hearing_time) · @endif
                                    @if ($case->hearing_time) Hearing Time: {{ \Illuminate\Support\Carbon::parse($case->hearing_time)->format('h:i A') }} @endif
                                </strong>
                            </div>
                        @endif
                        <div class="flowb-empty"><i class="bi bi-exclamation-circle"></i><div><strong>Please review the hearing notice.</strong><br>Our team will guide you on the next steps.</div></div>
                        @if ($case->hearing_package_price)
                            <div class="risk-card">
                                <strong>Hearing Support Package</strong>
                                <p>₹{{ number_format((float) $case->hearing_package_price, 2) }} · {{ $case->hearing_payment_status === 'paid' ? 'Paid' : 'Payment pending' }}</p>
                            </div>
                            @include('partials.government-fee-notice')
                        @endif
                        <div class="flowb-actions">
                            <a class="flowb-btn gray" href="{{ route('dashboard') }}">Book Hearing Support</a>
                            @if ($case->hearing_package_price && $case->hearing_payment_status !== 'paid')
                                <a class="flowb-btn" href="{{ route('dashboard') }}">Pay for Hearing Support</a>
                            @endif
                        </div>
                    @elseif ($registryOutcomeType === 'Further Clarification Required')
                        @if ($pendingClarificationDocuments->isNotEmpty())
                            <form method="POST" action="{{ route('examination-reply.requested-documents', $case) }}" enctype="multipart/form-data">
                                @csrf
                                <div class="flowb-draft-section">
                                    <span class="flowb-draft-section-title">Requested Documents</span>
                                    <div class="flowb-grid">
                                        @foreach ($pendingClarificationDocuments as $requested)
                                            <div class="flowb-upload-card">
                                                <label>{{ $requested->document_name }} <span class="flowb-badge">{{ $requested->is_required ? 'Required' : 'Optional' }}</span></label>
                                                <label class="flowb-file-picker">
                                                    <input class="flowb-file-native" type="file" name="requested_documents[{{ $requested->id }}]" {{ $requested->is_required ? 'required' : '' }}>
                                                    <i class="bi bi-cloud-arrow-up"></i>
                                                    <span class="flowb-file-choose">Choose file</span>
                                                    <span class="flowb-file-name" data-flowb-file-name>No file chosen</span>
                                                </label>
                                                <small class="flowb-upload-state"><i class="bi bi-info-circle"></i> Accepted: PDF, JPG, PNG, DOC, DOCX.</small>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                @if ($uploadedClarificationDocuments->isNotEmpty())
                                    <div class="flowb-admin-note"><i class="bi bi-check-circle"></i><span><strong>Already submitted</strong><p>{{ $uploadedClarificationDocuments->pluck('document_name')->implode(', ') }}</p></span></div>
                                @endif
                                <div class="flowb-message-field">
                                    <label for="clarification-message-to-admin">Message to admin <span class="flowb-muted">(optional)</span></label>
                                    <textarea id="clarification-message-to-admin" class="flowb-input" name="message_to_admin" placeholder="Add any context for the legal team.">{{ old('message_to_admin') }}</textarea>
                                </div>
                                <div class="flowb-actions"><button class="flowb-btn" type="submit"><i class="bi bi-cloud-upload"></i> Submit Clarification Documents</button></div>
                            </form>
                        @elseif ($clarificationRequestedDocuments->isNotEmpty())
                            <div class="flowb-empty"><i class="bi bi-check-circle"></i><div><strong>Your clarification documents have been submitted.</strong><br>Our legal team will review them.</div></div>
                        @else
                            <div class="flowb-empty"><i class="bi bi-info-circle"></i><div><strong>No document upload is required right now.</strong><br>Our team has shared the clarification details. Please follow the legal team note above.</div></div>
                        @endif
                    @elseif ($case->current_admin_status === $workflow::ADMIN_AWAITING_REGISTRY_REVIEW)
                        <div class="flowb-empty"><i class="bi bi-clock-history"></i><div><strong>No action is required right now.</strong><br>We will notify you when the Trademark Registry issues an update.</div></div>
                    @elseif ($case->current_admin_status === $workflow::ADMIN_MATTER_CLOSED)
                        <div class="flowb-grid">
                            <div class="flowb-field"><span>Final Outcome</span><strong>{{ $case->final_outcome ?: 'Matter Closed' }}</strong></div>
                            @if ($case->registry_update_date)
                                <div class="flowb-field"><span>Registry Update Date</span><strong>{{ $case->registry_update_date->format('d M Y') }}</strong></div>
                            @endif
                            @if ($case->closed_at)
                                <div class="flowb-field"><span>Matter Closed On</span><strong>{{ $formatDateTime($case->closed_at) }}</strong></div>
                            @endif
                        </div>
                        <div class="flowb-empty"><i class="bi bi-check-circle"></i><div><strong>No action is required right now.</strong><br>Please review the final update shared by our team.</div></div>
                        @if ($finalDocuments->isNotEmpty())
                            <div class="flowb-actions"><button class="flowb-btn light" type="button" data-open-stage-documents="{{ $finalDocumentsModalStage }}"><i class="bi bi-folder2-open"></i> View Final Documents</button></div>
                        @endif
                    @elseif ($case->current_client_stage !== $workflow::CLIENT_EVIDENCE_COLLECTION && $pendingRequestedDocuments->isNotEmpty())
                        <form method="POST" action="{{ route('examination-reply.requested-documents', $case) }}" enctype="multipart/form-data">
                            @csrf
                            @if ($requestedDocumentNotes->isNotEmpty())
                                <div class="flowb-admin-note">
                                    <i class="bi bi-chat-left-text"></i>
                                    <span>
                                        <strong>Admin note</strong>
                                        @foreach ($requestedDocumentNotes as $note)
                                            <p>{{ $note }}</p>
                                        @endforeach
                                    </span>
                                </div>
                            @endif
                            <div class="flowb-grid">
                                @foreach ($pendingRequestedDocuments as $requested)
                                    <div class="flowb-upload-card">
                                        <label>{{ $requested->document_name }} {{ $requested->is_required ? '(required)' : '' }}</label>
                                        <label class="flowb-file-picker">
                                            <input class="flowb-file-native" type="file" name="requested_documents[{{ $requested->id }}]" {{ $requested->is_required ? 'required' : '' }}>
                                            <i class="bi bi-cloud-arrow-up"></i>
                                            <span class="flowb-file-choose">Choose file</span>
                                            <span class="flowb-file-name" data-flowb-file-name>No file chosen</span>
                                        </label>
                                        <small class="flowb-upload-state"><i class="bi bi-info-circle"></i> Accepted: PDF, JPG, PNG, DOC, DOCX.</small>
                                    </div>
                                @endforeach
                            </div>
                            <div class="flowb-message-field">
                                <label for="message-to-admin-documents">Message to admin <span class="flowb-muted">(optional)</span></label>
                                <textarea id="message-to-admin-documents" class="flowb-input" name="message_to_admin" placeholder="Add any context for the legal team.">{{ old('message_to_admin') }}</textarea>
                            </div>
                            <div class="flowb-actions"><button class="flowb-btn" type="submit"><i class="bi bi-cloud-upload"></i> Upload Requested Documents</button></div>
                        </form>
                    @elseif ($case->current_client_stage === $workflow::CLIENT_EVIDENCE_COLLECTION && $pendingRequestedDocuments->isNotEmpty())
                        <form method="POST" action="{{ route('examination-reply.evidence', $case) }}" enctype="multipart/form-data">
                            @csrf
                            @if ($reuploadDocumentNotes->isNotEmpty())
                                <div class="flowb-admin-note">
                                    <i class="bi bi-chat-left-text"></i>
                                    <span>
                                        <strong>Reupload requested</strong>
                                        @foreach ($reuploadDocumentNotes as $note)
                                            <p>{{ $note }}</p>
                                        @endforeach
                                    </span>
                                </div>
                            @elseif ($requestedDocumentNotes->isNotEmpty())
                                <div class="flowb-admin-note">
                                    <i class="bi bi-chat-left-text"></i>
                                    <span>
                                        <strong>Admin note</strong>
                                        @foreach ($requestedDocumentNotes as $note)
                                            <p>{{ $note }}</p>
                                        @endforeach
                                    </span>
                                </div>
                            @endif
                            <div class="flowb-grid">
                                @if($reuploadRequestedDocuments->isEmpty())
                                    <div class="flowb-upload"><label>When did you start using this brand?</label><select class="flowb-input" name="usage_status" required><option value="proposed">Proposed to be used</option><option value="used">Already in use</option></select></div>
                                    <div class="flowb-upload"><label>Date of First Use</label><input class="flowb-input" type="date" name="first_use_date"></div>
                                    <div class="flowb-upload"><label>Is the brand used continuously?</label><select class="flowb-input" name="used_continuously" required><option value="yes">Yes</option><option value="no">No</option></select></div>
                                    <div class="flowb-upload"><label>Annual Sales, if any</label><input class="flowb-input" name="annual_sales"></div>
                                    <div class="flowb-upload"><label>Advertising Expenses, if any</label><input class="flowb-input" name="advertising_expenses"></div>
                                    <div class="flowb-upload"><label>Has anyone objected to your brand before?</label><select class="flowb-input" name="previous_objection" required><option value="no">No</option><option value="yes">Yes</option></select></div>
                                    <div class="flowb-upload"><label>Are you aware of similar brands in the market?</label><select class="flowb-input" name="similar_brands_known" required><option value="no">No</option><option value="yes">Yes</option></select></div>
                                @endif
                                @foreach ($evidencePendingDocuments as $requested)
                                    <div class="flowb-upload-card @if($reuploadRequestedDocuments->contains('id', $requested->id)) is-reupload @endif">
                                        <label>{{ $requested->document_name }} {{ $requested->is_required ? '(required)' : '' }}</label>
                                        <label class="flowb-file-picker">
                                            <input class="flowb-file-native" type="file" name="requested_documents[{{ $requested->id }}]" {{ $requested->is_required ? 'required' : '' }}>
                                            <i class="bi bi-cloud-arrow-up"></i>
                                            <span class="flowb-file-choose">Choose file</span>
                                            <span class="flowb-file-name" data-flowb-file-name>No file chosen</span>
                                        </label>
                                        @if($reuploadRequestedDocuments->contains('id', $requested->id))
                                            <small class="flowb-upload-state is-reupload"><i class="bi bi-exclamation-triangle"></i> Reupload requested by admin.</small>
                                        @else
                                            <small class="flowb-upload-state"><i class="bi bi-info-circle"></i> Accepted: PDF, JPG, PNG, DOC, DOCX.</small>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <div class="flowb-message-field">
                                <label for="message-to-admin-evidence">Message to admin <span class="flowb-muted">(optional)</span></label>
                                <textarea id="message-to-admin-evidence" class="flowb-input" name="message_to_admin" placeholder="Add any context for the legal team.">{{ old('message_to_admin') }}</textarea>
                            </div>
                            <div class="flowb-actions"><button class="flowb-btn" type="submit"><i class="bi bi-cloud-upload"></i> Submit Evidence</button></div>
                        </form>
                    @elseif ($case->current_client_stage === $workflow::CLIENT_PRICING_PAYMENT && $case->package_price && $case->payment_status !== 'paid')
                        <div class="flowb-pay-shell">
                            <div class="flowb-pay-intro">
                                <span class="flowb-pay-icon"><i class="bi bi-credit-card-2-front"></i></span>
                                <div>
                                    <h3>Pricing &amp; Payment</h3>
                                    <p>Review the package summary on the right and complete payment to start reply drafting.</p>
                                </div>
                            </div>

                            @if ($case->client_visible_note)
                                <div class="flowb-pay-card note">
                                    <span class="flowb-pay-bubble note"><i class="bi bi-info-lg"></i></span>
                                    <div>
                                        <h4>Note to Client</h4>
                                        <p>{{ $case->client_visible_note }}</p>
                                    </div>
                                </div>
                            @endif

                            @if ($case->risk_level)
                                <div class="flowb-pay-card risk">
                                    <span class="flowb-pay-bubble risk"><i class="bi bi-shield"></i></span>
                                    <div>
                                        <h4>Risk Level</h4>
                                        <div class="flowb-risk-line">
                                            <span class="flowb-risk-pill">{{ $case->risk_level }}</span>
                                            <span class="flowb-risk-sep"></span>
                                            <p>{{ $case->client_visible_risk_note ?: $case->risk_reason }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if ($case->recommendation_note)
                                <div class="flowb-pay-card recommend">
                                    <span class="flowb-pay-bubble recommend"><i class="bi bi-star"></i></span>
                                    <div>
                                        <h4>Recommendation</h4>
                                        <p>{{ $case->recommendation_note }}</p>
                                    </div>
                                </div>
                            @endif

                            <div class="flowb-pay-card terms">
                                <span class="flowb-pay-bubble terms"><i class="bi bi-file-earmark-text"></i></span>
                                <p>You are purchasing Trademark Objection Reply service. This includes examination report review, reply drafting, and online filing. Government fees are not included. It does not include hearing representation, opposition proceedings, appeal, rectification, or fresh trademark filing unless separately purchased.</p>
                            </div>

                            @include('partials.payment-coupons', ['paymentCoupons' => $paymentCoupons, 'paymentAmount' => $objectionReplyOriginalAmount, 'couponInputName' => 'objection_reply_discount_coupon_id', 'selectedCouponId' => $objectionReplyAutoCoupon?->id])

                            @include('partials.government-fee-notice')

                            <label class="flowb-pay-card disclaimer" for="successDisclaimer">
                                <input type="checkbox" id="successDisclaimer">
                                <span>I understand that Legal Bruz does not guarantee acceptance or registration of the trademark.</span>
                            </label>

                            <div class="flowb-pay-actions">
                                <button class="flowb-btn" type="button" id="payObjectionReply"><i class="bi bi-lock"></i> Pay ₹<span data-objection-pay-amount>{{ number_format($objectionReplyPayableAmount, 2) }}</span> &amp; Continue</button>
                                <span class="flowb-secure"><i class="bi bi-shield-check"></i> Secure payment</span>
                            </div>
                        </div>
                    @elseif ($case->current_client_stage === $workflow::CLIENT_DRAFT_APPROVAL && $latestDraft && $latestDraft->status === 'client_approval_pending')
                        @if ($latestDraftDocument)
                            <div class="flowb-draft-section">
                                <span class="flowb-draft-section-title">Objection Reply Draft</span>
                                <div class="flowb-sent-documents">
                                    <div class="flowb-sent-document">
                                        <span class="flowb-sent-document-icon"><i class="bi bi-file-earmark-text"></i></span>
                                        <div class="flowb-sent-document-copy">
                                            <strong>{{ $latestDraftDocument->document_title ?: 'Objection Reply Draft' }}</strong>
                                            <span>{{ strtoupper($latestDraftDocument->file_type ?: 'FILE') }} · {{ $formatDocumentSize($latestDraftDocument->file_size) }} · Uploaded {{ $formatDateTime($latestDraftDocument->created_at) }}</span>
                                        </div>
                                        <div class="flowb-sent-document-actions">
                                            <a class="flowb-doc-action" href="{{ route('examination-reply.document.view', $latestDraftDocument) }}" target="_blank"><i class="bi bi-eye"></i> View</a>
                                            <a class="flowb-doc-action" href="{{ route('examination-reply.document.download', $latestDraftDocument) }}"><i class="bi bi-download"></i> Download</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($draftSupportingDocuments->isNotEmpty())
                            <div class="flowb-draft-section">
                                <span class="flowb-draft-section-title">Other Documents</span>
                                <div class="flowb-sent-documents">
                                    @foreach ($draftSupportingDocuments as $supportingDocument)
                                        <div class="flowb-sent-document">
                                            <span class="flowb-sent-document-icon"><i class="bi bi-file-earmark-check"></i></span>
                                            <div class="flowb-sent-document-copy">
                                                <strong>{{ $documentDisplayName($supportingDocument) }}</strong>
                                                <span>{{ strtoupper($supportingDocument->file_type ?: 'FILE') }} · {{ $formatDocumentSize($supportingDocument->file_size) }} · Uploaded {{ $formatDateTime($supportingDocument->created_at) }}</span>
                                            </div>
                                            <div class="flowb-sent-document-actions">
                                                <a class="flowb-doc-action" href="{{ route('examination-reply.document.view', $supportingDocument) }}" target="_blank"><i class="bi bi-eye"></i> View</a>
                                                <a class="flowb-doc-action" href="{{ route('examination-reply.document.download', $supportingDocument) }}"><i class="bi bi-download"></i> Download</a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="flowb-draft-decisions">
                            <button class="flowb-decision-button flowb-decision-approve" type="button" data-open-exam-draft-modal="approve">
                                <i class="bi bi-check-circle"></i><span><strong>Approve Draft</strong><span>Approve and submit for final processing.</span></span>
                            </button>
                            <button class="flowb-decision-button flowb-decision-change" type="button" data-open-exam-draft-modal="changes">
                                <i class="bi bi-pencil-square"></i><span><strong>Request Changes</strong><span>Request corrections and a revised upload.</span></span>
                            </button>
                        </div>

                        <div class="flowb-draft-modal" data-exam-draft-modal="approve" role="dialog" aria-modal="true" aria-labelledby="approveExamDraftTitle">
                            <div class="flowb-draft-modal-dialog">
                                <div class="flowb-draft-modal-head">
                                    <h3 id="approveExamDraftTitle">Approve Objection Reply Draft</h3>
                                    <button class="flowb-draft-modal-close" type="button" data-close-exam-draft-modal aria-label="Close">&times;</button>
                                </div>
                                <form method="POST" action="{{ route('examination-reply.draft.approve', $case) }}">
                                    @csrf
                                    <div class="flowb-draft-modal-body">
                                        <p>Confirm that the draft is approved and may proceed to final filing preparation.</p>
                                        <div class="flowb-draft-modal-actions">
                                            <button class="flowb-btn gray" type="button" data-close-exam-draft-modal>Cancel</button>
                                            <button class="flowb-btn green" type="submit"><i class="bi bi-check-circle"></i> Approve Draft</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="flowb-draft-modal" data-exam-draft-modal="changes" role="dialog" aria-modal="true" aria-labelledby="requestExamDraftChangesTitle">
                            <div class="flowb-draft-modal-dialog">
                                <div class="flowb-draft-modal-head">
                                    <h3 id="requestExamDraftChangesTitle">Request Draft Reupload</h3>
                                    <button class="flowb-draft-modal-close" type="button" data-close-exam-draft-modal aria-label="Close">&times;</button>
                                </div>
                                <form method="POST" action="{{ route('examination-reply.draft.request-changes', $case) }}">
                                    @csrf
                                    <div class="flowb-draft-modal-body">
                                        <p>Clearly describe every correction required. The matter will return to the legal drafting team.</p>
                                        <label for="examDraftChangeRequest">Reupload / Change Note</label>
                                        <textarea class="flowb-input" id="examDraftChangeRequest" name="change_request" rows="5" minlength="10" maxlength="1500" placeholder="Example: Please correct the applicant address on page 2 and upload the revised draft." required></textarea>
                                        <div class="flowb-draft-modal-actions">
                                            <button class="flowb-btn gray" type="button" data-close-exam-draft-modal>Cancel</button>
                                            <button class="flowb-btn" type="submit"><i class="bi bi-arrow-repeat"></i> Request Reupload</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @elseif ($case->current_admin_status === $workflow::ADMIN_CHANGES_REQUESTED)
                        <div class="flowb-empty">
                            <i class="bi bi-arrow-repeat"></i>
                            <div>
                                <strong>Objection Reply Draft asked for reupload.</strong><br>
                                Your requested changes have been shared with our drafting team. You will be notified when the revised draft is ready.
                            </div>
                        </div>
                    @elseif ($case->current_client_stage === $workflow::CLIENT_REPLY_FILED)
                        @if ($filingAcknowledgment)
                            <div class="flowb-actions"><a class="flowb-btn light" href="{{ route('examination-reply.document.download', $filingAcknowledgment) }}"><i class="bi bi-download"></i> Download Acknowledgment</a></div>
                        @else
                            <div class="flowb-empty"><i class="bi bi-file-earmark"></i><div><strong>Acknowledgment pending</strong><br>The filing acknowledgment will appear here when uploaded.</div></div>
                        @endif
                    @elseif ($case->current_client_stage === $workflow::CLIENT_HEARING_REQUIRED)
                        @if ($hearingNotice)
                            <div class="flowb-actions"><a class="flowb-btn light" href="{{ route('examination-reply.document.download', $hearingNotice) }}"><i class="bi bi-download"></i> Download Hearing Notice</a></div>
                        @endif
                        @if ($case->hearing_date || $case->hearing_time)
                            <div class="flowb-field"><span>Hearing Details</span><strong>{{ $case->hearing_date?->format('d M Y') }} {{ $case->hearing_date && $case->hearing_time ? '·' : '' }} {{ $case->hearing_time ? \Illuminate\Support\Carbon::parse($case->hearing_time)->format('h:i A') : '' }}</strong></div>
                        @endif
                        <div class="flowb-actions"><a class="flowb-btn gray" href="{{ route('dashboard') }}">Book Hearing Support</a></div>
                    @elseif ($case->current_client_stage === $workflow::CLIENT_MATTER_CLOSED)
                        <div class="flowb-field"><span>Final Outcome</span><strong>{{ $case->final_outcome ?: 'Matter Closed' }}</strong></div>
                        <p class="flowb-muted">{{ $case->final_note_to_client ?: 'The matter has been closed with a final Registry update.' }}</p>
                        @if ($finalDocuments->isNotEmpty())
                            <div class="flowb-actions"><button class="flowb-btn light" type="button" data-open-stage-documents="{{ $finalDocumentsModalStage }}"><i class="bi bi-folder2-open"></i> View Final Documents</button></div>
                        @endif
                    @elseif ($isWaitingForRequestedDocumentReview)
                        <div class="flowb-empty"><i class="bi bi-clock-history"></i><div><strong>No action is required right now.</strong><br>Your documents have been re-uploaded successfully and are waiting for review by our team. We will notify you when the next step is ready.</div></div>
                    @else
                        <div class="flowb-empty"><i class="bi bi-clock-history"></i><div><strong>No action is required right now.</strong><br>Our team will notify you when your next action is ready.</div></div>
                    @endif
                </div>
            </section>

            </div>

            @include('examination-reply.partials.sidebar')
        </div>
    </div>

    <script>
        const closeStageDocumentsModal = (modal) => {
            modal?.classList.remove('is-visible');
            document.body.style.overflow = '';
        };

        document.querySelectorAll('[data-open-stage-documents]').forEach((button) => {
            button.addEventListener('click', () => {
                const modal = document.querySelector(`[data-stage-documents-modal="${button.dataset.openStageDocuments}"]`);
                if (!modal) return;

                modal.classList.add('is-visible');
                document.body.style.overflow = 'hidden';
                modal.querySelector('[data-close-stage-documents]')?.focus();
            });
        });

        document.querySelectorAll('[data-stage-documents-modal]').forEach((modal) => {
            modal.querySelectorAll('[data-stage-doc-tab]').forEach((tab) => {
                tab.addEventListener('click', () => {
                    modal.querySelectorAll('[data-stage-doc-tab]').forEach((candidate) => {
                        const isActive = candidate === tab;
                        candidate.classList.toggle('is-active', isActive);
                        candidate.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    });
                    modal.querySelectorAll('[data-stage-doc-panel]').forEach((panel) => {
                        panel.hidden = panel.dataset.stageDocPanel !== tab.dataset.stageDocTab;
                    });
                });
            });
            modal.querySelectorAll('[data-close-stage-documents]').forEach((button) => {
                button.addEventListener('click', () => closeStageDocumentsModal(modal));
            });
            modal.addEventListener('click', (event) => {
                if (event.target === modal) closeStageDocumentsModal(modal);
            });
        });

        const closeStageConversationModal = (modal) => {
            modal?.classList.remove('is-visible');
            document.body.style.overflow = '';
        };

        document.querySelectorAll('[data-open-stage-conversation]').forEach((button) => {
            button.addEventListener('click', () => {
                const modal = document.querySelector(`[data-stage-conversation-modal="${button.dataset.openStageConversation}"]`);
                if (!modal) return;

                modal.classList.add('is-visible');
                document.body.style.overflow = 'hidden';
                modal.querySelector('[data-close-stage-conversation]')?.focus();
            });
        });

        document.querySelectorAll('[data-stage-conversation-modal]').forEach((modal) => {
            modal.querySelectorAll('[data-close-stage-conversation]').forEach((button) => {
                button.addEventListener('click', () => closeStageConversationModal(modal));
            });
            modal.addEventListener('click', (event) => {
                if (event.target === modal) closeStageConversationModal(modal);
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                document.querySelectorAll('[data-stage-documents-modal].is-visible').forEach(closeStageDocumentsModal);
                document.querySelectorAll('[data-stage-conversation-modal].is-visible').forEach(closeStageConversationModal);
            }
        });

        document.querySelectorAll('.flowb-file-native').forEach((input) => {
            input.addEventListener('change', () => {
                const card = input.closest('.flowb-upload-card');
                const label = card?.querySelector('[data-flowb-file-name]');
                const state = card?.querySelector('.flowb-upload-state');
                const count = input.files?.length || 0;
                const isReuploadCard = card?.classList.contains('is-reupload');

                if (!label || count === 0) {
                    card?.classList.remove('has-file');
                    if (label) label.textContent = 'No file chosen';
                    if (state) {
                        state.classList.remove('is-uploaded');
                        if (isReuploadCard) {
                            state.classList.add('is-reupload');
                            state.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Reupload requested by admin.';
                        } else {
                            state.classList.remove('is-reupload');
                            state.innerHTML = '<i class="bi bi-info-circle"></i> Accepted: PDF, JPG, PNG, DOC, DOCX.';
                        }
                    }
                    return;
                }

                label.textContent = count === 1 ? input.files[0].name : `${count} files selected`;
                card?.classList.add('has-file');
                if (state) {
                    state.classList.remove('is-reupload');
                    state.classList.add('is-uploaded');
                    state.innerHTML = '<i class="bi bi-check-circle-fill"></i> Ready to upload.';
                }
            });
        });
    </script>

    @if ($case->current_client_stage === $workflow::CLIENT_DRAFT_APPROVAL && $latestDraft && $latestDraft->status === 'client_approval_pending')
        <script>
            document.querySelectorAll('[data-open-exam-draft-modal]').forEach((button) => {
                button.addEventListener('click', () => {
                    const modal = document.querySelector(`[data-exam-draft-modal="${button.dataset.openExamDraftModal}"]`);
                    modal?.classList.add('is-visible');
                    (modal?.querySelector('textarea') || modal?.querySelector('button[type="submit"]'))?.focus();
                });
            });

            document.querySelectorAll('[data-exam-draft-modal]').forEach((modal) => {
                modal.querySelectorAll('[data-close-exam-draft-modal]').forEach((button) => {
                    button.addEventListener('click', () => modal.classList.remove('is-visible'));
                });
                modal.addEventListener('click', (event) => {
                    if (event.target === modal) modal.classList.remove('is-visible');
                });
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    document.querySelectorAll('[data-exam-draft-modal].is-visible').forEach((modal) => modal.classList.remove('is-visible'));
                }
            });
        </script>
    @endif

    @if ($case->current_client_stage === $workflow::CLIENT_PRICING_PAYMENT && $case->package_price && $case->payment_status !== 'paid')
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        <script>
            document.getElementById('payObjectionReply')?.addEventListener('click', async () => {
                const couponInputs = Array.from(document.querySelectorAll('input[name="objection_reply_discount_coupon_id"]'));
                const selectedCoupon = couponInputs.find((input) => input.checked);
                const disclaimer = document.getElementById('successDisclaimer');
                const disclaimerCard = disclaimer?.closest('.flowb-pay-card.disclaimer');
                if (!disclaimer?.checked) {
                    disclaimerCard?.classList.add('is-error');
                    disclaimerCard?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    disclaimer?.focus();
                    alert('Please accept the disclaimer before payment.');
                    return;
                }
                disclaimerCard?.classList.remove('is-error');
                const button = document.getElementById('payObjectionReply');
                const oldText = button.innerHTML;
                button.disabled = true;
                button.innerHTML = 'Creating order...';
                try {
                    const response = await fetch(@json(route('examination-reply.payment.create-order', $case)), {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()), 'X-Requested-With': 'XMLHttpRequest'},
                        body: JSON.stringify({success_disclaimer: true, discount_coupon_id: selectedCoupon?.value || null})
                    });
                    const order = await response.json();
                    if (order.status !== 'success') {
                        alert(order.message || 'Payment order could not be created.');
                        button.disabled = false;
                        button.innerHTML = oldText;
                        return;
                    }
                    new Razorpay({
                        key: order.key,
                        amount: order.amount,
                        currency: order.currency,
                        name: 'Legal Bruz',
                        description: 'Trademark Objection Reply',
                        order_id: order.order_id,
                        handler: async function (payment) {
                            const verify = await fetch(@json(route('examination-reply.payment.verify-signature', $case)), {
                                method: 'POST',
                                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()), 'X-Requested-With': 'XMLHttpRequest'},
                                body: JSON.stringify(payment)
                            });
                            const result = await verify.json();
                            if (result.status === 'success') window.location.href = result.redirect_url;
                            else alert(result.message || 'Payment verification failed.');
                        },
                        modal: {ondismiss: () => { button.disabled = false; button.innerHTML = oldText; }}
                    }).open();
                } catch (error) {
                    alert('Payment checkout could not be started. Please try again.');
                    button.disabled = false;
                    button.innerHTML = oldText;
                }
            });
            document.getElementById('successDisclaimer')?.addEventListener('change', (event) => {
                if (event.target.checked) {
                    event.target.closest('.flowb-pay-card.disclaimer')?.classList.remove('is-error');
                }
            });
            const objectionCouponInputs = Array.from(document.querySelectorAll('input[name="objection_reply_discount_coupon_id"]'));
            const objectionPayAmount = document.querySelector('[data-objection-pay-amount]');
            const updateObjectionPayAmount = () => {
                const selected = objectionCouponInputs.find((input) => input.checked);
                const amount = selected?.dataset.payableAmount || @json($objectionReplyPayableAmountForJs);
                if (objectionPayAmount) {
                    objectionPayAmount.textContent = Number(amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }
            };
            objectionCouponInputs.forEach((input) => input.addEventListener('change', updateObjectionPayAmount));
            updateObjectionPayAmount();
        </script>
    @endif
@endsection
