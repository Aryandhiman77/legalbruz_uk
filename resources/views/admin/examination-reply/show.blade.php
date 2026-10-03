@extends('layouts.app')

@section('content')
    @php
        $workflow = \App\Support\ExaminationReportReplyWorkflow::class;
        $displayTimezone = config('app.timezone', 'Europe/London');
        $formatDateTime = fn ($timestamp, string $format = 'd M Y, h:i A T') => $timestamp
            ? \Illuminate\Support\Carbon::parse($timestamp)->timezone($displayTimezone)->format($format)
            : null;
        $currentStatus = $case->current_admin_status;
        $displayAdminStatus = $currentStatus;
        $latestDocuments = $case->documents->sortByDesc('id')->values();
        if (
            $currentStatus === $workflow::ADMIN_EVIDENCE_SUBMITTED
            && $latestDocuments->where('uploaded_by', 'client')->where('review_status', 'reuploaded')->isNotEmpty()
        ) {
            $displayAdminStatus = 'Evidences Reuploaded';
        }
        if (in_array($currentStatus, [
            $workflow::ADMIN_PAYMENT_COMPLETED,
            $workflow::ADMIN_REPLY_DRAFTING,
            $workflow::ADMIN_DRAFT_UNDER_REVIEW,
            $workflow::ADMIN_CHANGES_REQUESTED,
        ], true)) {
            $displayAdminStatus = 'Draft Reply Upload';
        }
        $displayDocuments = $latestDocuments
            ->reject(function ($document) use ($latestDocuments) {
                if ($document->uploaded_by !== 'client') {
                    return false;
                }

                return $latestDocuments->contains(function ($candidate) use ($document) {
                    return $candidate->id > $document->id
                        && $candidate->uploaded_by === 'client'
                        && $candidate->document_type === $document->document_type;
                });
            })
            ->values();
        $selectableDocuments = $displayDocuments
            ->where('uploaded_by', 'client')
            ->reject(fn ($document) => in_array($document->review_status, ['reviewed', 'accepted', 'reupload_requested', 'needs_better_copy'], true));
        $clientDocuments = $displayDocuments->where('uploaded_by', 'client');
        $adminDocuments = $displayDocuments->where('uploaded_by', 'admin');
        $stageRank = $workflow::stageRank($case);
        $historiesByClientStage = $case->statusHistories->groupBy('new_client_stage');
        $includedServices = [
            'Examination Report review',
            'Legal objection analysis',
            'Reply drafting',
            'Evidence/document review',
            'Online filing with Trademark Registry',
            'Status tracking after filing',
        ];
        $addonOptions = ['Evidence Review', 'Case-law based reply', 'Similarity analysis', 'Hearing support'];
        $additionalDocumentsFor = fn (string $stageKey) => $case->documents
            ->where('uploaded_by', 'admin')
            ->where('stage_key', $stageKey)
            ->sortByDesc('id')
            ->values();
        $savedDraftReplyDocument = $additionalDocumentsFor('draft')
            ->first(fn ($document) => ($document->metadata['input_name'] ?? null) === 'draft_file' && $document->is_draft);
        $draftAdditionalDocuments = $additionalDocumentsFor('draft')
            ->reject(fn ($document) => ($document->metadata['input_name'] ?? null) === 'draft_file')
            ->values();
        $savedFilingAcknowledgmentDocument = $additionalDocumentsFor('filing')
            ->first(fn ($document) => ($document->metadata['input_name'] ?? null) === 'filing_acknowledgment' && $document->is_draft);
        $filingAdditionalDocuments = $additionalDocumentsFor('filing')
            ->reject(fn ($document) => ($document->metadata['input_name'] ?? null) === 'filing_acknowledgment')
            ->values();
        $registryAdditionalDocuments = $additionalDocumentsFor('registry_update')
            ->filter(fn ($document) => in_array($document->metadata['source'] ?? null, ['additional_documents', 'stage_draft'], true)
                && ($document->metadata['input_name'] ?? 'optional_documents') === 'optional_documents')
            ->values();
        $stageDraftPayloads = $case->stageDrafts
            ->mapWithKeys(fn ($draft) => [$draft->stage_key => $draft->payload ?? []])
            ->all();
        $shouldHydrateDrafts = ! $errors->any();
        $examDocumentView = fn ($document) => route('admin.examination-reply.document.view', $document);
        $examDocumentDestroy = fn ($document) => route('admin.examination-reply.document.destroy', $document);
        $savedObjectionTypes = old('objection_types', $case->objection_types ?? []);
        $savedSection9Reasons = old('section_9_reasons', $case->section_9_reasons ?? []);
        $savedSection11Details = $case->section_11_details ?? [];
        $savedFormalReasons = old('formal_objection_reasons', $case->formal_objection_reasons ?? []);
        $savedRequestedDocuments = old('requested_documents', $case->requestedDocuments->pluck('document_name')->all());
        $savedEvidenceRequired = old('evidence_required', $case->evidence_required ? '1' : '0');
        $currentEvidenceRequests = $case->requestedDocuments
            ->filter(fn ($requested) => $requested->stageRequest?->to_stage === $workflow::CLIENT_EVIDENCE_COLLECTION)
            ->groupBy(fn ($requested) => \Illuminate\Support\Str::lower(trim((string) $requested->document_name)))
            ->map(fn ($requests) => $requests->sortByDesc('id')->first())
            ->filter()
            ->values();
        $uploadedRequestedPaths = $currentEvidenceRequests
            ->where('is_uploaded_by_client', true)
            ->pluck('uploaded_file_path')
            ->filter()
            ->values();
        $currentStageEvidenceDocuments = $clientDocuments
            ->filter(fn ($document) => $uploadedRequestedPaths->contains($document->file_path))
            ->values();
        $currentStageEvidenceDocumentIds = $currentStageEvidenceDocuments->pluck('id');
        $generalDisplayDocuments = $displayDocuments
            ->reject(fn ($document) => $currentStageEvidenceDocumentIds->contains($document->id))
            ->values();
        $selectableEvidenceDocuments = $currentStageEvidenceDocuments
            ->reject(fn ($document) => in_array($document->review_status, ['reviewed', 'accepted', 'reupload_requested', 'needs_better_copy'], true))
            ->values();
        $selectableGeneralDocuments = $selectableDocuments
            ->reject(fn ($document) => $currentStageEvidenceDocumentIds->contains($document->id))
            ->values();
        $hasPendingEvidenceDocuments = $selectableEvidenceDocuments->isNotEmpty();
        $evidenceIntake = $case->evidence_intake ?? [];
        $evidenceIntakeFields = [
            'When did you start using this brand?' => $evidenceIntake['usage_status'] ?? null,
            'Date of First Use' => $evidenceIntake['first_use_date'] ?? null,
            'Is the brand used continuously?' => $evidenceIntake['used_continuously'] ?? null,
            'Annual Sales, if any' => $evidenceIntake['annual_sales'] ?? null,
            'Advertising Expenses, if any' => $evidenceIntake['advertising_expenses'] ?? null,
            'Has anyone objected to your brand before?' => $evidenceIntake['previous_objection'] ?? null,
            'Are you aware of similar brands in the market?' => $evidenceIntake['similar_brands_known'] ?? null,
        ];
    @endphp

    <style>
        .admin-err{max-width:1440px;margin:-10px auto 36px;padding:0 18px;color:#22324a}.admin-shell{display:grid;grid-template-columns:minmax(0,1fr) 430px;gap:22px;align-items:start}.admin-main{min-width:0}.admin-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:18px}.admin-head h1{font-size:1.55rem;font-weight:900;color:#102a4c;margin:0}.admin-sub{margin:6px 0 0;color:#607089;font-weight:700}.admin-badge{display:inline-flex;border-radius:999px;padding:8px 13px;background:#eaf1ff;color:#174ea6;font-weight:900;font-size:.82rem;white-space:nowrap}.admin-card,.stage-panel{background:#fff;border:1px solid #dfe8f4;border-radius:9px;box-shadow:0 12px 28px rgba(8,36,90,.06);overflow:hidden;margin-bottom:18px;padding:0!important}.stage-panel{position:sticky;top:16px}.admin-card-head,.stage-panel-head{background:#203e68;color:#fff;padding:16px 20px}.admin-card-head h2,.stage-panel-head h2{color:#fff!important;font-size:1.1rem;font-weight:900;margin:0}.admin-card-body,.stage-panel-body{padding:20px}.admin-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.admin-field{border:1px solid #edf1f7;border-radius:7px;background:#fbfdff;padding:12px}.admin-field span{display:block;color:#66758b;font-size:.75rem;font-weight:900;text-transform:uppercase}.admin-field strong{display:block;margin-top:4px;color:#1d2b41;overflow-wrap:anywhere}.admin-table{width:100%;border-collapse:collapse}.admin-table th{background:#f7f9fc;color:#202633;font-size:.76rem;text-transform:uppercase;font-weight:900;text-align:left;padding:10px}.admin-table td{border-top:1px solid #e6ebf2;padding:10px;color:#3f4b5d;vertical-align:middle}.admin-link{color:#2a9d8f;font-weight:900;text-decoration:none}.admin-btn{border:0;border-radius:7px;background:#2a9d8f;color:#fff!important;text-decoration:none;font-weight:900;min-height:40px;padding:0 14px;display:inline-flex;align-items:center;justify-content:center;gap:7px}.admin-btn.secondary{background:#203e68}.admin-btn.light{background:#e5eaf2;color:#203e68!important}.admin-btn.danger{background:#dc2626}.admin-input,.admin-select,.admin-textarea{width:100%;border:1px solid #d9e0ea;border-radius:7px;padding:9px 11px;min-height:40px}.admin-textarea{min-height:92px}.admin-label{display:block;color:#263c5c;font-weight:900;margin:12px 0 7px}.stage-summary-card{border:1px solid #dfe8f4;border-radius:8px;background:#fbfdff;padding:12px;margin-bottom:14px}.stage-summary-card span{display:block;color:#66758b;font-size:.74rem;font-weight:900;text-transform:uppercase}.stage-summary-card strong{display:block;color:#203e68;font-weight:900;margin-top:4px}.admin-checks{display:grid;gap:8px}.admin-checks label{border:1px solid #edf1f7;border-radius:7px;background:#fbfdff;padding:9px 10px;font-weight:800}.timeline-mini{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px}.timeline-mini div{border:1px solid #dfe8f4;border-radius:8px;background:#fbfdff;padding:10px;font-size:.8rem;font-weight:900;color:#66758b}.timeline-mini .done{background:#dcfce7;color:#15803d}.timeline-mini .active{background:#dbeafe;color:#1d4ed8}.note{border-left:4px solid #2a9d8f;background:#f2fbf8;border-radius:7px;padding:12px;color:#203e68;font-weight:700;line-height:1.5}.doc-actions{display:flex;gap:8px;flex-wrap:wrap}.stage-doc-list{display:grid;gap:8px;margin-top:10px}.stage-doc-row{border:1px solid #e6edf7;border-radius:7px;background:#fff;padding:9px}.stage-doc-row strong{display:block;color:#203e68;overflow-wrap:anywhere}.stage-doc-row small{display:block;color:#66758b}.visibility-pill{display:inline-flex;border-radius:999px;padding:4px 8px;font-size:.72rem;font-weight:900;background:#eaf1ff;color:#174ea6}.visibility-pill.admin{background:#f3f4f6;color:#4b5563}.mt-2{margin-top:.5rem}.mt-3{margin-top:1rem}.mb-3{margin-bottom:1rem}@media(max-width:1100px){.admin-shell{grid-template-columns:1fr}.stage-panel{position:static}.timeline-mini{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:640px){.admin-detail-grid{grid-template-columns:1fr}.admin-head{display:block}.admin-table{display:block;overflow-x:auto;white-space:nowrap}}
    </style>
    <style>
        @media (min-width:1101px) {
            .stage-panel{display:flex;max-height:calc(100vh - 32px);flex-direction:column}
            .stage-panel-head{flex:0 0 auto}
            .stage-panel-body{min-height:0;overflow-y:auto;overscroll-behavior-y:contain;scrollbar-gutter:stable;-webkit-overflow-scrolling:touch}
        }
    </style>
    <style>
        [data-exam-doc-card] .admin-card-head{display:flex;align-items:center;justify-content:space-between;gap:12px}.admin-select-btn{min-height:34px;border:1px solid rgba(255,255,255,.55);border-radius:7px;background:rgba(255,255,255,.12);color:#fff;font-weight:900;padding:0 14px}.admin-select-btn:hover{background:rgba(255,255,255,.2)}.admin-doc-select{display:none;margin-right:10px;transform:scale(1.12)}[data-exam-doc-card].is-selecting .admin-doc-select{display:inline-block}.admin-review-status{display:inline-flex;border-radius:999px;padding:6px 10px;font-size:.72rem;font-weight:900}.admin-review-status.is-uploaded{background:#fff7ed;color:#b45309}.admin-review-status.is-reviewed{background:#dcfce7;color:#15803d}.admin-review-status.is-reupload{background:#fee2e2;color:#b91c1c}.admin-review-status.is-admin{background:#eef2f7;color:#475569}.admin-doc-bulk-actions{display:none;align-items:center;gap:10px;justify-content:flex-end;margin-top:16px}.admin-doc-bulk-actions.is-visible{display:flex}.pending-doc-jump{width:100%;border:1px solid #c6d5ea;border-radius:8px;background:#eef6ff;color:#174ea6;font-size:.78rem;font-weight:900;min-height:34px;margin:-2px 0 12px;padding:0 10px;display:flex;align-items:center;justify-content:center;gap:6px}.pending-doc-jump:hover{background:#e1efff}.admin-review-modal{position:fixed;inset:0;z-index:3050;display:none;align-items:center;justify-content:center;background:rgba(15,35,60,.45);padding:18px}.admin-review-modal.is-visible{display:flex}.admin-review-dialog{width:min(520px,100%);border-radius:9px;background:#fff;box-shadow:0 24px 60px rgba(15,35,60,.28);overflow:hidden}.admin-review-dialog header{background:#203e68;color:#fff;padding:16px 20px}.admin-review-dialog header h3{margin:0;color:#fff!important;font-size:1.1rem;font-weight:900}.admin-review-dialog-body{padding:20px}.admin-review-dialog-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:14px}.admin-btn-light{background:#e5eaf2;color:#203e68!important}.admin-btn-light:hover{background:#d8e0eb;color:#203e68!important}@media(max-width:640px){.admin-doc-bulk-actions{align-items:stretch;flex-direction:column}.admin-doc-bulk-actions .admin-btn{width:100%}}
    </style>
    <style>
        .stage-panel-head{display:flex;align-items:center;justify-content:space-between;gap:10px}.stage-note-btn{position:relative;min-height:32px;border:1px solid rgba(255,255,255,.55);border-radius:7px;background:rgba(255,255,255,.12);color:#fff;font-size:.78rem;font-weight:900;padding:0 11px;display:inline-flex;align-items:center;gap:6px;white-space:nowrap}.stage-note-btn:hover{background:rgba(255,255,255,.22)}.stage-note-btn.has-note:after{content:"";position:absolute;top:-5px;right:-5px;width:11px;height:11px;border:2px solid #203e68;border-radius:50%;background:#dc2626}.exam-timeline{position:relative;display:grid;gap:12px}.exam-timeline:before{content:"";position:absolute;left:19px;top:18px;bottom:18px;width:2px;background:#dbe7f5}.exam-timeline-item{position:relative;display:grid;grid-template-columns:40px minmax(0,1fr);gap:12px}.exam-timeline-dot{position:relative;z-index:1;width:40px;height:40px;border-radius:50%;display:grid;place-items:center;background:#f1f5f9;border:1px solid #dbe7f5;color:#64748b;font-weight:900}.exam-timeline-item.done .exam-timeline-dot{background:#dcfce7;border-color:#bbf7d0;color:#15803d}.exam-timeline-item.active .exam-timeline-dot{background:#dbeafe;border-color:#bfdbfe;color:#1d4ed8}.exam-timeline-card{border:1px solid #e2eaf4;border-radius:10px;background:#fbfdff;padding:12px}.exam-timeline-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}.exam-timeline-head h3{margin:0;color:#203e68;font-size:1rem;font-weight:900}.exam-timeline-state{display:inline-flex;border-radius:999px;padding:5px 9px;background:#eef2f7;color:#475569;font-size:.68rem;font-weight:900;white-space:nowrap}.exam-timeline-item.done .exam-timeline-state{background:#dcfce7;color:#15803d}.exam-timeline-item.active .exam-timeline-state{background:#dbeafe;color:#1d4ed8}.exam-timeline-card p{margin:6px 0 0;color:#56657a;line-height:1.45;font-weight:650}.exam-stage-notes{display:grid;gap:7px;margin-top:10px}.exam-stage-note{border:1px solid #e5edf7;border-radius:8px;background:#fff;padding:9px 10px}.exam-stage-note span{display:inline-flex;border-radius:999px;padding:3px 7px;font-size:.66rem;font-weight:900;text-transform:uppercase}.exam-stage-note.admin span{background:#eaf1ff;color:#174ea6}.exam-stage-note.client span{background:#ecfdf5;color:#15803d}.exam-stage-note.system span{background:#f1f5f9;color:#475569}.exam-stage-note p{margin:5px 0 0;color:#26364f;white-space:pre-line;font-size:.84rem}.exam-stage-note small{display:block;margin-top:4px;color:#7a8798;font-size:.72rem}.stage-action-panel{border:1px solid #dfe8f4;border-radius:9px;background:#fbfdff;padding:12px;margin-bottom:14px}.stage-action-panel h3{margin:0 0 4px;color:#203e68;font-size:.98rem;font-weight:900}.stage-action-panel p{margin:0 0 10px;color:#66758b;font-size:.82rem;line-height:1.35;font-weight:650}.stage-row-list{display:grid;gap:8px}.stage-dynamic-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:center}.stage-dynamic-row.file-row{display:block;border:1px solid #e2eaf4;border-radius:8px;background:#fff;padding:10px}.stage-dynamic-row.file-row .file-row-head{display:flex;justify-content:space-between;gap:8px;margin-bottom:8px}.stage-remove-btn{min-height:36px;border:1px solid #fecaca;border-radius:7px;background:#fff;color:#b91c1c;font-weight:900;padding:0 10px}.stage-remove-btn:hover{background:#fee2e2}.stage-outline-btn{min-height:38px;border:1px dashed #7aa2f7;border-radius:8px;background:#fff;color:#2563eb;font-weight:850;padding:0 12px}.stage-panel .admin-actions{display:flex;gap:8px;flex-wrap:wrap}.admin-review-modal textarea{min-height:120px}@media(max-width:640px){.stage-note-btn span{display:none}.stage-note-btn{width:36px;justify-content:center}.exam-timeline-head{display:block}.exam-timeline-state{margin-top:6px}.stage-dynamic-row{grid-template-columns:1fr}}
    </style>
    <style>
        .client-timeline-card .admin-card-head{padding:11px 20px}.client-timeline-card .admin-card-head h2{font-size:.86rem}.client-timeline-card .admin-card-body{padding:12px 16px}.client-timeline-card .exam-timeline{gap:7px}.client-timeline-card .exam-timeline:before{left:15px;top:13px;bottom:13px}.client-timeline-card .exam-timeline-item{grid-template-columns:32px minmax(0,1fr);gap:7px}.client-timeline-card .exam-timeline-dot{width:32px;height:32px;font-size:.76rem}.client-timeline-card .exam-timeline-card{border-radius:8px;padding:8px 10px}.client-timeline-card .exam-timeline-head{gap:7px}.client-timeline-card .exam-timeline-head h3{font-size:.76rem;line-height:1.18}.client-timeline-card .exam-timeline-state{padding:3px 7px;font-size:.54rem}.client-timeline-card .exam-timeline-card p{margin-top:3px;font-size:.68rem;line-height:1.24;font-weight:650}.client-timeline-card .exam-stage-notes{gap:4px;margin-top:6px}.client-timeline-card .exam-stage-note{border-radius:7px;padding:6px 7px}.client-timeline-card .exam-stage-note span{padding:2px 6px;font-size:.52rem}.client-timeline-card .exam-stage-note p{margin-top:3px;font-size:.66rem;line-height:1.22}.client-timeline-card .exam-stage-note small{margin-top:2px;font-size:.58rem}.stage-panel-head{padding:13px 16px}.stage-panel-head h2{font-size:1rem}.stage-panel-body{padding:14px}.stage-note-btn{min-height:28px;border-radius:6px;font-size:.7rem;padding:0 9px;gap:5px}.stage-summary-card{padding:9px 10px;margin-bottom:10px}.stage-summary-card span{font-size:.66rem}.stage-summary-card strong{font-size:.86rem}.stage-action-panel{border-radius:8px;padding:10px;margin-bottom:10px}.stage-action-panel h3{font-size:.9rem}.stage-action-panel p{margin-bottom:8px;font-size:.76rem;line-height:1.3}.stage-row-list{gap:6px}.stage-dynamic-row{gap:6px}.stage-dynamic-row.file-row{border-radius:7px;padding:8px}.stage-dynamic-row.file-row .file-row-head{margin-bottom:6px}.stage-panel .admin-actions,.stage-panel .doc-actions{gap:6px}.stage-panel .admin-btn,.stage-panel .stage-outline-btn,.stage-panel .stage-remove-btn{min-height:31px;border-radius:6px;font-size:.74rem;padding:0 9px;gap:5px}.stage-panel .stage-outline-btn{min-height:32px}.stage-panel .admin-input,.stage-panel .admin-select{min-height:34px;padding:7px 9px;font-size:.82rem}.stage-panel .admin-textarea{min-height:74px;padding:7px 9px;font-size:.82rem}.stage-panel .admin-label{margin:9px 0 5px;font-size:.82rem}.stage-panel .admin-checks{gap:6px}.stage-panel .admin-checks label{padding:7px 8px;font-size:.8rem}.stage-panel .admin-additional-documents-header{margin:0 0 8px}.stage-panel .admin-additional-documents-header>div{min-width:0}.stage-panel .admin-document-empty{font-size:.72rem;line-height:1.25;margin:2px 0 0}.stage-panel .admin-document-row{border-radius:7px;margin-bottom:8px;padding:8px}.stage-panel .admin-document-row.is-new .admin-document-row-head{margin-bottom:6px}.stage-panel .admin-document-row-head{gap:8px}.stage-panel .admin-document-row-head strong{font-size:.8rem}.stage-panel .admin-document-remove{min-height:28px;border-radius:6px;font-size:.7rem;padding:0 8px}.stage-doc-list{gap:6px;margin-top:8px}.stage-doc-row{padding:7px 8px}.stage-doc-row strong{font-size:.82rem}.stage-doc-row small{font-size:.72rem}.visibility-pill{padding:3px 7px;font-size:.66rem}@media(max-width:640px){.client-timeline-card .admin-card-body{padding:10px}.client-timeline-card .exam-timeline-item{grid-template-columns:30px minmax(0,1fr)}.client-timeline-card .exam-timeline-dot{width:30px;height:30px}.client-timeline-card .exam-timeline:before{left:14px}.stage-note-btn{width:32px}}
    </style>
    <style>
        .registry-quick-title{margin:2px 0 9px;color:#203e68;font-size:.9rem;font-weight:900}
        .registry-quick-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}
        .registry-quick-option{display:block;margin:0;cursor:pointer}
        .registry-quick-option input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
        .registry-quick-card{min-height:48px;display:flex;align-items:center;gap:8px;border:1px solid #dfe8f4;border-radius:8px;background:#fbfdff;padding:8px;color:#203e68;font-size:.76rem;font-weight:850;line-height:1.2;transition:border-color .15s ease,background .15s ease,box-shadow .15s ease}
        .registry-quick-card i{width:28px;height:28px;flex:0 0 28px;display:grid;place-items:center;border-radius:50%;background:#eaf1ff;color:#2563eb;font-size:.85rem}
        .registry-quick-option input:focus-visible+.registry-quick-card{outline:2px solid #2563eb;outline-offset:2px}
        .registry-quick-option input:checked+.registry-quick-card{border-color:#2a9d8f;background:#effaf7;box-shadow:0 0 0 2px rgba(42,157,143,.13)}
        .registry-quick-option input:checked+.registry-quick-card i{background:#2a9d8f;color:#fff}
        .registry-quick-help{margin:7px 0 0;color:#66758b;font-size:.7rem;line-height:1.35}
        .registry-quick-submit{width:100%;margin-top:11px}
        .registry-select-helper{margin:9px 0 0;padding:8px 10px;border:1px dashed #c6d5ea;border-radius:8px;background:#f8fbff;color:#607089;font-size:.74rem;font-weight:750;text-align:center}
        .registry-conditional-fields{margin-top:10px;padding-top:3px}
        .registry-field-helper{display:block;margin-top:4px;color:#66758b;font-size:.68rem;line-height:1.35}
        .registry-request-list{display:grid;gap:7px;margin-top:7px}
        .registry-request-row{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:7px;align-items:center;padding:7px;border:1px solid #e2eaf4;border-radius:8px;background:#fbfdff}
        .registry-required-check{display:inline-flex;align-items:center;gap:5px;color:#40516b;font-size:.7rem;font-weight:800;white-space:nowrap}
        .registry-required-check input{width:15px;height:15px}
        .registry-request-remove{min-height:32px;border:1px solid #fecaca;border-radius:7px;background:#fff;color:#b91c1c;font-size:.7rem;font-weight:900;padding:0 8px}
        .registry-request-add{width:100%;margin-top:7px;border-style:dashed}
        [data-registry-update-fields][hidden],[data-registry-field-group][hidden],[data-hearing-location-field][hidden]{display:none!important}
        @media(max-width:420px){.registry-quick-grid{grid-template-columns:1fr}}
        @media(max-width:520px){.registry-request-row{grid-template-columns:1fr auto}.registry-required-check{grid-column:1}.registry-request-remove{grid-column:2;grid-row:1 / span 2}}
    </style>
    <style>
        .admin-err { margin-top: 0; }
        .admin-err > .admin-shell > .stage-panel { top: 90px; }
    </style>
    <div class="admin-err">
        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if (session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

        <div class="admin-head">
            <div>
                <h1>{{ $case->case_number }} · {{ $case->trademark_name }}</h1>
                <p class="admin-sub">Examination Report Reply / Trademark Objection Reply</p>
            </div>
            <span class="admin-badge">{{ $displayAdminStatus }}</span>
        </div>

        <div class="admin-shell">
            <div class="admin-main">
                <section class="admin-card">
                    <header class="admin-card-head"><h2>Linked Examination Report Reply Case</h2></header>
                    <div class="admin-card-body">
                        <div class="admin-detail-grid">
                            <div class="admin-field"><span>Applicant</span><strong>{{ $case->applicant_name }}</strong></div>
                            <div class="admin-field"><span>User</span><strong>{{ $case->user?->email ?: 'N/A' }}</strong></div>
                            <div class="admin-field"><span>Application</span><strong>{{ $case->application_number }} · Class {{ $case->trademark_class }}</strong></div>
                            <div class="admin-field"><span>Deadline</span><strong>{{ $case->reply_deadline->format('d M Y') }} · {{ $case->deadline_status_label }}</strong></div>
                            <div class="admin-field"><span>Client Stage</span><strong>{{ $case->current_client_stage }}</strong></div>
                            <div class="admin-field"><span>Risk</span><strong>{{ $case->risk_level ?: 'Not assessed' }}</strong></div>
                            <div class="admin-field"><span>Package</span><strong>{{ $case->package_type ?: 'Not assigned' }} {{ $case->package_price ? '· ₹' . number_format((float) $case->package_price, 2) : '' }}</strong></div>
                            <div class="admin-field"><span>Payment</span><strong>{{ ucfirst($case->payment_status) }}</strong></div>
                        </div>
                    </div>
                </section>

                <section class="admin-card client-timeline-card">
                    <header class="admin-card-head"><h2>Client Timeline</h2></header>
                    <div class="admin-card-body">
                        <div class="exam-timeline">
                            @foreach ($timeline as $index => $step)
                                @php
                                    $state = $index < $stageRank ? 'done' : ($index === $stageRank ? 'active' : 'pending');
                                    $stageNotes = $historiesByClientStage->get($step['stage_label'], collect());
                                @endphp
                                <div class="exam-timeline-item {{ $state }}">
                                    <div class="exam-timeline-dot"><i class="bi {{ $step['icon'] }}"></i></div>
                                    <div class="exam-timeline-card">
                                        <div class="exam-timeline-head">
                                            <h3>{{ $step['stage_label'] }}</h3>
                                            <span class="exam-timeline-state">{{ ucfirst($state) }}</span>
                                        </div>
                                        <p>{{ $step['description'] }}</p>
                                        @if($stageNotes->isNotEmpty())
                                            <div class="exam-stage-notes">
                                                @foreach($stageNotes as $history)
                                                    @php
                                                        $noteType = $history->changed_by === 'admin' ? 'admin' : ($history->changed_by === 'client' ? 'client' : 'system');
                                                    @endphp
                                                    <div class="exam-stage-note {{ $noteType }}">
                                                        <span>{{ $noteType === 'admin' ? 'Admin Note' : ($noteType === 'client' ? 'Client Note' : 'System Note') }}</span>
                                                        <p>{{ $history->note ?: 'Stage updated.' }}</p>
                                                        <small>{{ $formatDateTime($history->created_at) }} · {{ ucfirst($history->changed_by) }}</small>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="admin-card" data-exam-doc-card>
                    <header class="admin-card-head">
                        <h2>Documents</h2>
                        @if($selectableGeneralDocuments->isNotEmpty())
                            <button class="admin-select-btn" type="button" data-exam-doc-select-toggle>Select</button>
                        @endif
                    </header>
                    <div class="admin-card-body">
                        <form method="POST" action="{{ route('admin.examination-reply.documents.review', $case) }}" data-exam-doc-review-form>
                            @csrf
                            <input type="hidden" name="action" data-exam-doc-review-action>
                            <input type="hidden" name="note" data-exam-doc-review-note>
                            <table class="admin-table">
                                <thead><tr><th>Document</th><th>Uploaded By</th><th>Visibility</th><th>Review</th></tr></thead>
                                <tbody>
                                @forelse ($generalDisplayDocuments as $document)
                                    @php
                                        $reviewStatus = $document->review_status ?: ($document->uploaded_by === 'admin' ? 'admin_sent' : 'uploaded');
                                        $statusClass = match ($reviewStatus) {
                                            'reviewed', 'accepted' => 'is-reviewed',
                                            'reupload_requested', 'needs_better_copy' => 'is-reupload',
                                            'admin_sent' => 'is-admin',
                                            default => 'is-uploaded',
                                        };
                                        $statusLabel = match ($reviewStatus) {
                                            'reviewed', 'accepted' => 'Reviewed',
                                            'reupload_requested', 'needs_better_copy' => 'Reupload Requested',
                                            'reuploaded' => 'Reuploaded',
                                            'admin_sent' => 'Admin Sent',
                                            default => 'Uploaded',
                                        };
                                        $isSelectableDocument = $selectableGeneralDocuments->contains('id', $document->id);
                                    @endphp
                                    <tr>
                                        <td>
                                            @if($isSelectableDocument)
                                                <input class="admin-doc-select" type="checkbox" name="document_ids[]" value="{{ $document->id }}" data-exam-doc-checkbox>
                                            @endif
                                            <strong>{{ \Illuminate\Support\Str::headline($document->document_type) }}</strong><br>
                                            <small>{{ $document->original_name }}</small>
                                        </td>
                                        <td>{{ ucfirst($document->uploaded_by) }}</td>
                                        <td>{{ $document->visibility === 'client' ? 'Visible to Client' : 'Admin Only' }}</td>
                                        <td><span class="admin-review-status {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4">No documents uploaded.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                            <div class="admin-doc-bulk-actions" data-exam-doc-bulk-actions>
                                <button class="admin-btn" type="button" data-exam-doc-open-modal="reviewed">Mark Selected as Reviewed</button>
                                <button class="admin-btn danger" type="button" data-exam-doc-open-modal="reupload_requested">Request Reupload</button>
                            </div>
                        </form>
                    </div>
                </section>

                @if($currentStageEvidenceDocuments->isNotEmpty() || array_filter($evidenceIntakeFields))
                    <section class="admin-card" data-exam-doc-card data-client-evidence-card>
                        <header class="admin-card-head">
                            <h2>Client Submitted Evidences</h2>
                            @if($selectableEvidenceDocuments->isNotEmpty())
                                <button class="admin-select-btn" type="button" data-exam-doc-select-toggle>Select</button>
                            @endif
                        </header>
                        <div class="admin-card-body">
                            @if(array_filter($evidenceIntakeFields))
                                <div class="admin-detail-grid mb-3">
                                    @foreach($evidenceIntakeFields as $label => $value)
                                        @if(filled($value))
                                            <div class="admin-field">
                                                <span>{{ $label }}</span>
                                                <strong>{{ $value }}</strong>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            <form method="POST" action="{{ route('admin.examination-reply.documents.review', $case) }}" data-exam-doc-review-form>
                                @csrf
                                <input type="hidden" name="action" data-exam-doc-review-action>
                                <input type="hidden" name="note" data-exam-doc-review-note>
                                <table class="admin-table">
                                    <thead><tr><th>Evidence</th><th>Requested As</th><th>Review</th><th>Note</th></tr></thead>
                                    <tbody>
                                    @forelse ($currentStageEvidenceDocuments as $document)
                                        @php
                                            $reviewStatus = $document->review_status ?: 'uploaded';
                                            $statusClass = match ($reviewStatus) {
                                                'reviewed', 'accepted' => 'is-reviewed',
                                                'reupload_requested', 'needs_better_copy' => 'is-reupload',
                                                default => 'is-uploaded',
                                            };
                                            $statusLabel = match ($reviewStatus) {
                                                'reviewed', 'accepted' => 'Reviewed',
                                                'reupload_requested', 'needs_better_copy' => 'Reupload Requested',
                                                'reuploaded' => 'Reuploaded',
                                                default => 'Pending Review',
                                            };
                                            $isSelectableEvidence = $selectableEvidenceDocuments->contains('id', $document->id);
                                        @endphp
                                        <tr>
                                            <td>
                                                @if($isSelectableEvidence)
                                                    <input class="admin-doc-select" type="checkbox" name="document_ids[]" value="{{ $document->id }}" data-exam-doc-checkbox>
                                                @endif
                                                <strong>{{ $document->document_title ?: \Illuminate\Support\Str::headline($document->document_type) }}</strong><br>
                                                <small><a class="admin-link" href="{{ $examDocumentView($document) }}" target="_blank">{{ $document->original_name }}</a></small>
                                            </td>
                                            <td>{{ \Illuminate\Support\Str::headline($document->document_type) }}</td>
                                            <td><span class="admin-review-status {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                            <td>{{ $document->review_note ?: 'N/A' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4">No evidence documents submitted yet.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                                <div class="admin-doc-bulk-actions" data-exam-doc-bulk-actions>
                                    <button class="admin-btn" type="button" data-exam-doc-open-modal="reviewed">Mark Selected as Reviewed</button>
                                    <button class="admin-btn danger" type="button" data-exam-doc-open-modal="reupload_requested">Request Reupload</button>
                                </div>
                            </form>
                        </div>
                    </section>
                @endif

                <section class="admin-card">
                    <header class="admin-card-head"><h2>Status History</h2></header>
                    <div class="admin-card-body">
                        <table class="admin-table">
                            <thead><tr><th>Date</th><th>Status</th><th>Client Stage</th><th>Note</th><th>By</th></tr></thead>
                            <tbody>
                            @foreach ($case->statusHistories as $history)
                                <tr><td>{{ $formatDateTime($history->created_at, 'd M Y h:i A') }}</td><td>{{ $history->new_admin_status }}</td><td>{{ $history->new_client_stage }}</td><td>{{ $history->note }}</td><td>{{ ucfirst($history->changed_by) }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <aside class="stage-panel">
                <header class="stage-panel-head">
                    <h2>Stage Actions</h2>
                    <button class="stage-note-btn" type="button" data-global-note-open><i class="bi bi-journal-text"></i> <span>Stage Note</span></button>
                </header>
                <div class="stage-panel-body">
                    <div class="stage-summary-card"><span>Linked Case</span><strong>{{ $case->case_number }} · {{ $case->trademark_name }}</strong></div>
                    <div class="stage-summary-card"><span>Current Stage</span><strong>{{ $displayAdminStatus }}</strong></div>
                    @if($hasPendingEvidenceDocuments)
                        <button class="pending-doc-jump" type="button" data-scroll-client-evidences>
                            <i class="bi bi-arrow-down-circle"></i>
                            View Client Submitted Evidences
                        </button>
                    @elseif($selectableGeneralDocuments->isNotEmpty())
                        <button class="pending-doc-jump" type="button" data-scroll-pending-documents>
                            <i class="bi bi-arrow-down-circle"></i>
                            Review Pending Documents ({{ $selectableGeneralDocuments->count() }})
                        </button>
                    @endif

                    @if (in_array($currentStatus, [$workflow::ADMIN_REPORT_UNDER_REVIEW, $workflow::ADMIN_OBJECTION_TYPE_IDENTIFIED], true))
                        <form method="POST" action="{{ route('admin.examination-reply.objection', $case) }}" enctype="multipart/form-data">
                            @csrf
                            @include('admin.trademark-opposition.partials.additional-documents', [
                                'case' => $case,
                                'documents' => $additionalDocumentsFor('objection'),
                                'addButtonAttribute' => 'data-add-exam-objection-document',
                                'listAttribute' => 'data-exam-objection-document-list',
                                'templateAttribute' => 'data-exam-objection-document-template',
                                'rowAttribute' => 'data-exam-objection-document-row',
                                'removeAttribute' => 'data-remove-exam-objection-document',
                                'emptyAttribute' => 'data-exam-objection-document-empty',
                                'showVisibility' => true,
                                'showEmptyState' => false,
                                'label' => 'Additional Document',
                                'description' => 'Add optional client-visible or admin-only support files.',
                                'addButtonText' => 'Add Document',
                                'viewUrlResolver' => $examDocumentView,
                                'destroyUrlResolver' => $examDocumentDestroy,
                            ])
                            @error('optional_documents')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            @error('optional_documents.*')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            @php($oldObjectionTypes = old('objection_types', []))
                            <label class="admin-label">Objection Type</label>
                            <div class="admin-checks">
                                @foreach(['Section 9 Objection','Section 11 Objection','Formal Objection','Mixed Objection'] as $type)
                                    <label><input type="checkbox" name="objection_types[]" value="{{ $type }}" data-objection-type-checkbox @checked(in_array($type, $oldObjectionTypes, true))> {{ $type }}</label>
                                @endforeach
                            </div>
                            @php($oldPortalLabel = old('portal_label', 'Distinctiveness Objection'))
                            <label class="admin-label">Portal Label</label>
                            <select class="admin-select" name="portal_label" required data-portal-label-select>
                                @foreach(['Distinctiveness Objection','Similarity / Conflict Objection','Documentation / Formality Objection','Multiple Objections'] as $label)
                                    <option value="{{ $label }}" @selected($oldPortalLabel === $label)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="note mt-2" data-mixed-objection-helper @if(! in_array('Mixed Objection', $oldObjectionTypes, true)) style="display:none" @endif>
                                Mixed Objection means the Examination Report contains more than one objection type.
                            </div>
                            <div data-section-9-fields @if(! in_array('Section 9 Objection', $oldObjectionTypes, true)) style="display:none" @endif>
                                <label class="admin-label">Section 9 Reasons</label>
                                <div class="admin-checks">@foreach(['Descriptive','Generic','Non-distinctive','Common to trade','Quality / character / purpose indicating'] as $reason)<label><input type="checkbox" name="section_9_reasons[]" value="{{ $reason }}" @checked(in_array($reason, old('section_9_reasons', []), true))> {{ $reason }}</label>@endforeach</div>
                            </div>
                            <div data-section-11-fields @if(! in_array('Section 11 Objection', $oldObjectionTypes, true)) style="display:none" @endif>
                                <label class="admin-label">Similar Earlier Mark Name <small>(Required: No)</small></label><input class="admin-input" name="similar_mark_name" value="{{ old('similar_mark_name') }}">
                                <label class="admin-label">Earlier Application Number <small>(Required: No)</small></label><input class="admin-input" name="earlier_application_number" value="{{ old('earlier_application_number') }}">
                                <label class="admin-label">Similarity Note <small>(Recommended, not mandatory)</small></label><textarea class="admin-textarea" name="similarity_note">{{ old('similarity_note') }}</textarea>
                            </div>
                            <div data-formal-objection-fields @if(! in_array('Formal Objection', $oldObjectionTypes, true)) style="display:none" @endif>
                                <label class="admin-label">Formal Objection Reasons</label>
                                <div class="admin-checks">@foreach(['Wrong applicant details','Incorrect class','Vague goods/services','Missing user affidavit','Missing MSME/startup certificate','POA/authorization issue'] as $reason)<label><input type="checkbox" name="formal_objection_reasons[]" value="{{ $reason }}" @checked(in_array($reason, old('formal_objection_reasons', []), true))> {{ $reason }}</label>@endforeach</div>
                            </div>
                            <label class="admin-label">Admin Legal Note</label><textarea class="admin-textarea" name="admin_legal_note">{{ old('admin_legal_note') }}</textarea>
                            <label class="admin-label"><span data-client-visible-note-label>Client Visible Note</span></label><textarea class="admin-textarea" name="client_visible_note" data-client-visible-note>{{ old('client_visible_note') }}</textarea>
                            @php($evidenceRequiredOld = old('evidence_required', '0'))
                            <label class="admin-label">Evidence Required?</label><select class="admin-select" name="evidence_required" required data-evidence-required-select><option value="1" @selected($evidenceRequiredOld === '1')>Yes</option><option value="0" @selected($evidenceRequiredOld === '0')>No</option></select>
                            <div data-requested-evidence-documents @if($evidenceRequiredOld !== '1') style="display:none" @endif>
                                <label class="admin-label">Requested Evidence Documents</label>
                                @foreach ($evidenceTypes as $label)
                                    <label class="admin-label"><input type="checkbox" name="requested_documents[]" value="{{ $label }}" @checked(in_array($label, old('requested_documents', []), true))> {{ $label }}</label>
                                @endforeach
                            </div>
                            <label class="admin-label">Internal / Tracking Note</label><textarea class="admin-textarea" name="tracking_note">{{ old('tracking_note') }}</textarea>
                            <button class="admin-btn mt-2" type="submit" data-stage2-submit>Upload Details, Notify Client &amp; Move to Risk Assessment</button>
                        </form>
                    @elseif (in_array($currentStatus, [
                        $workflow::ADMIN_OBJECTION_TYPE_IDENTIFIED,
                        $workflow::ADMIN_EVIDENCE_REQUESTED,
                        $workflow::ADMIN_EVIDENCE_SUBMITTED,
                        $workflow::ADMIN_EVIDENCE_REVIEW_COMPLETED,
                    ], true) && blank($case->package_price))
                        <div class="stage-action-panel" data-previous-objection-editor>
                            <button class="admin-btn secondary" type="button" data-previous-objection-edit>Edit Objection Details</button>
                            <form method="POST" action="{{ route('admin.examination-reply.objection', $case) }}" enctype="multipart/form-data" data-previous-objection-form style="display:none">
                                @csrf
                                <input type="hidden" name="edit_previous_stage" value="1">
                                @include('admin.trademark-opposition.partials.additional-documents', [
                                    'case' => $case,
                                    'documents' => $additionalDocumentsFor('objection'),
                                    'addButtonAttribute' => 'data-add-exam-objection-document',
                                    'listAttribute' => 'data-exam-objection-document-list',
                                    'templateAttribute' => 'data-exam-objection-document-template',
                                    'rowAttribute' => 'data-exam-objection-document-row',
                                    'removeAttribute' => 'data-remove-exam-objection-document',
                                    'emptyAttribute' => 'data-exam-objection-document-empty',
                                    'showVisibility' => true,
                                    'showEmptyState' => false,
                                    'label' => 'Additional Document',
                                    'description' => 'Edit the support files originally saved with objection details.',
                                    'addButtonText' => 'Add Document',
                                    'viewUrlResolver' => $examDocumentView,
                                    'destroyUrlResolver' => $examDocumentDestroy,
                                ])
                                @error('optional_documents')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                                @error('optional_documents.*')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                                <label class="admin-label">Objection Type</label>
                                <div class="admin-checks">
                                    @foreach(['Section 9 Objection','Section 11 Objection','Formal Objection','Mixed Objection'] as $type)
                                        <label><input type="checkbox" name="objection_types[]" value="{{ $type }}" data-objection-type-checkbox @checked(in_array($type, $savedObjectionTypes, true))> {{ $type }}</label>
                                    @endforeach
                                </div>
                                <label class="admin-label">Portal Label</label>
                                <select class="admin-select" name="portal_label" required data-portal-label-select>
                                    @foreach(['Distinctiveness Objection','Similarity / Conflict Objection','Documentation / Formality Objection','Multiple Objections'] as $label)
                                        <option value="{{ $label }}" @selected(old('portal_label', $case->portal_label ?: 'Distinctiveness Objection') === $label)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="note mt-2" data-mixed-objection-helper @if(! in_array('Mixed Objection', $savedObjectionTypes, true)) style="display:none" @endif>
                                    Mixed Objection means the Examination Report contains more than one objection type.
                                </div>
                                <div data-section-9-fields @if(! in_array('Section 9 Objection', $savedObjectionTypes, true)) style="display:none" @endif>
                                    <label class="admin-label">Section 9 Reasons</label>
                                    <div class="admin-checks">@foreach(['Descriptive','Generic','Non-distinctive','Common to trade','Quality / character / purpose indicating'] as $reason)<label><input type="checkbox" name="section_9_reasons[]" value="{{ $reason }}" @checked(in_array($reason, $savedSection9Reasons, true))> {{ $reason }}</label>@endforeach</div>
                                </div>
                                <div data-section-11-fields @if(! in_array('Section 11 Objection', $savedObjectionTypes, true)) style="display:none" @endif>
                                    <label class="admin-label">Similar Earlier Mark Name <small>(Required: No)</small></label><input class="admin-input" name="similar_mark_name" value="{{ old('similar_mark_name', $savedSection11Details['similar_mark_name'] ?? '') }}">
                                    <label class="admin-label">Earlier Application Number <small>(Required: No)</small></label><input class="admin-input" name="earlier_application_number" value="{{ old('earlier_application_number', $savedSection11Details['earlier_application_number'] ?? '') }}">
                                    <label class="admin-label">Similarity Note <small>(Recommended, not mandatory)</small></label><textarea class="admin-textarea" name="similarity_note">{{ old('similarity_note', $savedSection11Details['similarity_note'] ?? $case->section_11_note) }}</textarea>
                                </div>
                                <div data-formal-objection-fields @if(! in_array('Formal Objection', $savedObjectionTypes, true)) style="display:none" @endif>
                                    <label class="admin-label">Formal Objection Reasons</label>
                                    <div class="admin-checks">@foreach(['Wrong applicant details','Incorrect class','Vague goods/services','Missing user affidavit','Missing MSME/startup certificate','POA/authorization issue'] as $reason)<label><input type="checkbox" name="formal_objection_reasons[]" value="{{ $reason }}" @checked(in_array($reason, $savedFormalReasons, true))> {{ $reason }}</label>@endforeach</div>
                                </div>
                                <label class="admin-label">Admin Legal Note</label><textarea class="admin-textarea" name="admin_legal_note">{{ old('admin_legal_note', $case->internal_tracking_note) }}</textarea>
                                <label class="admin-label"><span data-client-visible-note-label>Client Visible Note</span></label><textarea class="admin-textarea" name="client_visible_note" data-client-visible-note>{{ old('client_visible_note', $case->client_visible_note) }}</textarea>
                                <label class="admin-label">Evidence Required?</label><select class="admin-select" name="evidence_required" required data-evidence-required-select><option value="1" @selected($savedEvidenceRequired === '1')>Yes</option><option value="0" @selected($savedEvidenceRequired === '0')>No</option></select>
                                <div data-requested-evidence-documents @if($savedEvidenceRequired !== '1') style="display:none" @endif>
                                    <label class="admin-label">Requested Evidence Documents</label>
                                    @foreach ($evidenceTypes as $label)
                                        <label class="admin-label"><input type="checkbox" name="requested_documents[]" value="{{ $label }}" @checked(in_array($label, $savedRequestedDocuments, true))> {{ $label }}</label>
                                    @endforeach
                                </div>
                                <label class="admin-label">Internal / Tracking Note</label><textarea class="admin-textarea" name="tracking_note">{{ old('tracking_note') }}</textarea>
                                <div class="admin-actions mt-2">
                                    <button class="admin-btn" type="submit" data-stage2-submit>Update Objection Details</button>
                                    <button class="admin-btn light" type="button" data-previous-objection-cancel>Cancel</button>
                                </div>
                            </form>
                        </div>
                    @endif

                    @if (in_array($currentStatus, [$workflow::ADMIN_EVIDENCE_REVIEW_COMPLETED, $workflow::ADMIN_RISK_ASSESSMENT_COMPLETED], true))
                        <form method="POST" action="{{ route('admin.examination-reply.risk', $case) }}" enctype="multipart/form-data">
                            @csrf
                            @include('admin.trademark-opposition.partials.additional-documents', [
                                'case' => $case,
                                'documents' => $additionalDocumentsFor('risk'),
                                'addButtonAttribute' => 'data-add-exam-risk-document',
                                'listAttribute' => 'data-exam-risk-document-list',
                                'templateAttribute' => 'data-exam-risk-document-template',
                                'rowAttribute' => 'data-exam-risk-document-row',
                                'removeAttribute' => 'data-remove-exam-risk-document',
                                'emptyAttribute' => 'data-exam-risk-document-empty',
                                'showVisibility' => true,
                                'showEmptyState' => false,
                                'label' => 'Additional Document',
                                'description' => 'Attach client-visible or admin-only legal review support files.',
                                'addButtonText' => 'Add Document',
                                'viewUrlResolver' => $examDocumentView,
                                'destroyUrlResolver' => $examDocumentDestroy,
                            ])
                            @error('optional_documents')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            @error('optional_documents.*')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            <label class="admin-label">Risk Level</label>
                            <select class="admin-select" name="risk_level" required>
                                @foreach(['Low Risk','Medium Risk','High Risk'] as $riskLevel)
                                    <option value="{{ $riskLevel }}" @selected(old('risk_level', $case->risk_level) === $riskLevel)>{{ $riskLevel }}</option>
                                @endforeach
                            </select>
                            <label class="admin-label">Risk Reason</label><textarea class="admin-textarea" name="risk_reason" required>{{ old('risk_reason', $case->risk_reason) }}</textarea>
                            <label class="admin-label">Client Visible Risk Note</label><textarea class="admin-textarea" name="client_visible_risk_note">{{ old('client_visible_risk_note', $case->client_visible_risk_note) }}</textarea>
                            <label class="admin-label">Recommendation Note</label><textarea class="admin-textarea" name="recommendation_note">{{ old('recommendation_note', $case->recommendation_note) }}</textarea>
                            <label class="admin-label">Package Type</label>
                            <select class="admin-select" name="package_type" required>
                                @foreach(['Basic Objection Reply','Standard Objection Reply','Advanced Objection Reply','Custom Package'] as $type)
                                    <option value="{{ $type }}" @selected(old('package_type', $case->package_type ?: 'Basic Objection Reply') === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                            <label class="admin-label">Package Price</label><input class="admin-input" type="number" name="package_price" value="{{ old('package_price', $case->package_price) }}" required>
                            <label class="admin-label">Package Description</label><textarea class="admin-textarea" name="package_description" required>{{ old('package_description', $case->package_description ?: 'You are purchasing Trademark Objection Reply service. This includes examination report review, reply drafting, and online filing. It does not include hearing representation, opposition proceedings, appeal, rectification, or fresh trademark filing unless separately purchased.') }}</textarea>
                            <label class="admin-label">Included Services</label>
                            <div class="admin-checks">
                                @foreach($includedServices as $service)
                                    <label><input type="checkbox" name="included_services[]" value="{{ $service }}" @checked(in_array($service, old('included_services', $case->included_services ?: $includedServices), true))> {{ $service }}</label>
                                @endforeach
                            </div>
                            <label class="admin-label">Add-ons</label>
                            <div class="admin-checks">
                                @foreach($addonOptions as $addon)
                                    <label><input type="checkbox" name="add_ons[]" value="{{ $addon }}" @checked(in_array($addon, old('add_ons', $case->add_ons ?: []), true))> {{ $addon }}</label>
                                @endforeach
                            </div>
                            <label class="admin-label">Internal / Tracking Note</label><textarea class="admin-textarea" name="tracking_note">{{ old('tracking_note') }}</textarea>
                            <button class="admin-btn mt-2" type="submit">Save Legal Review & Request Payment</button>
                        </form>
                    @endif

                    @if (in_array($currentStatus, [$workflow::ADMIN_RISK_ASSESSMENT_COMPLETED, $workflow::ADMIN_PRICING_ASSIGNED], true))
                        <form method="POST" action="{{ route('admin.examination-reply.pricing', $case) }}" enctype="multipart/form-data">
                            @csrf
                            <label class="admin-label">Package Type</label><select class="admin-select" name="package_type" required>@foreach(['Basic Objection Reply','Standard Objection Reply','Advanced Objection Reply','Custom Package'] as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select>
                            <label class="admin-label">Package Price</label><input class="admin-input" type="number" name="package_price" value="{{ old('package_price', $case->package_price) }}" required>
                            <label class="admin-label">Package Description</label><textarea class="admin-textarea" name="package_description" required>{{ old('package_description', $case->package_description) }}</textarea>
                            <label class="admin-label">Included Services</label><div class="admin-checks">@foreach($includedServices as $service)<label><input type="checkbox" name="included_services[]" value="{{ $service }}" checked> {{ $service }}</label>@endforeach</div>
                            <label class="admin-label">Add-ons</label><div class="admin-checks">@foreach($addonOptions as $addon)<label><input type="checkbox" name="add_ons[]" value="{{ $addon }}"> {{ $addon }}</label>@endforeach</div>
                            <label class="admin-label">Internal / Tracking Note</label><textarea class="admin-textarea" name="tracking_note"></textarea>
                            <button class="admin-btn mt-2" type="submit">Assign Package & Notify Client</button>
                        </form>
                    @endif

                    @if (in_array($currentStatus, [$workflow::ADMIN_PAYMENT_COMPLETED, $workflow::ADMIN_REPLY_DRAFTING, $workflow::ADMIN_DRAFT_UNDER_REVIEW, $workflow::ADMIN_CLIENT_APPROVAL_PENDING, $workflow::ADMIN_CHANGES_REQUESTED], true))
                        <form method="POST" action="{{ route('admin.examination-reply.draft', $case) }}" enctype="multipart/form-data" data-stage-draft-button-text="Save as Draft">
                            @csrf
                            <label class="admin-label">Draft Reply Upload</label><input class="admin-input" type="file" name="draft_file" @required(!$savedDraftReplyDocument)>
                            @if($savedDraftReplyDocument)
                                <p class="admin-document-empty">
                                    Saved draft:
                                    <a class="admin-link" href="{{ $examDocumentView($savedDraftReplyDocument) }}" target="_blank">{{ $savedDraftReplyDocument->original_name }}</a>
                                    <span>· Choose another file to replace it.</span>
                                </p>
                            @endif
                            @error('draft_file')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            @include('admin.trademark-opposition.partials.additional-documents', [
                                'case' => $case,
                                'documents' => $draftAdditionalDocuments,
                                'addButtonAttribute' => 'data-add-draft-document',
                                'listAttribute' => 'data-draft-document-list',
                                'templateAttribute' => 'data-draft-document-template',
                                'rowAttribute' => 'data-draft-document-row',
                                'removeAttribute' => 'data-remove-draft-document',
                                'emptyAttribute' => 'data-draft-document-empty',
                                'label' => 'Attach Additional Documents',
                                'addButtonText' => 'Attach Additional Document',
                                'addButtonClass' => 'mt-2',
                                'editButtonText' => 'Edit Additional Documents',
                                'showVisibility' => true,
                                'showDocumentType' => false,
                                'showAttachmentNote' => false,
                                'showRemarks' => false,
                                'showSaveNote' => false,
                                'viewUrlResolver' => $examDocumentView,
                                'destroyUrlResolver' => $examDocumentDestroy,
                            ])
                            @error('optional_documents')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            @error('optional_documents.*')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            <label class="admin-label">Message to Client</label><textarea class="admin-textarea" name="message_to_client" placeholder="Add a note for the client reviewing this draft.">{{ old('message_to_client') }}</textarea>
                            <label class="admin-label">Internal / Tracking Note</label><textarea class="admin-textarea" name="tracking_note" placeholder="Visible only to the admin/legal team.">{{ old('tracking_note') }}</textarea>
                            <button class="admin-btn mt-2" type="submit">Upload Draft for Client Approval</button>
                        </form>
                    @endif

                    @if ($currentStatus === $workflow::ADMIN_READY_FOR_FILING)
                        <form method="POST" action="{{ route('admin.examination-reply.filing', $case) }}" enctype="multipart/form-data">
                            @csrf
                            <label class="admin-label">Filing Date</label><input class="admin-input" type="date" name="reply_filing_date" required>
                            <label class="admin-label">Filing Acknowledgment Upload</label><input class="admin-input" type="file" name="filing_acknowledgment" @required(!$savedFilingAcknowledgmentDocument)>
                            @if($savedFilingAcknowledgmentDocument)
                                <p class="admin-document-empty">
                                    Saved acknowledgment:
                                    <a class="admin-link" href="{{ $examDocumentView($savedFilingAcknowledgmentDocument) }}" target="_blank">{{ $savedFilingAcknowledgmentDocument->original_name }}</a>
                                    <span>· Choose another file to replace it.</span>
                                </p>
                            @endif
                            @error('filing_acknowledgment')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            @include('admin.trademark-opposition.partials.additional-documents', [
                                'case' => $case,
                                'documents' => $filingAdditionalDocuments,
                                'addButtonAttribute' => 'data-add-filing-document',
                                'listAttribute' => 'data-filing-document-list',
                                'templateAttribute' => 'data-filing-document-template',
                                'rowAttribute' => 'data-filing-document-row',
                                'removeAttribute' => 'data-remove-filing-document',
                                'emptyAttribute' => 'data-filing-document-empty',
                                'label' => 'Attach Additional Documents',
                                'addButtonText' => 'Attach Additional Document',
                                'addButtonClass' => 'mt-2',
                                'editButtonText' => 'Edit Additional Documents',
                                'showVisibility' => true,
                                'showDocumentType' => false,
                                'showAttachmentNote' => false,
                                'showRemarks' => false,
                                'showSaveNote' => false,
                                'viewUrlResolver' => $examDocumentView,
                                'destroyUrlResolver' => $examDocumentDestroy,
                            ])
                            @error('optional_documents')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            @error('optional_documents.*')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            <label class="admin-label">Registry Filing Note</label><textarea class="admin-textarea" name="registry_filing_note"></textarea>
                            <label class="admin-label">Internal / Tracking Note</label><textarea class="admin-textarea" name="tracking_note"></textarea>
                            <button class="admin-btn mt-2" type="submit">Mark Reply Filed & Notify Client</button>
                        </form>
                    @endif

                    @if (in_array($currentStatus, [$workflow::ADMIN_AWAITING_REGISTRY_REVIEW, $workflow::ADMIN_FURTHER_ACTION_REQUIRED], true))
                        <form method="POST" action="{{ route('admin.examination-reply.registry-update', $case) }}" enctype="multipart/form-data" data-no-stage-draft data-registry-update-form>
                            @csrf
                            <h3 class="registry-quick-title">What update did you receive?</h3>
                            <div class="registry-quick-grid">
                                @foreach([
                                    ['Accepted', 'bi-check-circle'],
                                    ['Accepted & Advertised', 'bi-megaphone'],
                                    ['Hearing Issued', 'bi-calendar-event'],
                                    ['Further Clarification Required', 'bi-question-circle'],
                                    ['Application Abandoned', 'bi-x-circle'],
                                    ['Still Waiting', 'bi-hourglass-split'],
                                ] as [$updateType, $updateIcon])
                                    <label class="registry-quick-option">
                                        <input type="radio" name="registry_update_type" value="{{ $updateType }}" @checked(old('registry_update_type') === $updateType) required>
                                        <span class="registry-quick-card"><i class="bi {{ $updateIcon }}"></i><span>{{ $updateType }}</span></span>
                                    </label>
                                @endforeach
                            </div>
                            @error('registry_update_type')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                            <p class="registry-select-helper" data-registry-select-helper>Select a Registry update to continue.</p>

                            <div class="registry-conditional-fields" data-registry-update-fields hidden>
                                <div data-registry-field-group="registry-document" hidden>
                                    <label class="admin-label">Attach Registry Document / Screenshot <small>(Optional)</small></label>
                                    <input class="admin-input" type="file" name="registry_document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                    <small class="registry-field-helper" data-registry-document-helper></small>
                                </div>

                                <div data-registry-field-group="hearing" hidden>
                                    <label class="admin-label">Hearing Notice Upload <small>(Required)</small></label>
                                    <input class="admin-input" type="file" name="hearing_notice" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                    @error('hearing_notice')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                                    <small class="registry-field-helper">Upload the hearing notice issued by the Trademark Registry.</small>
                                    <label class="admin-label">Hearing Date &amp; Time <small>(Optional)</small></label>
                                    <input class="admin-input" type="datetime-local" name="hearing_datetime" value="{{ old('hearing_datetime', $case->hearing_date ? $case->hearing_date->format('Y-m-d') . 'T' . substr((string) ($case->hearing_time ?: '00:00'), 0, 5) : '') }}">
                                </div>

                                <div data-registry-field-group="clarification" hidden>
                                    <label class="admin-label">Request Documents From Client <small>(Recommended)</small></label>
                                    <div class="registry-request-list" data-registry-request-list>
                                        @foreach(old('clarification_documents', []) as $requestIndex => $requestedDocument)
                                            <div class="registry-request-row" data-registry-request-row>
                                                <input class="admin-input" name="clarification_documents[{{ $requestIndex }}][name]" maxlength="120" placeholder="e.g. User affidavit, corrected applicant details" value="{{ $requestedDocument['name'] ?? '' }}">
                                                <label class="registry-required-check"><input type="hidden" name="clarification_documents[{{ $requestIndex }}][required]" value="0"><input type="checkbox" name="clarification_documents[{{ $requestIndex }}][required]" value="1" @checked((string)($requestedDocument['required'] ?? '1') === '1')> Required</label>
                                                <button class="registry-request-remove" type="button" data-registry-request-remove>Remove</button>
                                            </div>
                                        @endforeach
                                    </div>
                                    <button class="admin-btn light registry-request-add" type="button" data-registry-request-add><i class="bi bi-plus-circle"></i> Add Requested Document</button>
                                    <template data-registry-request-template>
                                        <div class="registry-request-row" data-registry-request-row>
                                            <input class="admin-input" name="clarification_documents[__INDEX__][name]" maxlength="120" placeholder="e.g. User affidavit, corrected applicant details">
                                            <label class="registry-required-check"><input type="hidden" name="clarification_documents[__INDEX__][required]" value="0"><input type="checkbox" name="clarification_documents[__INDEX__][required]" value="1" checked> Required</label>
                                            <button class="registry-request-remove" type="button" data-registry-request-remove>Remove</button>
                                        </div>
                                    </template>
                                </div>

                                <div data-registry-field-group="additional-documents" hidden>
                                    @include('admin.trademark-opposition.partials.additional-documents', [
                                        'case' => $case,
                                        'documents' => $registryAdditionalDocuments,
                                        'addButtonAttribute' => 'data-add-tracking-document',
                                        'listAttribute' => 'data-tracking-document-list',
                                        'templateAttribute' => 'data-tracking-document-template',
                                        'rowAttribute' => 'data-tracking-document-row',
                                        'removeAttribute' => 'data-remove-tracking-document',
                                        'emptyAttribute' => 'data-tracking-document-empty',
                                        'label' => 'Attach Additional Documents',
                                        'addButtonText' => 'Attach Additional Document',
                                        'addButtonClass' => 'mt-2',
                                        'editButtonText' => 'Edit Additional Documents',
                                        'showVisibility' => true,
                                        'showDocumentType' => false,
                                        'showAttachmentNote' => false,
                                        'showRemarks' => false,
                                        'showSaveNote' => false,
                                        'viewUrlResolver' => $examDocumentView,
                                        'destroyUrlResolver' => $examDocumentDestroy,
                                    ])
                                    @error('optional_documents')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                                    @error('optional_documents.*')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                                </div>

                                <label class="admin-label">Message to Client <small data-registry-message-required>(Optional)</small></label>
                                <textarea class="admin-textarea" name="message_to_client" data-registry-client-message>{{ old('message_to_client') }}</textarea>
                                @error('message_to_client')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror
                                <p class="registry-quick-help" data-registry-still-waiting-help hidden>Still Waiting does not notify the client unless you add a message.</p>
                                <label class="admin-label">Internal Note <small>(Optional)</small></label>
                                <textarea class="admin-textarea" name="tracking_note" placeholder="Add internal tracking note, if needed.">{{ old('tracking_note') }}</textarea>
                                <button class="admin-btn registry-quick-submit" type="submit" data-registry-submit disabled><i class="bi bi-send-check"></i> <span>Save Update</span></button>
                            </div>
                        </form>
                    @endif

                    @if ($currentStatus === $workflow::ADMIN_HEARING_ISSUED)
                        <form method="POST" action="{{ route('admin.examination-reply.hearing', $case) }}" enctype="multipart/form-data">
                            @csrf
                            <label class="admin-label">Hearing Notice Upload</label><input class="admin-input" type="file" name="hearing_notice">
                            <label class="admin-label">Hearing Date &amp; Time <small>(Optional)</small></label>
                            <input class="admin-input" type="datetime-local" name="hearing_datetime" value="{{ old('hearing_datetime', $case->hearing_date ? $case->hearing_date->format('Y-m-d') . 'T' . substr((string) ($case->hearing_time ?: '00:00'), 0, 5) : '') }}">
                            <label class="admin-label">Hearing Support Package Price</label><input class="admin-input" type="number" name="hearing_package_price">
                            <label class="admin-label">Message to Client</label><textarea class="admin-textarea" name="message_to_client"></textarea>
                            <label class="admin-label">Internal / Tracking Note</label><textarea class="admin-textarea" name="tracking_note"></textarea>
                            <button class="admin-btn mt-2" type="submit">Notify Hearing Issued</button>
                        </form>
                    @endif

                    @if (in_array($currentStatus, [$workflow::ADMIN_ACCEPTED, $workflow::ADMIN_ACCEPTED_ADVERTISED, $workflow::ADMIN_APPLICATION_ABANDONED, $workflow::ADMIN_HEARING_ISSUED], true))
                        <form method="POST" action="{{ route('admin.examination-reply.close', $case) }}" enctype="multipart/form-data" class="mt-3">
                            @csrf
                            <label class="admin-label">Final Outcome</label><select class="admin-select" name="final_outcome" required>@foreach(['Accepted','Accepted & Advertised','Hearing Required','Further Clarification Required','Application Abandoned','Other'] as $outcome)<option value="{{ $outcome }}">{{ $outcome }}</option>@endforeach</select>
                            <label class="admin-label">Final Registry Document</label><input class="admin-input" type="file" name="final_registry_document">
                            <label class="admin-label">Final Note to Client</label><textarea class="admin-textarea" name="final_note_to_client" required></textarea>
                            <label class="admin-label">Internal Closing Note</label><textarea class="admin-textarea" name="internal_closing_note"></textarea>
                            <button class="admin-btn secondary mt-2" type="submit">Close Matter & Notify Client</button>
                        </form>
                    @endif

                    @if ($currentStatus === $workflow::ADMIN_MATTER_CLOSED)
                        <div class="note">This matter is closed. Internal notes and documents remain available for the admin/legal team.</div>
                    @endif
                </div>
            </aside>
        </div>
    </div>

    <div class="admin-review-modal" data-exam-doc-review-modal>
        <div class="admin-review-dialog">
            <header><h3 data-exam-doc-modal-title>Review Documents</h3></header>
            <div class="admin-review-dialog-body">
                <p class="admin-sub" data-exam-doc-modal-copy>Confirm the selected document review action.</p>
                <label class="admin-label" data-exam-doc-modal-label>Review Note</label>
                <textarea class="admin-textarea" data-exam-doc-modal-note></textarea>
                <div class="admin-review-dialog-actions">
                    <button class="admin-btn admin-btn-light" type="button" data-exam-doc-modal-cancel>Cancel</button>
                    <button class="admin-btn" type="button" data-exam-doc-modal-submit>Submit</button>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-review-modal" data-global-note-modal>
        <div class="admin-review-dialog">
            <header><h3>Stage Note</h3></header>
            <div class="admin-review-dialog-body">
                <p class="admin-sub">Visible only to the admin/legal team. This note is saved with the current stage form when you submit that stage.</p>
                <label class="admin-label">Stage Note</label>
                <textarea class="admin-textarea" data-global-note-textarea placeholder="Write stage context, urgency, communication note, or special instruction.">{{ old('internal_client_note', $case->internal_client_note) }}</textarea>
                <div class="admin-review-dialog-actions">
                    <button class="admin-btn admin-btn-light" type="button" data-global-note-cancel>Cancel</button>
                    <button class="admin-btn" type="button" data-global-note-save>Attach Stage Note</button>
                </div>
            </div>
        </div>
    </div>

    @include('admin.trademark-opposition.partials.additional-documents-script')

    <script>
        (() => {
            const stageDraftRoute = @json(route('admin.examination-reply.stage-draft', $case));
            const draftPayloads = @json($shouldHydrateDrafts ? $stageDraftPayloads : []);
            const slug = (value) => String(value || '')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '') || 'stage';
            const stageKeyFromForm = (form) => {
                const existing = form.querySelector('input[name="stage_key"]')?.value;
                if (existing) return slug(existing);

                try {
                    const parts = new URL(form.action, window.location.href).pathname.split('/').filter(Boolean);
                    return slug(parts[parts.length - 1]);
                } catch (error) {
                    return 'stage';
                }
            };
            const fieldValues = (stageKey) => draftPayloads?.[stageKey]?.fields || {};
            const valueFor = (fields, name) => fields[name] ?? fields[name.replace(/\[\]$/, '')];
            const hydrateForm = (form, stageKey) => {
                const fields = fieldValues(stageKey);
                if (!fields || Object.keys(fields).length === 0) return;
                const fieldIndexes = {};

                Array.from(form.elements).forEach((field) => {
                    if (!field.name || ['_token', '_method', 'stage_key'].includes(field.name) || field.type === 'file') return;

                    const value = valueFor(fields, field.name);
                    if (value === undefined || value === null) return;

                    if (field.type === 'checkbox') {
                        field.checked = Array.isArray(value) ? value.includes(field.value) : Boolean(value);
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                        return;
                    }

                    if (field.type === 'radio') {
                        field.checked = String(value) === field.value;
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                        return;
                    }

                    const fieldIndex = fieldIndexes[field.name] || 0;
                    field.value = Array.isArray(value) ? value[fieldIndex] ?? '' : value;
                    fieldIndexes[field.name] = fieldIndex + 1;
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                });
            };

            document.querySelectorAll('.stage-panel-body form').forEach((form) => {
                if (form.hasAttribute('data-no-stage-draft')) return;

                const stageKey = stageKeyFromForm(form);
                let stageKeyInput = form.querySelector('input[name="stage_key"]');
                if (!stageKeyInput) {
                    stageKeyInput = document.createElement('input');
                    stageKeyInput.type = 'hidden';
                    stageKeyInput.name = 'stage_key';
                    form.appendChild(stageKeyInput);
                }
                stageKeyInput.value = stageKey;

                hydrateForm(form, stageKey);

                if (!form.querySelector('[data-save-stage-draft]')) {
                    const button = document.createElement('button');
                    button.className = 'admin-btn light mt-2';
                    button.type = 'submit';
                    button.textContent = form.dataset.stageDraftButtonText || 'Save Draft';
                    button.formAction = stageDraftRoute;
                    button.formMethod = 'post';
                    button.formNoValidate = true;
                    button.setAttribute('data-save-stage-draft', '');
                    form.appendChild(button);
                }
            });
        })();
    </script>

    <script>
        (() => {
            const globalNoteModal = document.querySelector('[data-global-note-modal]');
            const openGlobalNote = document.querySelector('[data-global-note-open]');
            const cancelGlobalNote = document.querySelector('[data-global-note-cancel]');
            const saveGlobalNote = document.querySelector('[data-global-note-save]');
            const noteTextarea = globalNoteModal?.querySelector('[data-global-note-textarea]');

            const currentStageForm = () => Array.from(document.querySelectorAll('.stage-panel-body form'))
                .find((form) => !form.closest('[data-global-note-modal]')
                    && !form.hasAttribute('data-exam-doc-review-form')
                    && !form.hasAttribute('data-previous-objection-form')
                    && !form.hasAttribute('data-additional-documents-form'));
            const stageNoteField = () => {
                const form = currentStageForm();
                if (!form) return null;

                const visibleNoteField = form.querySelector('textarea[name="admin_legal_note"], textarea[name="internal_legal_note"], textarea[name="tracking_note"], textarea[name="internal_closing_note"]');
                if (visibleNoteField) return visibleNoteField;

                let hiddenNoteField = form.querySelector('input[name="internal_client_note"]');
                if (!hiddenNoteField) {
                    hiddenNoteField = document.createElement('input');
                    hiddenNoteField.type = 'hidden';
                    hiddenNoteField.name = 'internal_client_note';
                    form.appendChild(hiddenNoteField);
                }

                return hiddenNoteField;
            };
            const syncStageNoteBadge = () => {
                const hasNote = Boolean(stageNoteField()?.value?.trim());
                openGlobalNote?.classList.toggle('has-note', hasNote);
                openGlobalNote?.setAttribute('aria-label', hasNote ? 'Stage Note attached' : 'Stage Note');
                openGlobalNote?.setAttribute('title', hasNote ? 'Stage Note attached' : 'Stage Note');
            };

            const closeGlobalNote = () => globalNoteModal?.classList.remove('is-visible');

            openGlobalNote?.addEventListener('click', () => {
                const field = stageNoteField();
                if (field && noteTextarea) noteTextarea.value = field.value || noteTextarea.value;
                globalNoteModal?.classList.add('is-visible');
                noteTextarea?.focus();
            });
            saveGlobalNote?.addEventListener('click', () => {
                const field = stageNoteField();
                if (field && noteTextarea) {
                    field.value = noteTextarea.value;
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                }
                syncStageNoteBadge();
                closeGlobalNote();
            });
            cancelGlobalNote?.addEventListener('click', closeGlobalNote);
            globalNoteModal?.addEventListener('click', (event) => {
                if (event.target === globalNoteModal) closeGlobalNote();
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeGlobalNote();
            });
            stageNoteField()?.addEventListener('input', syncStageNoteBadge);
            syncStageNoteBadge();
        })();
    </script>

    <script>
        (() => {
            const pendingJump = document.querySelector('[data-scroll-pending-documents]');
            const evidenceJump = document.querySelector('[data-scroll-client-evidences]');
            const cards = Array.from(document.querySelectorAll('[data-exam-doc-card]'));
            const modal = document.querySelector('[data-exam-doc-review-modal]');
            const modalTitle = modal?.querySelector('[data-exam-doc-modal-title]');
            const modalCopy = modal?.querySelector('[data-exam-doc-modal-copy]');
            const modalLabel = modal?.querySelector('[data-exam-doc-modal-label]');
            const modalNote = modal?.querySelector('[data-exam-doc-modal-note]');
            const modalSubmit = modal?.querySelector('[data-exam-doc-modal-submit]');
            const modalCancel = modal?.querySelector('[data-exam-doc-modal-cancel]');
            let pendingAction = null;
            let activeForm = null;
            let activeActionInput = null;
            let activeNoteInput = null;

            const closeModal = () => {
                modal?.classList.remove('is-visible');
                pendingAction = null;
                activeForm = null;
                activeActionInput = null;
                activeNoteInput = null;
            };

            const activateCardSelection = (card) => {
                const toggle = card.querySelector('[data-exam-doc-select-toggle]');
                if (toggle && !card.classList.contains('is-selecting')) {
                    window.setTimeout(() => toggle.click(), 350);
                }
            };

            cards.forEach((card) => {
                const form = card.querySelector('[data-exam-doc-review-form]');
                if (!form) return;

                const toggle = card.querySelector('[data-exam-doc-select-toggle]');
                const checkboxes = Array.from(card.querySelectorAll('[data-exam-doc-checkbox]'));
                const bulkActions = card.querySelector('[data-exam-doc-bulk-actions]');
                const actionInput = form.querySelector('[data-exam-doc-review-action]');
                const noteInput = form.querySelector('[data-exam-doc-review-note]');
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

                checkboxes.forEach((checkbox) => checkbox.addEventListener('change', refreshBulkActions));

                card.querySelectorAll('[data-exam-doc-open-modal]').forEach((button) => {
                    button.addEventListener('click', () => {
                        if (selectedCount() === 0) return;
                        pendingAction = button.dataset.examDocOpenModal;
                        activeForm = form;
                        activeActionInput = actionInput;
                        activeNoteInput = noteInput;
                        const isReupload = pendingAction === 'reupload_requested';
                        modalTitle.textContent = isReupload ? 'Request Document Reupload' : 'Mark Documents as Reviewed';
                        modalCopy.textContent = isReupload
                            ? 'Add the corrections the client must make before reuploading the selected documents.'
                            : 'Confirm that the selected documents have been reviewed. A note is optional.';
                        modalLabel.textContent = isReupload ? 'Changes Required' : 'Review Note';
                        modalNote.placeholder = isReupload ? 'Describe what needs to be corrected or replaced' : 'Optional review note';
                        modalNote.value = '';
                        modal?.classList.add('is-visible');
                        modalNote.focus();
                    });
                });
            });

            pendingJump?.addEventListener('click', () => {
                const card = document.querySelector('[data-exam-doc-card]:not([data-client-evidence-card])');
                card?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                if (card) activateCardSelection(card);
            });

            evidenceJump?.addEventListener('click', () => {
                const card = document.querySelector('[data-client-evidence-card]');
                card?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                if (card) activateCardSelection(card);
            });

            modalCancel?.addEventListener('click', closeModal);
            modal?.addEventListener('click', (event) => {
                if (event.target === modal) closeModal();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeModal();
            });

            modalSubmit?.addEventListener('click', () => {
                if (!pendingAction || !activeForm || !activeActionInput || !activeNoteInput) return;
                const note = modalNote.value.trim();
                if (pendingAction === 'reupload_requested' && note === '') {
                    modalNote.setCustomValidity('Please describe the changes required.');
                    modalNote.reportValidity();
                    modalNote.focus();
                    return;
                }

                modalNote.setCustomValidity('');
                const confirmed = window.confirm(
                    pendingAction === 'reupload_requested'
                        ? 'Ask the client to reupload the selected documents?'
                        : 'Mark the selected documents as reviewed?'
                );
                if (!confirmed) return;

                activeActionInput.value = pendingAction;
                activeNoteInput.value = note;
                modalSubmit.disabled = true;
                modalSubmit.textContent = 'Submitting...';
                HTMLFormElement.prototype.submit.call(activeForm);
            });
        })();
    </script>

    <script>
        (() => {
            document.querySelectorAll('.stage-panel-body form').forEach((form) => {
                const evidenceSelect = form.querySelector('[data-evidence-required-select]');
                const requestedDocuments = form.querySelector('[data-requested-evidence-documents]');
                const clientVisibleNote = form.querySelector('[data-client-visible-note]');
                const clientVisibleNoteLabel = form.querySelector('[data-client-visible-note-label]');
                const submitButton = form.querySelector('[data-stage2-submit]');
                if (!evidenceSelect || !requestedDocuments) return;

                const checkboxes = Array.from(requestedDocuments.querySelectorAll('input[type="checkbox"]'));
                const originalSubmitText = submitButton?.textContent;
                const syncEvidenceDocuments = () => {
                    const isRequired = evidenceSelect.value === '1';
                    requestedDocuments.style.display = isRequired ? '' : 'none';
                    if (clientVisibleNote) clientVisibleNote.required = isRequired;
                    if (clientVisibleNoteLabel) {
                        clientVisibleNoteLabel.textContent = isRequired ? 'Message to Client' : 'Client Visible Note';
                    }
                    if (submitButton && !form.hasAttribute('data-previous-objection-form')) {
                        submitButton.textContent = originalSubmitText || 'Upload Details, Notify Client & Move to Risk Assessment';
                    }
                    if (!isRequired) {
                        checkboxes.forEach((checkbox) => { checkbox.checked = false; });
                    }
                };

                evidenceSelect.addEventListener('change', syncEvidenceDocuments);
                syncEvidenceDocuments();
            });
        })();
    </script>

    <script>
        (() => {
            document.querySelectorAll('.stage-panel-body form').forEach((form) => {
                const portalLabel = form.querySelector('[data-portal-label-select]');
                const objectionCheckboxes = Array.from(form.querySelectorAll('[data-objection-type-checkbox]'));
                const section9Fields = form.querySelector('[data-section-9-fields]');
                const section11Fields = form.querySelector('[data-section-11-fields]');
                const formalFields = form.querySelector('[data-formal-objection-fields]');
                const mixedHelper = form.querySelector('[data-mixed-objection-helper]');
                if (!portalLabel || objectionCheckboxes.length === 0) return;

                let portalLabelWasEdited = form.hasAttribute('data-previous-objection-form');
                const selectedTypes = () => objectionCheckboxes
                    .filter((checkbox) => checkbox.checked)
                    .map((checkbox) => checkbox.value);
                const setBlockState = (block, isVisible) => {
                    if (!block) return;
                    block.style.display = isVisible ? '' : 'none';
                    block.querySelectorAll('input, textarea, select').forEach((input) => {
                        input.disabled = !isVisible;
                        if (!isVisible) {
                            if (input.type === 'checkbox' || input.type === 'radio') {
                                input.checked = false;
                            } else {
                                input.value = '';
                            }
                        }
                    });
                };
                const suggestedPortalLabel = (types) => {
                    const selectedCoreTypes = types.filter((type) => type !== 'Mixed Objection');
                    if (types.includes('Mixed Objection') || selectedCoreTypes.length > 1) return 'Multiple Objections';
                    if (selectedCoreTypes[0] === 'Section 9 Objection') return 'Distinctiveness Objection';
                    if (selectedCoreTypes[0] === 'Section 11 Objection') return 'Similarity / Conflict Objection';
                    if (selectedCoreTypes[0] === 'Formal Objection') return 'Documentation / Formality Objection';
                    return portalLabel.value;
                };
                const syncObjectionFields = () => {
                    const types = selectedTypes();
                    setBlockState(section9Fields, types.includes('Section 9 Objection'));
                    setBlockState(section11Fields, types.includes('Section 11 Objection'));
                    setBlockState(formalFields, types.includes('Formal Objection'));
                    if (mixedHelper) mixedHelper.style.display = types.includes('Mixed Objection') ? '' : 'none';

                    if (!portalLabelWasEdited) {
                        portalLabel.value = suggestedPortalLabel(types);
                    }
                };

                portalLabel.addEventListener('change', () => {
                    portalLabelWasEdited = true;
                });
                objectionCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', syncObjectionFields));
                syncObjectionFields();
            });

            document.querySelectorAll('[data-previous-objection-editor]').forEach((editor) => {
                const openButton = editor.querySelector('[data-previous-objection-edit]');
                const form = editor.querySelector('[data-previous-objection-form]');
                const cancelButton = editor.querySelector('[data-previous-objection-cancel]');
                openButton?.addEventListener('click', () => {
                    if (form) form.style.display = '';
                    openButton.style.display = 'none';
                });
                cancelButton?.addEventListener('click', () => {
                    if (form) form.style.display = 'none';
                    if (openButton) openButton.style.display = '';
                });
            });
        })();
    </script>

    <script>
        (() => {
            const form = document.querySelector('[data-registry-update-form]');
            if (!form) return;

            const radios = Array.from(form.querySelectorAll('input[name="registry_update_type"]'));
            const fields = form.querySelector('[data-registry-update-fields]');
            const selectHelper = form.querySelector('[data-registry-select-helper]');
            const submit = form.querySelector('[data-registry-submit]');
            const submitText = submit?.querySelector('span');
            const clientMessage = form.querySelector('[data-registry-client-message]');
            const messageRequired = form.querySelector('[data-registry-message-required]');
            const stillWaitingHelp = form.querySelector('[data-registry-still-waiting-help]');
            const registryDocumentHelper = form.querySelector('[data-registry-document-helper]');
            const hearingNotice = form.querySelector('input[name="hearing_notice"]');
            const requestList = form.querySelector('[data-registry-request-list]');
            const requestTemplate = form.querySelector('[data-registry-request-template]');
            const requestAdd = form.querySelector('[data-registry-request-add]');
            let requestIndex = 1000 + (requestList?.querySelectorAll('[data-registry-request-row]').length || 0);

            const configs = {
                'Accepted': {
                    groups: ['registry-document'],
                    button: 'Mark Accepted & Notify Client',
                    placeholder: 'Add a short note for the client, or leave blank to send default message.',
                    documentHelp: 'Upload acceptance proof, Registry screenshot, or order if available.',
                },
                'Accepted & Advertised': {
                    groups: ['registry-document'],
                    button: 'Mark Accepted & Advertised & Notify Client',
                    placeholder: 'Add a short note for the client, or leave blank to send default message.',
                    documentHelp: 'Upload journal advertisement proof, Registry screenshot, or acceptance update if available.',
                },
                'Hearing Issued': {
                    groups: ['hearing', 'additional-documents'],
                    button: 'Move to Hearing Required & Notify Client',
                    placeholder: 'Add hearing details or leave blank to send default message.',
                },
                'Further Clarification Required': {
                    groups: ['registry-document', 'clarification', 'additional-documents'],
                    button: 'Request Clarification & Notify Client',
                    placeholder: 'Explain what clarification or documents are needed from the client.',
                    documentHelp: 'Upload Registry clarification notice or screenshot if available.',
                    messageRequired: true,
                },
                'Application Abandoned': {
                    groups: ['registry-document'],
                    button: 'Mark Application Abandoned & Notify Client',
                    placeholder: 'Explain the abandonment update to the client.',
                    documentHelp: 'Upload abandonment order, Registry screenshot, or status proof if available.',
                    messageRequired: true,
                },
                'Still Waiting': {
                    groups: [],
                    button: 'Save Tracking Note',
                    placeholder: 'Optional client update. Leave blank if you do not want to notify the client.',
                    stillWaiting: true,
                },
            };

            const toggleGroup = (name, visible) => {
                const group = form.querySelector(`[data-registry-field-group="${name}"]`);
                if (!group) return;
                group.hidden = !visible;
                group.querySelectorAll('input, select, textarea, button').forEach((control) => {
                    control.disabled = !visible;
                });
            };

            const sync = () => {
                const selected = form.querySelector('input[name="registry_update_type"]:checked')?.value;
                const config = configs[selected];
                const hasSelection = Boolean(config);

                fields.hidden = !hasSelection;
                selectHelper.hidden = hasSelection;
                submit.disabled = !hasSelection;

                ['registry-document', 'hearing', 'clarification', 'additional-documents'].forEach((group) => {
                    toggleGroup(group, hasSelection && config.groups.includes(group));
                });

                if (!hasSelection) return;

                fields.querySelectorAll(':scope > label input, :scope > textarea, :scope > button').forEach((control) => {
                    control.disabled = false;
                });
                if (clientMessage) {
                    clientMessage.disabled = false;
                    clientMessage.required = Boolean(config.messageRequired);
                    clientMessage.placeholder = config.placeholder;
                }
                if (messageRequired) messageRequired.textContent = config.messageRequired ? '(Required)' : '(Optional)';
                if (registryDocumentHelper) registryDocumentHelper.textContent = config.documentHelp || '';
                if (stillWaitingHelp) stillWaitingHelp.hidden = !config.stillWaiting;
                if (hearingNotice) hearingNotice.required = selected === 'Hearing Issued';
                if (submitText) submitText.textContent = config.button;
            };

            requestAdd?.addEventListener('click', () => {
                if (!requestList || !requestTemplate || requestList.querySelectorAll('[data-registry-request-row]').length >= 10) return;
                const markup = requestTemplate.innerHTML.replaceAll('__INDEX__', String(requestIndex++));
                requestList.insertAdjacentHTML('beforeend', markup);
            });
            requestList?.addEventListener('click', (event) => {
                event.target.closest('[data-registry-request-remove]')?.closest('[data-registry-request-row]')?.remove();
            });
            radios.forEach((radio) => radio.addEventListener('change', sync));
            sync();
        })();
    </script>
@endsection
