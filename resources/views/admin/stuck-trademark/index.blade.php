@extends('layouts.app')

@section('content')
    <style>
        .recovery-admin-mobile-cards {
            display: none;
        }

        .recovery-admin-card {
            display: grid;
            gap: 0;
            padding: 16px;
            border: 1px solid #d8e2ef;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
        }

        .recovery-admin-card-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            min-width: 0;
            padding-bottom: 14px;
            border-bottom: 1px solid #e3ebf6;
        }

        .recovery-admin-title-copy {
            min-width: 0;
        }

        .recovery-admin-title-copy strong {
            display: block;
            color: #061e5f;
            font-size: 1rem;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .recovery-admin-title-copy small {
            display: block;
            margin-top: 4px;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }

        .recovery-admin-status {
            flex: 0 0 auto;
            max-width: 40%;
            border: 1px solid #fed7aa;
            border-radius: 6px;
            background: #fff7ed;
            color: #ea7600;
            padding: 7px 10px;
            font-size: 0.78rem;
            font-weight: 900;
            line-height: 1.1;
            text-align: center;
            text-transform: uppercase;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .recovery-admin-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            border-bottom: 1px solid #e3ebf6;
        }

        .recovery-admin-meta-item {
            min-width: 0;
            padding: 14px 0;
            border-bottom: 1px solid #e3ebf6;
        }

        .recovery-admin-meta-item:nth-child(odd) {
            padding-right: 14px;
        }

        .recovery-admin-meta-item:nth-child(even) {
            padding-left: 14px;
            border-left: 1px solid #e3ebf6;
        }

        .recovery-admin-meta-item:nth-last-child(-n + 2) {
            border-bottom: 0;
        }

        .recovery-admin-meta-item span {
            display: block;
            margin-bottom: 5px;
            color: #667085;
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .recovery-admin-meta-item strong,
        .recovery-admin-meta-item div {
            color: #111827;
            font-size: 0.92rem;
            font-weight: 800;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }

        .recovery-admin-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
            padding-top: 12px;
        }

        .recovery-admin-action-link {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            min-height: 30px;
            color: #0065d8;
            font-size: 0.98rem;
            font-weight: 900;
            text-decoration: none;
        }

        .recovery-case-table {
            min-width: 1180px !important;
        }

        .recovery-case-table .case-column { width: 145px; }
        .recovery-case-table .applicant-column { width: 235px; }
        .recovery-case-table .trademark-column { width: 155px; }
        .recovery-case-table .issues-column { width: 270px; }
        .recovery-case-table .status-column { width: 165px; }
        .recovery-case-table .audit-column { width: 85px; }
        .recovery-case-table .updated-column { width: 105px; }
        .recovery-case-table .action-column { width: 95px; text-align: right; }

        .recovery-case-number,
        .recovery-applicant,
        .recovery-trademark {
            display: block;
            overflow-wrap: anywhere;
        }

        .recovery-case-table .recovery-email {
            display: block;
            margin-top: 3px;
            overflow-wrap: anywhere;
        }

        .recovery-case-table .recovery-issues {
            display: -webkit-box;
            overflow: hidden;
            color: #526176;
            line-height: 1.5;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
        }

        .recovery-case-table .recovery-status-badge {
            display: inline-flex;
            justify-content: center;
            text-align: center;
        }

        .recovery-case-table .recovery-date {
            white-space: nowrap;
        }

        @media (max-width: 991.98px) {
            .recovery-admin-table {
                display: none;
            }

            .recovery-admin-mobile-cards {
                display: grid;
                gap: 12px;
                padding: 14px;
            }

            .recovery-admin-header {
                align-items: flex-start !important;
                flex-direction: column;
                gap: 12px;
            }
        }

        @media (max-width: 767.98px) {
            .recovery-admin-card {
                padding: 14px;
            }

            .recovery-admin-status {
                max-width: 40%;
                padding: 6px 9px;
                font-size: 0.72rem;
            }

            .recovery-admin-meta-item {
                padding: 10px 0;
            }

            .recovery-admin-meta-item:nth-child(odd) {
                padding-right: 10px;
            }

            .recovery-admin-meta-item:nth-child(even) {
                padding-left: 10px;
            }
        }
    </style>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4 recovery-admin-header">
            <div>
                <h2 class="mb-1">Stuck Trademark Recovery Cases</h2>
                <p class="text-muted mb-0">Audit, execution, and registry follow-up queue</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" class="d-flex gap-2 flex-wrap">
                    <input name="search" class="form-control form-control-sm flex-grow-1"
                        style="min-width:220px" value="{{ request('search') }}"
                        placeholder="Search case, applicant, email, application, or trademark">
                    <select name="status" class="form-select form-select-sm" style="max-width:240px">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $value => $label)
                            <x-admin-status-option :value="$value" :label="$label"
                                :selected="request('status') === $value" />
                        @endforeach
                    </select>
                    <button class="btn btn-primary btn-sm" type="submit">Search &amp; filter</button>
                    @if (request()->hasAny(['search', 'status']))
                        <a href="{{ route('admin.stuck-trademark.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                    @endif
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                @if ($cases->count())
                    <div class="table-responsive recovery-admin-table">
                        <table class="table table-hover align-middle mb-0 admin-list-table recovery-case-table">
                            <colgroup>
                                <col class="case-column">
                                <col class="applicant-column">
                                <col class="trademark-column">
                                <col class="issues-column">
                                <col class="status-column">
                                <col class="audit-column">
                                <col class="updated-column">
                                <col class="action-column">
                            </colgroup>
                            <thead class="table-light">
                                <tr>
                                    <th>Case</th>
                                    <th>Applicant</th>
                                    <th>Trademark</th>
                                    <th>Issues</th>
                                    <th>Status</th>
                                    <th>Audit</th>
                                    <th>Updated</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cases as $case)
                                    <tr>
                                        <td>
                                            <strong class="recovery-case-number">{{ $case->case_number }}</strong>
                                            <small class="text-muted">{{ $case->application_number ?: 'No application no.' }}</small>
                                        </td>
                                        <td>
                                            <strong class="recovery-applicant">{{ $case->applicant_name }}</strong>
                                            <small class="text-muted recovery-email">{{ $case->email }}</small>
                                        </td>
                                        <td><span class="recovery-trademark">{{ $case->trademark_name }}</span></td>
                                        <td>
                                            <span class="recovery-issues" title="{{ $case->issue_summary ?: 'Not classified' }}">
                                                {{ $case->issue_summary ?: 'Not classified' }}
                                            </span>
                                        </td>
                                        <td><x-admin-status :status="$case->status_label" class="recovery-status-badge" /></td>
                                        <td><x-admin-status :status="$case->audit_payment_status" /></td>
                                        <td><span class="recovery-date">{{ $case->updated_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}</span></td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.stuck-trademark.show', $case) }}" class="btn btn-sm btn-primary">Manage</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="recovery-admin-mobile-cards">
                        @foreach ($cases as $case)
                            <article class="recovery-admin-card">
                                <div class="recovery-admin-card-title">
                                    <div class="recovery-admin-title-copy">
                                        <strong>{{ $case->trademark_name }}</strong>
                                        <small class="text-muted">{{ $case->case_number }} · {{ $case->application_number ?: 'No application no.' }}</small>
                                    </div>
                                    <x-admin-status :status="$case->status_label" class="recovery-admin-status" />
                                </div>
                                <div class="recovery-admin-meta">
                                    <div class="recovery-admin-meta-item">
                                        <span>Applicant</span>
                                        <strong>{{ $case->applicant_name }}</strong>
                                        <small class="text-muted">{{ $case->email }}</small>
                                    </div>
                                    <div class="recovery-admin-meta-item">
                                        <span>Issues</span>
                                        <div>{{ $case->issue_summary ?: 'Not classified' }}</div>
                                    </div>
                                    <div class="recovery-admin-meta-item">
                                        <span>Case</span>
                                        <strong>{{ $case->case_number }}</strong>
                                    </div>
                                    <div class="recovery-admin-meta-item">
                                        <span>Audit</span>
                                        <strong>{{ ucfirst($case->audit_payment_status) }}</strong>
                                    </div>
                                    <div class="recovery-admin-meta-item">
                                        <span>Updated</span>
                                        <strong>{{ $case->updated_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}</strong>
                                    </div>
                                </div>
                                <div class="recovery-admin-actions">
                                    <a href="{{ route('admin.stuck-trademark.show', $case) }}" class="recovery-admin-action-link">
                                        Manage <i class="fas fa-chevron-right"></i>
                                    </a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                    <div class="p-3">
                        {{ $cases->links() }}
                    </div>
                @else
                    <div class="p-5 text-center">
                        <h5>No stuck trademark cases yet</h5>
                        <p class="text-muted mb-0">New Filed and Stuck intakes will appear here.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
