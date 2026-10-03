@extends('layouts.app')

@section('content')
    @php
        $workflow = \App\Support\TrademarkWorkflow::class;
        $displayTimezone = config('app.timezone', 'Europe/London');
        $formatDateTime = fn ($timestamp, string $format = 'd M Y') => $timestamp
            ? \Illuminate\Support\Carbon::parse($timestamp)->timezone($displayTimezone)->format($format)
            : null;
        $stageLabels = $workflow::labels();
        $stageTitle = $stageLabels[$stage] ?? ucwords(strtolower(str_replace('_', ' ', $stage)));
        $details = $application->members_details ?? [];
        $submittedSections = [
            'Billing Details' => $details['billing_details'] ?? ($details['billing_company_details'] ?? []),
            'Applicant Details' => $details['applicant_details'] ?? ($details['trademark_applicant_details'] ?? []),
            'Authorised Person' => $details['authorised_person'] ?? ($details['details_of_signatory'] ?? []),
            'Additional Applicants' => $details['additional_applicants'] ?? ($details['details_of_co_applicant_or_partners'] ?? []),
            'Trademark Details' => $details['trademark_details'] ?? [],
            'Priority Details' => $details['priority_details'] ?? [],
        ];
        $formatSubmittedValue = function ($value) {
            if (is_bool($value)) {
                return $value ? 'Yes' : 'No';
            }

            if (is_array($value)) {
                return collect($value)->map(function ($item) {
                    if (!is_array($item)) {
                        return $item;
                    }

                    return collect($item)
                        ->filter(fn ($nested) => $nested !== null && $nested !== '')
                        ->map(fn ($nested, $key) => ucwords(str_replace('_', ' ', $key)).': '.(is_bool($nested) ? ($nested ? 'Yes' : 'No') : $nested))
                        ->implode(', ');
                })->filter()->implode('; ') ?: 'N/A';
            }

            return filled($value) ? $value : 'N/A';
        };
        $documentLabel = function (string $type) {
            return match ($type) {
                'engagement_letter' => 'Engagement Letter',
                'engagement_letter (Signed)' => 'Signed Engagement Letter',
                'application_summary' => 'Application Summary',
                'final_specification' => 'Final Specification',
                'filing_authority' => 'Filing Authority',
                'filing_authority (Signed)' => 'Signed Filing Authority',
                'ukipo_filing_receipt' => 'UKIPO Filing Receipt',
                'search_report' => 'Search Report',
                'draft_pdf' => 'Draft PDF',
                'draft_pdf (Signed)' => 'Signed Draft PDF',
                'final_filing_signature' => 'Final Filing Signature',
                default => ucwords(str_replace('_', ' ', $type)),
            };
        };
        $statusBadgeClass = function (?string $status) {
            return match (strtolower((string) $status)) {
                'approved', 'verified', 'completed', 'paid' => 'success',
                'uploaded', 'reuploaded', 'pending', 'prepared' => 'warning',
                'reupload_requested', 'rejected', 'failed' => 'danger',
                default => 'info',
            };
        };
        $documentTypeClass = function (string $type) {
            return match (true) {
                str_contains($type, 'engagement_letter') => 'blue',
                str_contains($type, 'poa') => 'green',
                str_contains($type, 'affidavit') => 'purple',
                str_contains($type, 'draft') => 'amber',
                default => 'gray',
            };
        };
        $visibleDocuments = $application->documents
            ->reject(fn ($doc) => $doc->status === 'archived')
            ->sortByDesc('id');
    @endphp

    <div class="container py-3">
        <div class="stage-detail-header">
            <div class="stage-header-copy">
                <a href="{{ route('trademark.status', $application->id) }}" class="stage-back-link">
                    <i class="bi bi-arrow-left"></i> Back to status
                </a>
                <h2>{{ $stageTitle }}</h2>
                <p>
                    <button type="button" class="stage-meta-link" data-bs-toggle="modal" data-bs-target="#applicationDetailsModal">
                        <i class="bi bi-card-checklist"></i>
                        View Application Details & Documents
                    </button>
                    <span class="stage-dot"></span>
                    <span>{{ $application->brand_name ?? 'Trademark' }}</span>
                </p>
            </div>
            <div class="stage-header-actions">
                <div class="stage-header-chip">
                    <i class="bi bi-flag-fill"></i>
                    <span>{{ $application->status_label }}</span>
                </div>
            </div>
        </div>

        <section class="stage-card stage-overview-card">
            <div class="stage-overview-icon">
                <i class="bi bi-info-circle"></i>
            </div>
            <div>
                <span>Stage Overview</span>
                <p>Open the application details link in the stage heading to view the shared application data and uploaded documents for this trademark matter.</p>
            </div>
        </section>
    </div>

    <div class="modal fade stage-common-modal" id="applicationDetailsModal" tabindex="-1" aria-labelledby="applicationDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <span class="stage-section-badge stage-section-badge-blue">Application #{{ $application->id }}</span>
                        <h5 class="modal-title" id="applicationDetailsModalLabel">Application Details & Documents</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-pills stage-modal-tabs" id="applicationDetailsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="application-data-tab" data-bs-toggle="pill" data-bs-target="#application-data-panel" type="button" role="tab" aria-controls="application-data-panel" aria-selected="true">Application Details</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="application-documents-tab" data-bs-toggle="pill" data-bs-target="#application-documents-panel" type="button" role="tab" aria-controls="application-documents-panel" aria-selected="false">Documents</button>
                        </li>
                    </ul>

                    <div class="tab-content pt-3">
                        <div class="tab-pane fade show active" id="application-data-panel" role="tabpanel" aria-labelledby="application-data-tab" tabindex="0">
                            <div class="stage-summary-grid mb-3">
                                <div><span>Applicant</span><strong>{{ $application->applicant_name ?? 'N/A' }}</strong><em class="summary-mini-badge">Client</em></div>
                                <div><span>Trademark</span><strong>{{ $application->brand_name ?? 'N/A' }}</strong><em class="summary-mini-badge summary-mini-badge-teal">Mark</em></div>
                                <div><span>Email</span><strong>{{ $application->email ?? auth()->user()->email }}</strong></div>
                                <div><span>Phone</span><strong>{{ $application->phone ?? 'N/A' }}</strong></div>
                                <div><span>Applicant Type</span><strong>{{ ucwords(str_replace('_', ' ', data_get($details, 'applicant_details.applicant_type', $application->entity_type ?? 'N/A'))) }}</strong><em class="summary-mini-badge summary-mini-badge-indigo">Type</em></div>
                                <div><span>Submitted</span><strong>{{ $formatDateTime($application->created_at) }}</strong></div>
                            </div>

                            <div class="accordion stage-accordion" id="stageDataAccordion">
                                @foreach ($submittedSections as $title => $sectionData)
                                    @php
                                        $hasSectionData = !empty(array_filter($sectionData, fn ($value) => $value !== null && $value !== '' && $value !== []));
                                        $sectionId = 'stage-section-' . $loop->index;
                                    @endphp
                                    @if ($hasSectionData)
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="{{ $sectionId }}-heading">
                                                <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $sectionId }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="{{ $sectionId }}">
                                                    <span class="accordion-title-dot"></span>
                                                    {{ $title }}
                                                </button>
                                            </h2>
                                            <div id="{{ $sectionId }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" aria-labelledby="{{ $sectionId }}-heading" data-bs-parent="#stageDataAccordion">
                                                <div class="accordion-body">
                                                    <div class="stage-field-grid">
                                                        @foreach ($sectionData as $field => $value)
                                                            <div class="stage-field">
                                                                <span>{{ ucwords(str_replace('_', ' ', $field)) }}</span>
                                                                @if (in_array($field, ['image_of_trademark', 'logo_mark_file'], true) && $value)
                                                                    <a href="{{ route('trademark.image.view', ['id' => $application->id, 'file' => base64_encode((string) $value)]) }}" target="_blank">View Trademark Image</a>
                                                                @elseif (in_array($field, ['proof_of_use_of_trademark', 'supporting_evidence', 'priority_document'], true) && $value)
                                                                    <a href="{{ route('trademark.proof-of-use.view', ['id' => $application->id, 'file' => base64_encode((string) $value)]) }}" target="_blank">View Supporting Document</a>
                                                                @else
                                                                    <strong>{{ $formatSubmittedValue($value) }}</strong>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        <div class="tab-pane fade" id="application-documents-panel" role="tabpanel" aria-labelledby="application-documents-tab" tabindex="0">
                            @forelse ($visibleDocuments as $doc)
                                <div class="stage-document-row">
                                    <div class="stage-document-main">
                                        <span class="stage-document-type stage-document-type-{{ $documentTypeClass($doc->document_type) }}">{{ $documentLabel($doc->document_type) }}</span>
                                        <strong>{{ $documentLabel($doc->document_type) }}</strong>
                                        <small>{{ $doc->file_name }}</small>
                                    </div>
                                    <span class="stage-pill stage-pill-{{ $statusBadgeClass($doc->status) }}">{{ ucwords(str_replace('_', ' ', $doc->status)) }}</span>
                                    <div class="stage-document-actions">
                                        <a href="{{ route('user.document.view', $doc->id) }}" target="_blank">View</a>
                                        <a href="{{ route('user.document.download', $doc->id) }}">Download</a>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted mb-0">No documents are available yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .container.py-3 {
            max-width: 1260px;
        }

        .stage-detail-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.15rem;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            background: linear-gradient(135deg, var(--navy) 0%, #2d4a73 100%);
            box-shadow: 0 14px 34px rgba(29, 53, 87, 0.2);
            padding: 1rem 1.1rem;
        }

        .stage-header-copy {
            min-width: 0;
        }

        .stage-detail-header h2 {
            margin: 0.25rem 0 0.1rem;
            color: #fff;
            font-size: 1.55rem;
            font-weight: 900;
        }

        .stage-detail-header p {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
            color: rgba(255, 255, 255, 0.86);
            font-size: 0.9rem;
            font-weight: 700;
        }

        .stage-title-row {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            flex-wrap: wrap;
        }

        .stage-dot {
            width: 6px;
            height: 6px;
            border-radius: 999px;
            background: #2a9d8f;
            display: inline-block;
        }

        .stage-header-actions {
            display: flex;
            align-items: flex-end;
            flex-direction: column;
            gap: 0.7rem;
        }

        .stage-meta-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            border: 1px solid rgba(255, 255, 255, 0.26);
            border-radius: 9px;
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
            padding: 0.42rem 0.6rem;
            font-size: 0.82rem;
            font-weight: 900;
            box-shadow: 0 10px 22px rgba(0, 0, 0, 0.12);
            text-decoration: none;
            transition: transform 0.18s ease, background 0.18s ease;
        }

        .stage-meta-link:hover {
            background: rgba(255, 255, 255, 0.22);
            transform: translateY(-1px);
        }

        .stage-status-badge,
        .stage-pill,
        .stage-header-chip,
        .stage-section-badge,
        .stage-document-type {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: fit-content;
            border-radius: 7px;
            font-weight: 800;
            line-height: 1;
            white-space: nowrap;
        }

        .stage-status-badge,
        .stage-pill {
            padding: 0.42rem 0.7rem;
            font-size: 0.78rem;
        }

        .stage-status-badge-success,
        .stage-pill-success {
            background: #dcfce7;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .stage-status-badge-warning,
        .stage-pill-warning {
            background: #fff7ed;
            color: #c2410c;
            border: 1px solid #fed7aa;
        }

        .stage-status-badge-danger,
        .stage-pill-danger {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .stage-status-badge-info,
        .stage-pill-info {
            background: #dbeafe;
            color: #0d62d6;
            border: 1px solid #bfdbfe;
        }

        .stage-header-chip {
            gap: 0.45rem;
            padding: 0.65rem 0.85rem;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.18);
            color: #fff;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12);
        }

        .stage-header-chip i {
            color: #7dd3c7;
        }

        .stage-back-link {
            color: #dbeafe;
            text-decoration: none;
            font-weight: 800;
            font-size: 0.86rem;
        }

        .stage-overview-card {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            min-height: 118px;
        }

        .stage-overview-card::before {
            background: linear-gradient(90deg, #2a9d8f, #1464f6);
        }

        .stage-overview-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 52px;
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: #e9fbf4;
            color: #0f9461;
            font-size: 1.4rem;
        }

        .stage-overview-card span {
            display: block;
            margin-bottom: 0.25rem;
            color: #183153;
            font-size: 1rem;
            font-weight: 900;
        }

        .stage-overview-card p {
            margin: 0;
            color: #5b6575;
            font-size: 0.92rem;
            font-weight: 650;
        }

        .stage-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .stage-card {
            position: relative;
            overflow: hidden;
            border: 1px solid #dfe9f6;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 10px 26px rgba(15, 36, 68, 0.07);
            padding: 1.05rem;
        }

        .stage-card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 4px;
            background: linear-gradient(90deg, #1464f6, #2a9d8f);
        }

        .stage-card-summary::before {
            background: linear-gradient(90deg, #1464f6, #60a5fa);
        }

        .stage-card-documents::before {
            background: linear-gradient(90deg, #0f9461, #2a9d8f);
        }

        .stage-card-data::before {
            background: linear-gradient(90deg, #7c3aed, #1464f6);
        }

        .stage-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 0.9rem 1rem;
            margin: -1.05rem -1.05rem 0.9rem;
            border-bottom: 0;
            background: linear-gradient(135deg, var(--navy) 0%, #2d4a73 100%);
        }

        .stage-card-header h5 {
            color: #fff;
            margin: 0;
            font-size: 1rem;
            font-weight: 900;
        }

        .stage-section-badge {
            margin-bottom: 0.35rem;
            padding: 0.28rem 0.5rem;
            font-size: 0.68rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .stage-section-badge-blue {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .stage-section-badge-green {
            background: #dcfce7;
            color: #047857;
        }

        .stage-section-badge-purple {
            background: #ede9fe;
            color: #6d28d9;
        }

        .stage-card-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 46px;
            width: 46px;
            height: 46px;
            border-radius: 14px;
            font-size: 1.35rem;
            line-height: 1;
        }

        .stage-card-icon i {
            display: block;
            line-height: 1;
        }

        .stage-card-icon-blue {
            background: #eaf3ff;
            color: #1464f6;
        }

        .stage-card-icon-green {
            background: #e9fbf4;
            color: #0f9461;
        }

        .stage-card-icon-purple {
            background: #f1edff;
            color: #7c3aed;
        }

        .stage-summary-grid,
        .stage-field-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.7rem;
        }

        .stage-summary-grid div,
        .stage-field {
            position: relative;
            border: 1px solid #e5edf7;
            border-radius: 10px;
            background: linear-gradient(180deg, #f8fbff 0%, #f3f8ff 100%);
            padding: 0.75rem 0.8rem;
            min-height: 64px;
        }

        .stage-summary-grid span,
        .stage-field span {
            display: block;
            margin-bottom: 0.25rem;
            color: #6a778b;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .stage-summary-grid strong,
        .stage-field strong,
        .stage-field a {
            color: #183153;
            font-size: 0.9rem;
            font-weight: 800;
            word-break: break-word;
        }

        .summary-mini-badge {
            position: absolute;
            top: 0.55rem;
            right: 0.55rem;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            padding: 0.18rem 0.42rem;
            font-size: 0.62rem;
            font-style: normal;
            font-weight: 900;
        }

        .summary-mini-badge-teal {
            background: #ccfbf1;
            color: #0f766e;
        }

        .summary-mini-badge-indigo {
            background: #e0e7ff;
            color: #4338ca;
        }

        .stage-common-modal {
            z-index: 1085;
        }

        .stage-common-modal + .modal-backdrop {
            z-index: 1080;
        }

        .stage-common-modal .modal-content {
            overflow: hidden;
            border: 1px solid #dfe9f6;
            border-radius: 16px;
            box-shadow: 0 22px 55px rgba(15, 36, 68, 0.22);
        }

        .stage-common-modal .modal-header {
            align-items: center;
            background: linear-gradient(135deg, var(--navy) 0%, #2d4a73 100%);
            border-bottom: 0;
            color: #fff;
            padding: 0.95rem 1.1rem;
        }

        .stage-common-modal .modal-title {
            margin: 0;
            color: #fff;
            font-size: 1.05rem;
            font-weight: 900;
        }

        .stage-common-modal .btn-close {
            filter: invert(1) grayscale(1) brightness(1.8);
            opacity: 0.9;
        }

        .stage-common-modal .modal-body {
            padding: 1rem;
        }

        .stage-modal-tabs {
            gap: 0.45rem;
            border-bottom: 1px solid #e5edf7;
            padding-bottom: 0.7rem;
        }

        .stage-modal-tabs .nav-link {
            border: 1px solid #dbeafe;
            border-radius: 9px;
            background: #ffffff;
            color: #2A9D8F;
            font-size: 0.82rem;
            font-weight: 900;
        }

        .stage-modal-tabs .nav-link.active {
            border-color: #2A9D8F;
            background: #2A9D8F;
            color: #fff !important;
        }

        .stage-document-row {
            display: grid;
            grid-template-columns: 1fr auto auto;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #edf1f6;
        }

        .stage-document-main {
            min-width: 0;
        }

        .stage-document-row strong,
        .stage-document-row small {
            display: block;
        }

        .stage-document-row strong {
            margin-top: 0.35rem;
            color: #26384f;
            font-size: 0.98rem;
        }

        .stage-document-row small {
            color: #6a778b;
            font-size: 0.78rem;
            word-break: break-word;
        }

        .stage-document-type {
            padding: 0.25rem 0.5rem;
            font-size: 0.66rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .stage-document-type-blue {
            background: #e0f2fe;
            color: #0369a1;
        }

        .stage-document-type-green {
            background: #dcfce7;
            color: #047857;
        }

        .stage-document-type-purple {
            background: #ede9fe;
            color: #6d28d9;
        }

        .stage-document-type-amber {
            background: #fef3c7;
            color: #b45309;
        }

        .stage-document-type-gray {
            background: #f1f5f9;
            color: #475569;
        }

        .stage-document-actions {
            display: flex;
            gap: 0.45rem;
            flex-wrap: wrap;
        }

        .stage-document-row a {
            border: 1px solid #bfdbfe;
            border-radius: 7px;
            background: #eff6ff;
            color: #0d62d6;
            padding: 0.32rem 0.55rem;
            font-size: 0.82rem;
            font-weight: 800;
            text-decoration: none;
        }

        .stage-accordion .accordion-button {
            gap: 0.6rem;
            padding: 0.78rem 1rem;
            background: #f8fbff;
            color: #183153;
            font-size: 0.92rem;
            font-weight: 900;
        }

        .stage-accordion .accordion-button:not(.collapsed) {
            background: linear-gradient(90deg, #dbeafe, #e9fbf4);
            color: #14345b;
            box-shadow: none;
        }

        .accordion-title-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #2a9d8f;
            box-shadow: 0 0 0 4px rgba(42, 157, 143, 0.13);
        }

        .stage-accordion .accordion-body {
            padding: 0.8rem;
        }

        @media (max-width: 900px) {
            .stage-grid,
            .stage-summary-grid,
            .stage-field-grid,
            .stage-document-row {
                grid-template-columns: 1fr;
            }

            .stage-detail-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .stage-header-actions {
                align-items: stretch;
                width: 100%;
            }

            .stage-document-actions {
                width: 100%;
            }
        }
    </style>
@endsection
