@extends('layouts.app')

@section('content')
    @php
        $displayTimezone = config('app.timezone', 'Europe/London');
        $latestDocumentsByType = $case->documents->sortByDesc('id')->unique('document_type')->values();
        $latestDocumentIdsByType = $latestDocumentsByType->pluck('id', 'document_type');
        $documentTypeLabels = [
            'registry_status_screenshot' => 'Registry Status Screenshot',
            'notice_received' => 'Notice Received',
            'missed_hearing_notice' => 'Missed Hearing Notice',
            'tm_acknowledgment_receipt' => 'TM acknowledgment receipt',
            'status_screenshot' => 'Status screenshot',
            'authorization_letter' => 'Authorization letter',
            'pan_aadhaar_gst' => 'PAN/Aadhaar/GST (if amendment may happen)',
            'previous_notices' => 'Previous notices',
            'reply_copies' => 'Reply copies',
            'hearing_notices' => 'Hearing notices',
            'user_affidavit' => 'User affidavit',
            'previous_attorney_communication' => 'Previous attorney communication',
            'audit_supporting_document' => 'Audit Supporting Document',
        ];
        $applicationDocumentTypes = ['registry_status_screenshot', 'notice_received', 'missed_hearing_notice'];
        $caseDocumentTypes = [
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
        $mandatoryCaseDocumentTypes = [
            'tm_acknowledgment_receipt',
            'status_screenshot',
            'authorization_letter',
            'pan_aadhaar_gst',
        ];
        $verificationDocumentTypes = [...$applicationDocumentTypes, ...$caseDocumentTypes];
        $applicationDocuments = $case->documents
            ->whereIn('document_type', $applicationDocumentTypes)
            ->sortByDesc('id')
            ->values();
        $caseDocuments = $case->documents
            ->whereIn('document_type', $caseDocumentTypes)
            ->sortByDesc('id')
            ->values();
        $verificationDocuments = $case->documents
            ->whereIn('document_type', $verificationDocumentTypes)
            ->sortByDesc('id')
            ->values();
        $auditSupportingDocuments = $case->documents
            ->where('document_type', 'audit_supporting_document')
            ->sortByDesc('id')
            ->values();
        $latestVerificationDocumentsByType = $verificationDocuments->unique('document_type')->values();
        $latestVerificationDocumentIdsByType = $latestVerificationDocumentsByType->pluck('id', 'document_type');
        $latestCaseDocumentsByType = $caseDocuments->unique('document_type')->values();
        $documentsReadyForAudit = $latestVerificationDocumentsByType->isNotEmpty()
            && $latestVerificationDocumentsByType->every(fn ($document) => $document->status === 'verified');
        $canUploadAuditReport = $documentsReadyForAudit
            && $case->audit_payment_status === 'paid'
            && in_array($case->status, [
                \App\Support\StuckTrademarkWorkflow::AUDIT_IN_PROGRESS,
                \App\Support\StuckTrademarkWorkflow::AUDIT_REPORT_REUPLOAD_REQUESTED,
            ], true);
        $formatStatusValue = fn ($value) => $value ? ucwords(str_replace('_', ' ', $value)) : 'Not provided';
        $executionIsComplete = (bool) $case->execution_completed_at || $case->execution_sub_stage === 'execution_completed';
        $resolvedIsActive = in_array($case->status, [
            \App\Support\StuckTrademarkWorkflow::RESOLVED,
            \App\Support\StuckTrademarkWorkflow::CLOSED,
        ], true);
        $monitoringIsActive = ! $resolvedIsActive && ($executionIsComplete
            || in_array($case->status, [
                \App\Support\StuckTrademarkWorkflow::MONITORING,
            ], true));
        $displayStatusLabel = $executionIsComplete && $case->status === \App\Support\StuckTrademarkWorkflow::EXECUTION_ACTIVE
            ? \App\Support\StuckTrademarkWorkflow::label(\App\Support\StuckTrademarkWorkflow::MONITORING)
            : $case->status_label;
        $monitoringStatuses = ['Active', 'Completed'];
        $resolutionStatuses = ['Active', 'Completed'];
        $monitoringDocuments = $case->executionDocuments
            ->where('execution_stage', 'Monitoring & Updates')
            ->sortByDesc('id')
            ->values();
        $monitoringUpdates = $case->executionUpdates
            ->where('stage', 'Monitoring & Updates')
            ->sortByDesc('created_at')
            ->values();
        $resolutionDocuments = $case->executionDocuments
            ->where('execution_stage', 'Resolved & Closed')
            ->sortByDesc('id')
            ->values();
    @endphp

    <style>
        .recovery-review-page {
            color: #212529;
        }

        .recovery-review-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 28px;
        }

        .recovery-review-title {
            margin: 0 0 6px;
            color: #4a4a4f;
            font-size: 1.55rem;
            font-weight: 900;
            line-height: 1.15;
        }

        .recovery-review-subtitle {
            margin: 0;
            color: #5f6368;
            font-size: 1rem;
            font-weight: 700;
        }

        .recovery-review-nav {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 22px;
            flex-wrap: wrap;
        }

        .recovery-status-pill {
            display: inline-flex;
            align-items: center;
            min-height: 34px;
            padding: 7px 14px;
            border-radius: 9px;
            background: #0d6efd;
            color: #ffffff;
            font-weight: 900;
            line-height: 1;
            text-decoration: none;
        }

        .recovery-review-link {
            color: #2a9d8f;
            font-weight: 900;
            text-decoration: none;
        }

        .recovery-review-link:hover {
            color: #177c72;
            text-decoration: underline;
            text-underline-offset: 4px;
        }

        .recovery-back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 10px 24px;
            border: 1px solid #e1e5eb;
            border-radius: 8px;
            color: #4a4a4f;
            background: #f8f9fc;
            font-weight: 900;
            text-decoration: none;
        }

        .recovery-back-button:hover {
            color: #1d3557;
            background: #ffffff;
            text-decoration: none;
        }

        .admin-card-header {
            min-height: 74px;
            display: flex;
            align-items: center;
            padding: 20px 26px;
            background: #27466d;
            color: #ffffff;
        }

        .admin-card-header h1,
        .admin-card-header h2,
        .admin-card-header h3,
        .admin-card-header h4,
        .admin-card-header h5,
        .admin-card-header h6 {
            color: #ffffff !important;
            font-weight: 900;
        }

        .recovery-review-card {
            overflow: hidden;
            border: 0;
            border-radius: 9px;
            background: #ffffff;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.1);
        }

        .recovery-review-card .card-body {
            padding: 30px;
        }

        .data-block {
            height: 100%;
            padding: 18px 20px;
            border: 1px solid #e5edf5;
            border-radius: 12px;
            background: #f8fafc;
        }

        .data-label {
            display: block;
            margin-bottom: 8px;
            color: #6c757d;
            font-size: 0.8rem;
            font-weight: 900;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .data-value {
            color: #1d3557;
            font-size: 0.98rem;
            font-weight: 800;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .overview-section-title {
            margin: 26px 0 14px;
            color: #27466d;
            font-size: 0.92rem;
            font-weight: 950;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .overview-section-title:first-child {
            margin-top: 0;
        }

        .overview-document-list {
            display: grid;
            gap: 10px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .overview-document-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 12px 14px;
            border: 1px solid #e5edf5;
            border-radius: 10px;
            background: #f8fafc;
            color: #1d3557;
            font-weight: 850;
            text-decoration: none;
        }

        .overview-document-link:hover {
            color: #0d6efd;
            background: #ffffff;
            text-decoration: none;
        }

        .overview-document-review-link {
            color: #0d6efd;
            font-weight: 950;
        }

        .overview-document-meta {
            flex-shrink: 0;
            color: #6c757d;
            font-size: 0.8rem;
            font-weight: 800;
        }

        .document-action-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: nowrap;
        }

        .document-action-group form {
            margin: 0;
            flex: 0 0 auto;
        }

        .documents-verification-header {
            min-height: 74px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 20px 26px;
            background: #27466d;
            color: #ffffff;
        }

        .documents-verification-header h5 {
            margin: 0;
            color: #ffffff !important;
            font-weight: 900;
        }

        .documents-select-toggle {
            min-height: 36px;
            padding: 7px 14px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 8px;
            color: #ffffff;
            background: rgba(255, 255, 255, 0.1);
            font-weight: 900;
        }

        .documents-select-toggle:hover,
        .documents-select-toggle:focus {
            color: #1d3557;
            background: #ffffff;
        }

        .document-select-column,
        .document-select-cell {
            display: none;
            width: 44px;
        }

        .documents-bulk-select-mode .document-select-column,
        .documents-bulk-select-mode .document-select-cell {
            display: table-cell;
        }

        .documents-bulk-actions {
            display: none;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 14px 18px;
            border-bottom: 1px solid #e5edf5;
            background: #f8fafc;
        }

        .documents-bulk-actions.is-visible {
            display: flex;
        }

        .documents-bulk-actions-count {
            color: #1d3557;
            font-weight: 900;
        }

        .documents-bulk-actions-buttons {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .document-action-button {
            min-height: 32px;
            padding: 6px 10px !important;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 900;
            line-height: 1.1;
            white-space: nowrap;
        }

        .document-action-button.btn-danger {
            color: #ffffff;
            background: #dc3545;
            border-color: #dc3545;
        }

        .document-action-button.btn-danger:hover,
        .document-action-button.btn-danger:focus {
            color: #ffffff;
            background: #bb2d3b;
            border-color: #b02a37;
        }

        .document-verification-notice {
            display: grid;
            gap: 8px;
            margin-bottom: 18px;
            padding: 14px 16px;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            color: #1e3a5f;
            background: #eff6ff;
            font-size: 0.9rem;
            font-weight: 700;
            line-height: 1.45;
        }

        .document-verification-notice a {
            display: inline-flex;
            align-items: center;
            justify-self: start;
            color: #155eef;
            font-weight: 900;
            text-decoration: underline;
            text-underline-offset: 4px;
        }

        .document-verification-notice a:hover,
        .document-verification-notice a:focus {
            color: #0b4dcc;
        }

        .admin-stage-actions .card {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border: 0;
            border-radius: 9px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.1);
        }

        .admin-stage-actions .stage-actions-card {
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 40px);
        }

        .admin-stage-actions .card-header {
            flex: 0 0 auto;
            border-bottom: 0;
        }

        .admin-stage-actions .col-md-3,
        .admin-stage-actions .col-md-4,
        .admin-stage-actions .col-md-6,
        .admin-stage-actions .col-md-12 {
            width: 100%;
            flex: 0 0 100%;
        }

        .admin-stage-actions .table {
            font-size: 0.9rem;
        }

        .admin-stage-actions form.d-flex {
            align-items: stretch;
            flex-direction: column;
        }

        .admin-stage-actions form.d-flex .form-select,
        .admin-stage-actions form.d-flex .form-control,
        .admin-stage-actions form.d-flex .btn {
            max-width: none !important;
            width: 100%;
        }

        .stage-actions-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overscroll-behavior: contain;
            padding: 30px;
            scrollbar-gutter: stable;
        }

        .stage-actions-title {
            margin: 0;
            color: #1d3557;
            font-size: 1.25rem;
            font-weight: 900;
        }

        .stage-actions-divider {
            margin: 18px 0 20px;
        }

        .monitoring-optional-documents {
            display: grid;
            gap: 10px;
        }

        .monitoring-optional-document-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            border: 1px solid #dbe7f3;
            border-radius: 8px;
            background: #f8fafc;
        }

        .monitoring-optional-document-name {
            display: block;
            color: #1d3557;
            font-weight: 900;
            line-height: 1.25;
        }

        .monitoring-optional-document-meta {
            display: block;
            margin-top: 3px;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .monitoring-update-history {
            display: grid;
            gap: 10px;
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid #e5edf5;
        }

        .monitoring-update-history-title {
            margin: 0;
            color: #1d3557;
            font-size: 0.92rem;
            font-weight: 950;
            line-height: 1.2;
        }

        .monitoring-update-history-item {
            padding: 11px 12px;
            border: 1px solid #dbe7f3;
            border-radius: 8px;
            background: #f8fafc;
        }

        .monitoring-update-history-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 7px;
        }

        .monitoring-update-history-name {
            color: #1d3557;
            font-size: 0.84rem;
            font-weight: 950;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .monitoring-update-history-date {
            flex: 0 0 auto;
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .monitoring-update-history-note {
            margin: 0;
            padding: 9px 10px;
            border: 1px solid #facc15;
            border-radius: 7px;
            color: #854d0e;
            background: #fffbeb;
            font-size: 0.78rem;
            font-weight: 800;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .monitoring-update-history-documents {
            display: grid;
            gap: 8px;
            margin-top: 9px;
        }

        .monitoring-update-history-document {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 10px;
            border: 1px solid #d8eadf;
            border-radius: 7px;
            background: #f4fbf7;
        }

        .monitoring-update-history-document-name {
            color: #17324d;
            font-size: 0.78rem;
            font-weight: 900;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .final-audit-upload {
            position: relative;
            display: grid;
            place-items: center;
            min-height: 180px;
            padding: 28px;
            border: 2px dashed #b8c7dc;
            border-radius: 12px;
            background: #f8fbff;
            text-align: center;
            transition: border-color 0.18s ease, background 0.18s ease;
        }

        .final-audit-upload:hover,
        .final-audit-upload:focus-within {
            border-color: #0d6efd;
            background: #f1f7ff;
        }

        .final-audit-upload.has-file {
            border-color: #18a058;
            background: #effaf3;
        }

        .final-audit-upload.has-file:hover,
        .final-audit-upload.has-file:focus-within {
            border-color: #108548;
            background: #e9f8ef;
        }

        .final-audit-upload input[type="file"] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .final-audit-upload-icon {
            width: 54px;
            height: 54px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            border-radius: 999px;
            background: #eaf3ff;
            color: #0d6efd;
        }

        .final-audit-upload.has-file .final-audit-upload-icon {
            background: #dff6e8;
            color: #108548;
        }

        .final-audit-upload-icon svg {
            width: 24px;
            height: 24px;
            stroke-width: 2.35;
        }

        .final-audit-upload-title {
            display: block;
            color: #1d3557;
            font-size: 1rem;
            font-weight: 950;
        }

        .final-audit-upload-copy {
            display: block;
            margin-top: 5px;
            color: #667085;
            font-size: 0.88rem;
            font-weight: 750;
        }

        .final-audit-file-info {
            display: none;
            margin-top: 14px;
            padding: 12px 14px;
            border: 1px solid #b8e6ca;
            border-radius: 10px;
            background: #ffffff;
            color: #155f35;
            text-align: left;
        }

        .final-audit-upload.has-file .final-audit-file-info {
            display: block;
        }

        .final-audit-file-name {
            display: block;
            color: #0f5132;
            font-size: 0.94rem;
            font-weight: 950;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .final-audit-file-meta {
            display: block;
            margin-top: 4px;
            color: #267249;
            font-size: 0.82rem;
            font-weight: 800;
        }

        .audit-optional-documents {
            display: grid;
            gap: 10px;
        }

        .audit-optional-document-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid #d8e3ef;
            border-radius: 10px;
            background: #f8fafc;
        }

        .audit-optional-document-name {
            display: block;
            color: #1d3557;
            font-size: 0.92rem;
            font-weight: 900;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .audit-optional-document-meta {
            display: block;
            margin-top: 3px;
            color: #667085;
            font-size: 0.78rem;
            font-weight: 750;
        }

        .audit-optional-document-actions {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .audit-optional-document-fields {
            display: none;
        }

        .audit-optional-document-modal,
        .monitoring-optional-document-modal,
        .reupload-request-modal {
            z-index: 1095;
        }

        .modal-backdrop {
            z-index: 1085;
        }

        @media (max-width: 991.98px) {
            .admin-stage-actions .stage-actions-card {
                position: static;
                max-height: none;
            }

            .stage-actions-body {
                overflow: visible;
            }

            .recovery-review-top {
                flex-direction: column;
            }

            .recovery-review-nav {
                justify-content: flex-start;
                gap: 12px;
            }

            .document-action-group {
                align-items: stretch;
                flex-direction: column;
            }

            .documents-verification-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .document-action-button {
                width: 100%;
            }
        }
    </style>

    <div class="container-fluid py-4 recovery-review-page">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Please fix the highlighted fields.</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="recovery-review-top">
            <div>
                <h1 class="recovery-review-title">Application Review</h1>
                <p class="recovery-review-subtitle">Recovery Case #{{ $case->id }} | {{ $case->case_number }}</p>
            </div>
            <div class="recovery-review-nav">
                <span class="recovery-status-pill">{{ $displayStatusLabel }}</span>
                <a href="#submitted-data" class="recovery-review-link">Submitted Data</a>
                <a href="#case-documents" class="recovery-review-link">Documents</a>
                <a href="{{ route('admin.stuck-trademark.index') }}" class="recovery-back-button">Back</a>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card recovery-review-card mb-4" id="submitted-data">
                    <div class="card-header admin-card-header">
                        <h5 class="mb-0">Matter Overview</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="overview-section-title">Matter Summary</h6>
                        <div class="row g-3">
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Trademark</span><div class="data-value">{{ $case->trademark_name }}</div></div></div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Applicant</span><div class="data-value">{{ $case->applicant_name }}</div></div></div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Email</span><div class="data-value">{{ $case->email }}</div></div></div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Phone</span><div class="data-value">{{ $case->phone }}</div></div></div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Application Number</span><div class="data-value">{{ $case->application_number ?: 'Not provided' }}</div></div></div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Trademark Class</span><div class="data-value">{{ $case->trademark_class ?: 'Not provided' }}</div></div></div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Filing Date</span><div class="data-value">{{ $case->filing_date ? $case->filing_date->format('M d, Y') : 'Not provided' }}</div></div></div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Registry Status</span><div class="data-value">{{ $formatStatusValue($case->registry_status) }}</div></div></div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Submitted</span><div class="data-value">{{ $case->created_at->timezone($displayTimezone)->format('M d, Y h:i A') }}</div></div></div>
                        </div>

                        <h6 class="overview-section-title">Recovery Application Documents</h6>
                        @if ($applicationDocuments->count())
                            <ul class="overview-document-list">
                                @foreach ($applicationDocuments as $document)
                                    @php
                                        $isCurrentDocument = (int) ($latestDocumentIdsByType[$document->document_type] ?? 0) === (int) $document->id;
                                        $documentTypeLabel = $documentTypeLabels[$document->document_type] ?? ucwords(str_replace('_', ' ', $document->document_type));
                                    @endphp
                                    <li>
                                        <a href="{{ route('admin.stuck-trademark.document.view', $document) }}" target="_blank" class="overview-document-link">
                                            <span>
                                                <span class="overview-document-review-link">{{ $documentTypeLabel }}</span>
                                                <span class="d-block small text-muted">{{ $document->file_name }}{{ $isCurrentDocument ? '' : ' | Archived version' }}</span>
                                            </span>
                                            <span class="overview-document-meta">Review | {{ $document->created_at->timezone($displayTimezone)->format('d M Y') }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="data-block">
                                <span class="data-label">Documents</span>
                                <div class="data-value">No recovery application documents uploaded during intake.</div>
                            </div>
                        @endif

                        <h6 class="overview-section-title">Intake Data</h6>
                        <div class="row g-3">
                            <div class="col-12"><div class="data-block"><span class="data-label">Issues</span><div class="data-value">{{ $case->issue_summary ?: 'Not classified' }}</div></div></div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Status Unchanged Since</span><div class="data-value">{{ $case->status_unchanged_since ?: 'Not provided' }}</div></div></div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Objection / Hearing Notice</span><div class="data-value">{{ $formatStatusValue($case->objection_or_hearing_notice_received) }}</div></div></div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Attorney Explained Delay</span><div class="data-value">{{ $formatStatusValue($case->previous_attorney_explained_delay) }}</div></div></div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Correction Requirement</span><div class="data-value">{{ $formatStatusValue($case->correction_requirement_informed) }}</div></div></div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Previous Attorney / Agent</span><div class="data-value">{{ $case->prior_attorney_name ?: 'Not provided' }}</div></div></div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Previous Attorney Contact</span><div class="data-value">{{ $case->prior_attorney_contact ?: 'Not provided' }}</div></div></div>
                            <div class="col-12"><div class="data-block"><span class="data-label">Previous Attorney Details</span><div class="data-value">{{ $case->previous_attorney_details ?: 'Not provided' }}</div></div></div>
                            <div class="col-12"><div class="data-block"><span class="data-label">Notices Received</span><div class="data-value">{{ $case->notices_received ?: 'Not provided' }}</div></div></div>
                            <div class="col-12"><div class="data-block"><span class="data-label">Missed Hearing Notices</span><div class="data-value">{{ $case->hearing_notices_missed ?: 'Not provided' }}</div></div></div>
                            <div class="col-12"><div class="data-block"><span class="data-label">Problem</span><div class="data-value">{{ $case->problem_summary }}</div></div></div>
                        </div>

                        <h6 class="overview-section-title">Onboarding Data</h6>
                        <div class="row g-3">
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Business Name</span><div class="data-value">{{ $case->business_name ?: 'Not provided' }}</div></div></div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Previous Filing Mode</span><div class="data-value">{{ $formatStatusValue($case->filing_channel) }}</div></div></div>
                            <div class="col-12"><div class="data-block"><span class="data-label">Applicant Address</span><div class="data-value">{{ $case->applicant_address ?: 'Not provided' }}</div></div></div>
                            <div class="col-12"><div class="data-block"><span class="data-label">Onboarding Issues</span><div class="data-value">{{ collect($case->onboarding_issue_types ?? [])->map(fn ($issue) => ucwords(str_replace('_', ' ', $issue)))->implode(', ') ?: 'Not provided' }}</div></div></div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Received Notices</span><div class="data-value">{{ $formatStatusValue($case->received_notices) }}</div></div></div>
                            <div class="col-md-6"><div class="data-block"><span class="data-label">Replies Filed Earlier</span><div class="data-value">{{ $formatStatusValue($case->replies_filed_earlier) }}</div></div></div>
                        </div>
                    </div>
                </div>

                @php
                    $hasBulkVerificationDocuments = $verificationDocuments->contains(function ($document) use ($latestVerificationDocumentIdsByType) {
                        $isCurrentDocument = (int) ($latestVerificationDocumentIdsByType[$document->document_type] ?? 0) === (int) $document->id;
                        $documentStatus = $document->status === 'rejected' ? 'reupload_requested' : $document->status;

                        return $isCurrentDocument && !in_array($documentStatus, ['verified', 'reupload_requested'], true);
                    });
                @endphp
                <div class="card border-0 shadow-sm mb-4" id="case-documents" data-documents-verification>
                    <div class="documents-verification-header">
                        <h5>Documents Verification</h5>
                        @if ($hasBulkVerificationDocuments)
                            <button type="button" class="documents-select-toggle" data-documents-select-toggle>Select</button>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        @if ($verificationDocuments->count())
                            <div class="documents-bulk-actions" data-documents-bulk-actions>
                                <span class="documents-bulk-actions-count" data-documents-selected-count>0 documents selected</span>
                                <div class="documents-bulk-actions-buttons">
                                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#bulkVerifyDocumentsModal">
                                        Verify
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#bulkReuploadRequestModal">
                                        Request Reupload
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0 case-documents-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="document-select-column">Select</th>
                                            <th>File</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($verificationDocuments as $document)
                                            @php
                                                $isCurrentDocument = (int) ($latestVerificationDocumentIdsByType[$document->document_type] ?? 0) === (int) $document->id;
                                                $documentStatus = $document->status === 'rejected' ? 'reupload_requested' : $document->status;
                                                $documentStatusLabel = ucwords(str_replace('_', ' ', $documentStatus));
                                                $documentStatusBadge = match ($documentStatus) {
                                                    'verified' => 'success',
                                                    'reupload_requested', 'reuploaded' => 'warning text-dark',
                                                    default => 'secondary',
                                                };
                                                $documentTypeLabel = $documentTypeLabels[$document->document_type] ?? ucwords(str_replace('_', ' ', $document->document_type));
                                                $documentActionsAvailable = $isCurrentDocument && !in_array($documentStatus, ['verified', 'reupload_requested'], true);
                                            @endphp
                                            <tr>
                                                <td class="document-select-cell">
                                                    @if ($documentActionsAvailable)
                                                        <input type="checkbox" class="form-check-input" value="{{ $document->id }}" aria-label="Select {{ $documentTypeLabel }}" data-document-select-checkbox>
                                                    @endif
                                                </td>
                                                <td>
                                                    <a href="{{ route('admin.stuck-trademark.document.view', $document) }}" target="_blank">{{ $document->file_name }}</a>
                                                    @if (!$isCurrentDocument)
                                                        <div class="small text-muted">Archived version</div>
                                                    @endif
                                                </td>
                                                <td>{{ $documentTypeLabel }}</td>
                                                <td>
                                                    <span class="badge bg-{{ $documentStatusBadge }}">{{ $documentStatusLabel }}</span>
                                                    @if ($document->verification_notes)
                                                        <div class="small text-muted mt-1">{{ $document->verification_notes }}</div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="p-4 text-muted">No documents uploaded for verification yet.</div>
                        @endif
                    </div>
                </div>

                <div class="modal fade reupload-request-modal" id="bulkVerifyDocumentsModal" tabindex="-1" aria-labelledby="bulkVerifyDocumentsModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('admin.stuck-trademark.documents.bulk-verify', $case) }}" data-documents-bulk-verify-form>
                                @csrf
                                <input type="hidden" name="status" value="verified">
                                <span data-bulk-verify-selected-inputs></span>
                                <div class="modal-header">
                                    <h5 class="modal-title" id="bulkVerifyDocumentsModalLabel">Verify Selected Documents</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-muted mb-3">Selected documents will be marked as verified. Add a note if you want to send any update to the client.</p>
                                    <label class="form-label" for="bulkVerifyVerificationNotes">Note to client</label>
                                    <textarea id="bulkVerifyVerificationNotes" name="verification_notes" rows="4" class="form-control" placeholder="Example: Your submitted documents have been verified and the audit package is now being prepared."></textarea>
                                    <div class="form-text">This note will be saved in the activity and sent in the client notification.</div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success">Verify Selected</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal fade reupload-request-modal" id="bulkReuploadRequestModal" tabindex="-1" aria-labelledby="bulkReuploadRequestModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('admin.stuck-trademark.documents.bulk-verify', $case) }}" data-documents-bulk-reupload-form>
                                @csrf
                                <input type="hidden" name="status" value="reupload_requested">
                                <span data-bulk-reupload-selected-inputs></span>
                                <div class="modal-header">
                                    <h5 class="modal-title" id="bulkReuploadRequestModalLabel">Request Reupload</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-muted mb-3">Specify the cause of rejection or exactly what the client must upload again.</p>
                                    <label class="form-label" for="bulkReuploadVerificationNotes">Rejection reason / upload instructions</label>
                                    <textarea id="bulkReuploadVerificationNotes" name="verification_notes" rows="4" class="form-control" placeholder="Example: Please upload a clearer registry status screenshot showing the application number and latest status." required></textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Send Request</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header admin-card-header">
                        <h5 class="mb-0">Activity</h5>
                    </div>
                    <div class="card-body">
                        @forelse ($case->statusLogs as $log)
                            <div class="border-start border-3 ps-3 pb-3">
                                <div class="fw-bold">{{ $log->title }}</div>
                                <small class="text-muted">{{ $log->created_at->timezone($displayTimezone)->format('d M Y, h:i A') }} | {{ \App\Support\StuckTrademarkWorkflow::label($log->to_status) }}</small>
                                @if ($log->message)
                                    <p class="mb-0 mt-1">{{ $log->message }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="text-muted mb-0">No activity yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-lg-4 admin-stage-actions">
                <div class="card mb-4 stage-actions-card">
                    <div class="card-header admin-card-header">
                        <h5 class="mb-0">Stage Actions</h5>
                    </div>
                    <div class="card-body stage-actions-body">
                        @if ($hasBulkVerificationDocuments)
                            <div class="document-verification-notice">
                                <div>
                                    Document verification is pending. Go to the
                                    <a href="#case-documents" data-smooth-scroll>Document Verification</a>
                                    section and press Select to choose the document, then press either Verify or Reject based on your review.
                                </div>
                            </div>
                        @endif
                        @if ($canUploadAuditReport)
                            <h5 class="stage-actions-title">Finalized Audit Report Upload</h5>
                            <hr class="stage-actions-divider">
                            @if ($case->status === \App\Support\StuckTrademarkWorkflow::AUDIT_REPORT_REUPLOAD_REQUESTED && $case->audit_report_client_note)
                                <div class="alert alert-warning">
                                    <strong>Client requested audit report reupload:</strong>
                                    <div class="mt-1">{{ $case->audit_report_client_note }}</div>
                                </div>
                            @endif
                            <form action="{{ route('admin.stuck-trademark.audit-report', $case) }}" method="POST" enctype="multipart/form-data" data-loading-text="Publishing...">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label class="form-label">Finalized Audit Report Upload</label>
                                        <label class="final-audit-upload" data-final-audit-upload>
                                            <input type="file" name="audit_report" class="@error('audit_report') is-invalid @enderror" accept=".pdf,application/pdf" required data-final-audit-input>
                                            <span>
                                                <span class="final-audit-upload-icon" data-final-audit-icon><x-lucide-upload-cloud /></span>
                                                <span class="final-audit-upload-title" data-final-audit-title>Drag and drop the finalized PDF report here</span>
                                                <span class="final-audit-upload-copy" data-final-audit-copy>or click to browse. PDF only, max 15MB.</span>
                                                <span class="final-audit-file-info" data-final-audit-file-info>
                                                    <span class="final-audit-file-name" data-final-audit-file-name></span>
                                                    <span class="final-audit-file-meta" data-final-audit-file-meta></span>
                                                </span>
                                            </span>
                                        </label>
                                        @error('audit_report')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Optional Note</label>
                                        <textarea name="audit_note" rows="3" class="form-control @error('audit_note') is-invalid @enderror" placeholder="Add a client-facing note about this finalized report">{{ old('audit_note') }}</textarea>
                                        @error('audit_note')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Risk</label>
                                        <select name="risk_level" class="form-select @error('risk_level') is-invalid @enderror">
                                            <option value="">Not marked</option>
                                            @foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'] as $value => $label)
                                                <option value="{{ $value }}" @selected(old('risk_level', $case->risk_level) === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('risk_level')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Professional Recommendation</label>
                                        <textarea name="execution_recommendation" rows="4" class="form-control @error('execution_recommendation') is-invalid @enderror" placeholder="Add the recommended recovery action for the client">{{ old('execution_recommendation', $case->execution_recommendation) }}</textarea>
                                        @error('execution_recommendation')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Execution Package Fees</label>
                                        <input type="number" name="execution_fee" min="1" max="1000000" step="0.01" class="form-control @error('execution_fee') is-invalid @enderror" value="{{ old('execution_fee', $case->execution_fee) }}" placeholder="Enter execution package fee">
                                        <div class="form-text">This is the amount the client pays after approving the execution package.</div>
                                        @error('execution_fee')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-12">
                                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                            <label class="form-label mb-0">Optional Documents</label>
                                            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#auditOptionalDocumentModal">
                                                Attach Optional Document
                                            </button>
                                        </div>
                                        <div class="audit-optional-documents" data-optional-documents-list>
                                            @forelse ($auditSupportingDocuments as $document)
                                                @php
                                                    $sizeInKb = max(1, (int) ceil($document->file_size / 1024));
                                                    $displaySize = $sizeInKb >= 1024 ? number_format($sizeInKb / 1024, 1) . ' MB' : number_format($sizeInKb) . ' KB';
                                                @endphp
                                                <div class="audit-optional-document-item">
                                                    <span>
                                                        <span class="audit-optional-document-name">{{ $document->file_name }}</span>
                                                        <span class="audit-optional-document-meta">{{ $displaySize }} | Previously attached</span>
                                                    </span>
                                                    <a href="{{ route('admin.stuck-trademark.document.view', $document) }}" target="_blank" class="btn btn-outline-success btn-sm">View</a>
                                                </div>
                                            @empty
                                                <div class="text-muted small" data-optional-documents-empty>No optional documents attached yet.</div>
                                            @endforelse
                                        </div>
                                        <div data-optional-documents-fields></div>
                                        @error('optional_documents')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        @error('optional_documents.*')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        @error('optional_document_names.*')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success mt-3">Publish Finalized Report</button>
                                @if ($case->audit_report_path)
                                    <a href="{{ route('admin.stuck-trademark.audit-report.view', $case) }}" target="_blank" class="btn btn-outline-success mt-3">View Current Report</a>
                                @endif
                            </form>
                        @elseif ($monitoringIsActive)
                            <h5 class="stage-actions-title">Monitoring & Updates</h5>
                            <hr class="stage-actions-divider">
                            <form action="{{ route('admin.trademark-execution.monitoring-update', $case) }}" method="POST" enctype="multipart/form-data" data-monitoring-form>
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Monitoring Status</label>
                                    <select name="monitoring_status" class="form-select @error('monitoring_status') is-invalid @enderror" required data-monitoring-status-select>
                                        @foreach ($monitoringStatuses as $status)
                                            <option value="{{ $status }}" @selected(old('monitoring_status', in_array($case->monitoring_status, $monitoringStatuses, true) ? $case->monitoring_status : 'Active') === $status)>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                    @error('monitoring_status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-3" data-monitoring-active-only>
                                    <label class="form-label">Update Title</label>
                                    <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" placeholder="Example: Registry status checked">
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Client-facing Note</label>
                                    <textarea name="note" rows="4" class="form-control @error('note') is-invalid @enderror" placeholder="Add movement, follow-up or monitoring details">{{ old('note') }}</textarea>
                                    @error('note')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-3" data-monitoring-active-only>
                                    <label class="form-label">Next Follow-up</label>
                                    <input type="datetime-local" name="next_follow_up_at" class="form-control @error('next_follow_up_at') is-invalid @enderror" value="{{ old('next_follow_up_at', $case->next_follow_up_at?->timezone($displayTimezone)->format('Y-m-d\TH:i')) }}">
                                    @error('next_follow_up_at')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                        <label class="form-label mb-0">Optional Documents</label>
                                        <button type="button" class="btn btn-outline-primary btn-sm" data-monitoring-open-doc-modal>
                                            Attach Optional Document
                                        </button>
                                    </div>
                                    <div class="monitoring-optional-documents" data-monitoring-optional-doc-list>
                                        @forelse ($monitoringDocuments as $document)
                                            <div class="monitoring-optional-document-item">
                                                <span>
                                                    <span class="monitoring-optional-document-name">{{ $document->document_title }}</span>
                                                    <span class="monitoring-optional-document-meta">Previously attached</span>
                                                </span>
                                                <a href="{{ asset('storage/' . $document->file_path) }}" target="_blank" class="btn btn-outline-success btn-sm">View</a>
                                            </div>
                                        @empty
                                            <div class="text-muted small" data-monitoring-optional-doc-empty>No optional documents attached.</div>
                                        @endforelse
                                    </div>
                                    <div data-monitoring-optional-doc-fields></div>
                                    @error('optional_documents.*')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    @error('optional_document_names.*')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <button type="submit" class="btn btn-success w-100">Save Monitoring Update</button>
                            </form>
                            <div class="monitoring-update-history">
                                <h6 class="monitoring-update-history-title">Previously Sent Monitoring Updates</h6>
                                @forelse ($monitoringUpdates as $update)
                                    @php
                                        $nextOlderUpdate = $monitoringUpdates->get($loop->index + 1);
                                        $updateDocuments = $monitoringDocuments->filter(function ($document) use ($update, $nextOlderUpdate) {
                                            if ($document->created_at->gt($update->created_at->copy()->addSeconds(10))) {
                                                return false;
                                            }

                                            return ! $nextOlderUpdate || $document->created_at->gt($nextOlderUpdate->created_at->copy()->addSeconds(10));
                                        })->values();
                                    @endphp
                                    <div class="monitoring-update-history-item">
                                        <div class="monitoring-update-history-head">
                                            <span class="monitoring-update-history-name">{{ $update->title }}</span>
                                            <span class="monitoring-update-history-date">{{ $update->created_at->timezone($displayTimezone)->format('d M Y, h:i A') }}</span>
                                        </div>
                                        @if ($update->note)
                                            <p class="monitoring-update-history-note"><strong>Admin Note:</strong> {{ $update->note }}</p>
                                        @endif
                                        @if ($updateDocuments->isNotEmpty())
                                            <div class="monitoring-update-history-documents">
                                                @foreach ($updateDocuments as $document)
                                                    <div class="monitoring-update-history-document">
                                                        <span class="monitoring-update-history-document-name">{{ $document->document_title }}</span>
                                                        <a href="{{ asset('storage/' . $document->file_path) }}" target="_blank" class="btn btn-outline-success btn-sm">View</a>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-muted small mb-0">No monitoring updates sent yet.</p>
                                @endforelse
                            </div>
                        @elseif ($resolvedIsActive)
                            <h5 class="stage-actions-title">Resolved & Closed</h5>
                            <hr class="stage-actions-divider">
                            <form action="{{ route('admin.trademark-execution.resolution-report', $case) }}" method="POST" enctype="multipart/form-data" data-monitoring-form>
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="resolution_status" class="form-select @error('resolution_status') is-invalid @enderror" required>
                                        @foreach ($resolutionStatuses as $status)
                                            <option value="{{ $status }}" @selected(old('resolution_status', $case->status === \App\Support\StuckTrademarkWorkflow::CLOSED ? 'Completed' : 'Active') === $status)>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                    @error('resolution_status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Final Report</label>
                                    <input type="file" name="final_report" class="form-control @error('final_report') is-invalid @enderror" required>
                                    @error('final_report')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Client-facing Note</label>
                                    <textarea name="note" rows="4" class="form-control @error('note') is-invalid @enderror" placeholder="Add a final report note for the client">{{ old('note') }}</textarea>
                                    @error('note')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                        <label class="form-label mb-0">Optional Documents</label>
                                        <button type="button" class="btn btn-outline-primary btn-sm" data-monitoring-open-doc-modal>
                                            Attach Optional Document
                                        </button>
                                    </div>
                                    <div class="monitoring-optional-documents" data-monitoring-optional-doc-list>
                                        @forelse ($resolutionDocuments as $document)
                                            <div class="monitoring-optional-document-item">
                                                <span>
                                                    <span class="monitoring-optional-document-name">{{ $document->document_title }}</span>
                                                    <span class="monitoring-optional-document-meta">{{ $document->document_type ?: 'Document' }}</span>
                                                </span>
                                                <a href="{{ asset('storage/' . $document->file_path) }}" target="_blank" class="btn btn-outline-success btn-sm">View</a>
                                            </div>
                                        @empty
                                            <div class="text-muted small" data-monitoring-optional-doc-empty>No optional documents attached.</div>
                                        @endforelse
                                    </div>
                                    <div data-monitoring-optional-doc-fields></div>
                                    @error('optional_documents.*')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    @error('optional_document_names.*')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <button type="submit" class="btn btn-success w-100">Save Final Report</button>
                            </form>
                        @elseif ($case->status === \App\Support\StuckTrademarkWorkflow::EXECUTION_ACTIVE && $case->execution_payment_status === 'paid')
                            <h5 class="stage-actions-title">Execution Work</h5>
                            <hr class="stage-actions-divider">
                            <p class="text-muted">
                                Manage the revival execution substages, required actions, progress updates, monitoring, and optional client documents.
                            </p>
                            <a href="{{ route('admin.trademark-execution.show', $case) }}" class="btn btn-primary w-100">
                                Open Execution Work
                            </a>
                        @else
                            <p class="text-muted mb-0">No stage action is available right now.</p>
                        @endif
                    </div>
                </div>

            </div>

        </div>
    </div>

    @if ($canUploadAuditReport)
        <div class="modal fade audit-optional-document-modal" id="auditOptionalDocumentModal" tabindex="-1" aria-labelledby="auditOptionalDocumentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="auditOptionalDocumentModalLabel" data-optional-document-modal-title>Attach Optional Document</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="optionalAuditDocumentName">Document Name</label>
                            <input type="text" class="form-control" id="optionalAuditDocumentName" data-optional-document-name placeholder="Example: Registry status extract">
                        </div>
                        <div>
                            <label class="form-label" for="optionalAuditDocumentFile">Document File</label>
                            <input type="file" class="form-control" id="optionalAuditDocumentFile" data-optional-document-file>
                            <div class="form-text" data-optional-document-current-file></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" data-optional-document-attach>Attach Document</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($monitoringIsActive || $resolvedIsActive)
        <div class="modal fade monitoring-optional-document-modal" id="monitoringOptionalDocumentModal" tabindex="-1" aria-labelledby="monitoringOptionalDocumentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="monitoringOptionalDocumentModalLabel">Attach Optional Document</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="monitoringOptionalDocumentName">Document Name</label>
                        <input type="text" class="form-control mb-3" id="monitoringOptionalDocumentName" data-monitoring-doc-name placeholder="Example: Registry status screenshot">
                        <label class="form-label" for="monitoringOptionalDocumentFile">Document File</label>
                        <input type="file" class="form-control" id="monitoringOptionalDocumentFile" data-monitoring-doc-file>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" data-monitoring-doc-save>Attach Document</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const auditUpload = document.querySelector('[data-final-audit-upload]');
            const auditInput = document.querySelector('[data-final-audit-input]');
            const auditTitle = document.querySelector('[data-final-audit-title]');
            const auditCopy = document.querySelector('[data-final-audit-copy]');
            const auditFileName = document.querySelector('[data-final-audit-file-name]');
            const auditFileMeta = document.querySelector('[data-final-audit-file-meta]');
            const optionalDocumentModal = document.getElementById('auditOptionalDocumentModal');
            const optionalDocumentModalTitle = document.querySelector('[data-optional-document-modal-title]');
            const optionalDocumentName = document.querySelector('[data-optional-document-name]');
            const optionalDocumentAttach = document.querySelector('[data-optional-document-attach]');
            const optionalDocumentCurrentFile = document.querySelector('[data-optional-document-current-file]');
            const optionalDocumentsList = document.querySelector('[data-optional-documents-list]');
            const optionalDocumentsFields = document.querySelector('[data-optional-documents-fields]');
            let optionalDocumentFile = document.querySelector('[data-optional-document-file]');
            let editingOptionalDocument = null;
            const optionalDocumentEntries = [];
            const documentsVerification = document.querySelector('[data-documents-verification]');
            const documentsSelectToggle = document.querySelector('[data-documents-select-toggle]');
            const documentCheckboxes = [...document.querySelectorAll('[data-document-select-checkbox]')];
            const bulkActions = document.querySelector('[data-documents-bulk-actions]');
            const selectedCount = document.querySelector('[data-documents-selected-count]');
            const bulkVerifyInputs = document.querySelector('[data-bulk-verify-selected-inputs]');
            const bulkReuploadInputs = document.querySelector('[data-bulk-reupload-selected-inputs]');
            const bulkVerifyModal = document.getElementById('bulkVerifyDocumentsModal');
            const bulkVerifyNotes = document.getElementById('bulkVerifyVerificationNotes');
            const bulkReuploadModal = document.getElementById('bulkReuploadRequestModal');
            const bulkReuploadNotes = document.getElementById('bulkReuploadVerificationNotes');
            let documentsBulkSelectMode = false;

            document.querySelectorAll('[data-smooth-scroll]').forEach((link) => {
                link.addEventListener('click', (event) => {
                    const target = document.querySelector(link.getAttribute('href'));

                    if (!target) {
                        return;
                    }

                    event.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });

            const selectedDocumentIds = () => documentCheckboxes
                .filter((checkbox) => checkbox.checked)
                .map((checkbox) => checkbox.value);

            const renderBulkSelectedInputs = (target, ids) => {
                if (!target) {
                    return;
                }

                target.innerHTML = '';
                ids.forEach((id) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'document_ids[]';
                    input.value = id;
                    target.appendChild(input);
                });
            };

            const updateBulkDocumentActions = () => {
                const ids = selectedDocumentIds();
                const hasSelection = ids.length > 0;

                bulkActions?.classList.toggle('is-visible', documentsBulkSelectMode && hasSelection);

                if (selectedCount) {
                    selectedCount.textContent = `${ids.length} document${ids.length === 1 ? '' : 's'} selected`;
                }

                renderBulkSelectedInputs(bulkVerifyInputs, ids);
                renderBulkSelectedInputs(bulkReuploadInputs, ids);
            };

            const setDocumentsBulkSelectMode = (enabled) => {
                documentsBulkSelectMode = enabled;
                documentsVerification?.classList.toggle('documents-bulk-select-mode', enabled);

                if (documentsSelectToggle) {
                    documentsSelectToggle.textContent = enabled ? 'Cancel' : 'Select';
                    documentsSelectToggle.setAttribute('aria-pressed', enabled ? 'true' : 'false');
                }

                if (!enabled) {
                    documentCheckboxes.forEach((checkbox) => {
                        checkbox.checked = false;
                    });
                }

                updateBulkDocumentActions();
            };

            documentsSelectToggle?.addEventListener('click', () => {
                setDocumentsBulkSelectMode(!documentsBulkSelectMode);
            });

            documentCheckboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', updateBulkDocumentActions);
            });

            document.querySelector('[data-documents-bulk-verify-form]')?.addEventListener('submit', (event) => {
                if (selectedDocumentIds().length === 0) {
                    event.preventDefault();
                }
            });

            document.querySelector('[data-documents-bulk-reupload-form]')?.addEventListener('submit', (event) => {
                if (selectedDocumentIds().length === 0) {
                    event.preventDefault();
                }
            });

            bulkVerifyModal?.addEventListener('show.bs.modal', (event) => {
                if (selectedDocumentIds().length === 0) {
                    event.preventDefault();
                    return;
                }

                if (bulkVerifyNotes) {
                    bulkVerifyNotes.value = '';
                }
            });

            bulkReuploadModal?.addEventListener('show.bs.modal', (event) => {
                if (selectedDocumentIds().length === 0) {
                    event.preventDefault();
                    return;
                }

                if (bulkReuploadNotes) {
                    bulkReuploadNotes.value = '';
                }
            });

            const formatFileSize = (bytes) => {
                if (!bytes) {
                    return '0 KB';
                }

                const kb = bytes / 1024;

                if (kb < 1024) {
                    return `${Math.max(1, Math.round(kb))} KB`;
                }

                return `${(kb / 1024).toFixed(1)} MB`;
            };

            auditInput?.addEventListener('change', () => {
                const file = auditInput.files?.[0];

                if (!file || !auditUpload) {
                    auditUpload?.classList.remove('has-file');
                    return;
                }

                auditUpload.classList.add('has-file');

                if (auditTitle) {
                    auditTitle.textContent = 'Finalized PDF attached';
                }

                if (auditCopy) {
                    auditCopy.textContent = 'Ready to publish and email to the client.';
                }

                if (auditFileName) {
                    auditFileName.textContent = file.name;
                }

                if (auditFileMeta) {
                    auditFileMeta.textContent = `${formatFileSize(file.size)} | ${file.type || 'application/pdf'}`;
                }
            });

            const resetOptionalDocumentModal = () => {
                editingOptionalDocument = null;

                if (optionalDocumentModalTitle) {
                    optionalDocumentModalTitle.textContent = 'Attach Optional Document';
                }

                if (optionalDocumentName) {
                    optionalDocumentName.value = '';
                }

                if (optionalDocumentCurrentFile) {
                    optionalDocumentCurrentFile.textContent = '';
                }

                if (optionalDocumentFile) {
                    const replacement = optionalDocumentFile.cloneNode();
                    replacement.value = '';
                    optionalDocumentFile.replaceWith(replacement);
                    optionalDocumentFile = replacement;
                }

                if (optionalDocumentAttach) {
                    optionalDocumentAttach.textContent = 'Attach Document';
                }
            };

            const optionalDocumentModalInstance = optionalDocumentModal && window.bootstrap
                ? bootstrap.Modal.getOrCreateInstance(optionalDocumentModal)
                : null;

            const cleanupOptionalDocumentBackdrop = () => {
                document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('overflow');
                document.body.style.removeProperty('padding-right');
            };

            const closeOptionalDocumentModal = () => {
                optionalDocumentModalInstance?.hide();
                setTimeout(cleanupOptionalDocumentBackdrop, 180);
            };

            optionalDocumentModal?.addEventListener('hidden.bs.modal', () => {
                resetOptionalDocumentModal();
                cleanupOptionalDocumentBackdrop();
            });

            optionalDocumentModal?.addEventListener('show.bs.modal', (event) => {
                const editButton = event.relatedTarget?.closest('[data-optional-document-edit]');

                if (!editButton) {
                    resetOptionalDocumentModal();
                    return;
                }

                const entry = optionalDocumentEntries[Number(editButton.dataset.optionalDocumentEdit)];
                if (!entry) {
                    resetOptionalDocumentModal();
                    return;
                }

                editingOptionalDocument = entry;

                if (optionalDocumentModalTitle) {
                    optionalDocumentModalTitle.textContent = 'Edit Optional Document';
                }

                if (optionalDocumentName) {
                    optionalDocumentName.value = entry.nameInput.value;
                }

                if (optionalDocumentCurrentFile) {
                    optionalDocumentCurrentFile.textContent = `Current file: ${entry.fileName} (${entry.fileSize})`;
                }

                if (optionalDocumentAttach) {
                    optionalDocumentAttach.textContent = 'Update Document';
                }

                if (optionalDocumentFile) {
                    const replacement = optionalDocumentFile.cloneNode();
                    replacement.value = '';
                    optionalDocumentFile.replaceWith(replacement);
                    optionalDocumentFile = replacement;
                }
            });

            optionalDocumentAttach?.addEventListener('click', () => {
                const documentName = optionalDocumentName?.value.trim() || '';
                const selectedFile = optionalDocumentFile?.files?.[0] || null;

                if (!documentName) {
                    optionalDocumentName?.focus();
                    return;
                }

                if (!editingOptionalDocument && !selectedFile) {
                    optionalDocumentFile?.focus();
                    return;
                }

                const entry = editingOptionalDocument || {};
                let fileInputForEntry = entry.fileInput;

                if (selectedFile) {
                    fileInputForEntry = optionalDocumentFile;
                    fileInputForEntry.name = 'optional_documents[]';
                    fileInputForEntry.classList.add('audit-optional-document-fields');
                    fileInputForEntry.removeAttribute('id');
                    fileInputForEntry.removeAttribute('data-optional-document-file');
                }

                if (!entry.fields) {
                    entry.fields = document.createElement('div');
                    entry.fields.className = 'audit-optional-document-fields';

                    entry.nameInput = document.createElement('input');
                    entry.nameInput.type = 'hidden';
                    entry.nameInput.name = 'optional_document_names[]';
                    entry.fields.appendChild(entry.nameInput);

                    optionalDocumentsFields?.appendChild(entry.fields);
                    optionalDocumentEntries.push(entry);
                }

                if (selectedFile) {
                    entry.fileInput?.remove();
                    entry.fileInput = fileInputForEntry;
                    entry.fileName = selectedFile.name;
                    entry.fileSize = formatFileSize(selectedFile.size);
                    entry.fields.appendChild(fileInputForEntry);

                    const replacement = document.createElement('input');
                    replacement.type = 'file';
                    replacement.className = 'form-control';
                    replacement.id = 'optionalAuditDocumentFile';
                    replacement.setAttribute('data-optional-document-file', '');
                    optionalDocumentFile = replacement;
                    document.querySelector('label[for="optionalAuditDocumentFile"]')?.after(optionalDocumentFile);
                }

                entry.nameInput.value = documentName;

                if (!entry.row) {
                    entry.row = document.createElement('div');
                    entry.row.className = 'audit-optional-document-item';
                    entry.row.innerHTML = `
                        <span>
                            <span class="audit-optional-document-name"></span>
                            <span class="audit-optional-document-meta"></span>
                        </span>
                        <span class="audit-optional-document-actions">
                            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#auditOptionalDocumentModal" data-optional-document-edit="${optionalDocumentEntries.length - 1}">Edit</button>
                        </span>
                    `;
                    optionalDocumentsList?.appendChild(entry.row);
                }

                entry.row.querySelector('.audit-optional-document-name').textContent = documentName;
                entry.row.querySelector('.audit-optional-document-meta').textContent = `${entry.fileSize || 'Attached'} | Ready to publish`;
                optionalDocumentsList?.querySelector('[data-optional-documents-empty]')?.remove();
                closeOptionalDocumentModal();
            });

            const monitoringForm = document.querySelector('[data-monitoring-form]');
            const monitoringModalElement = document.getElementById('monitoringOptionalDocumentModal');
            const monitoringModal = monitoringModalElement && window.bootstrap
                ? bootstrap.Modal.getOrCreateInstance(monitoringModalElement)
                : null;
            const monitoringNameInput = document.querySelector('[data-monitoring-doc-name]');
            let monitoringFileInput = document.querySelector('[data-monitoring-doc-file]');
            const monitoringStatusSelect = document.querySelector('[data-monitoring-status-select]');
            const monitoringActiveOnlyFields = [...document.querySelectorAll('[data-monitoring-active-only]')];

            const syncMonitoringFields = () => {
                const isCompleted = monitoringStatusSelect?.value === 'Completed';
                monitoringActiveOnlyFields.forEach((field) => {
                    field.classList.toggle('d-none', isCompleted);
                });
            };

            monitoringStatusSelect?.addEventListener('change', syncMonitoringFields);
            syncMonitoringFields();

            document.querySelector('[data-monitoring-open-doc-modal]')?.addEventListener('click', () => {
                if (monitoringNameInput) {
                    monitoringNameInput.value = '';
                }

                if (monitoringFileInput) {
                    const replacement = monitoringFileInput.cloneNode();
                    replacement.value = '';
                    monitoringFileInput.replaceWith(replacement);
                    monitoringFileInput = replacement;
                }

                monitoringModal?.show();
            });

            document.querySelector('[data-monitoring-doc-save]')?.addEventListener('click', () => {
                const documentName = monitoringNameInput?.value.trim() || '';
                const selectedFile = monitoringFileInput?.files?.[0] || null;

                if (!monitoringForm || !documentName) {
                    monitoringNameInput?.focus();
                    return;
                }

                if (!selectedFile) {
                    monitoringFileInput?.focus();
                    return;
                }

                const fields = monitoringForm.querySelector('[data-monitoring-optional-doc-fields]');
                const list = monitoringForm.querySelector('[data-monitoring-optional-doc-list]');
                const empty = list?.querySelector('[data-monitoring-optional-doc-empty]');
                const attachedFileInput = monitoringFileInput;
                attachedFileInput.name = 'optional_documents[]';
                attachedFileInput.removeAttribute('id');
                attachedFileInput.removeAttribute('data-monitoring-doc-file');
                attachedFileInput.classList.add('d-none');

                const nameField = document.createElement('input');
                nameField.type = 'hidden';
                nameField.name = 'optional_document_names[]';
                nameField.value = documentName;

                fields?.appendChild(nameField);
                fields?.appendChild(attachedFileInput);
                empty?.remove();

                const row = document.createElement('div');
                row.className = 'monitoring-optional-document-item';
                row.innerHTML = `
                    <span>
                        <span class="monitoring-optional-document-name"></span>
                        <span class="monitoring-optional-document-meta"></span>
                    </span>
                `;
                row.querySelector('.monitoring-optional-document-name').textContent = documentName;
                row.querySelector('.monitoring-optional-document-meta').textContent = `${selectedFile.name} | Ready to save`;
                list?.appendChild(row);

                const replacement = document.createElement('input');
                replacement.type = 'file';
                replacement.className = 'form-control';
                replacement.id = 'monitoringOptionalDocumentFile';
                replacement.setAttribute('data-monitoring-doc-file', '');
                monitoringFileInput = replacement;
                document.querySelector('label[for="monitoringOptionalDocumentFile"]')?.after(monitoringFileInput);
                monitoringModal?.hide();
            });

        });
    </script>
@endsection
