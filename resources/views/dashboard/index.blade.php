@extends('layouts.app')

@section('body_class', !empty($adminPreview) ? 'admin-client-preview' : '')

@section('content')
    @php
        $workflow = \App\Support\TrademarkWorkflow::class;
        $isAdminPreview = !empty($adminPreview);
        $dashboardUser = $client ?? Auth::user();
        $consultationBookings = $consultationBookings ?? collect();
        $trademarkSearchReportRequests = $trademarkSearchReportRequests ?? collect();
        $trademarkStatusUrl = function ($clientApplication, bool $stageAction = false) use ($isAdminPreview) {
            if ($isAdminPreview) {
                return route('admin.application.client-action-center', array_filter([
                    'id' => $clientApplication->id,
                    'stage_action' => $stageAction ? 1 : null,
                ]));
            }

            return route('trademark.status', array_filter([
                'id' => $clientApplication->id,
                'stage_action' => $stageAction ? 1 : null,
            ]));
        };
        $trademarkImageUrl = fn ($clientApplication) => route(
            $isAdminPreview ? 'admin.trademark.image.view' : 'trademark.image.view',
            $clientApplication->id
        );
    @endphp
    <style>
        .dashboard-shell {
            color: #111827;
            font-size: 0.94rem;
        }

        .dashboard-stats-grid {
            --bs-gutter-x: 1.25rem;
            --bs-gutter-y: 1.25rem;
        }

        .dashboard-stat-card {
            height: 100%;
            border: 1px solid #e1e8f2;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.08);
        }

        .dashboard-stat-body {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: 18px;
            align-items: center;
            min-height: 104px;
            padding: 20px;
        }

        .dashboard-stat-icon {
            width: 58px;
            height: 58px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            font-size: 1.55rem;
        }

        .dashboard-stat-icon iconify-icon {
            width: 30px;
            height: 30px;
        }

        .dashboard-stat-icon.is-warning {
            background: #fff5df;
            color: #eda000;
        }

        .dashboard-stat-icon.is-info {
            background: #edf5ff;
            color: #1d73df;
        }

        .dashboard-stat-icon.is-primary {
            background: #f2edff;
            color: #7254d9;
        }

        .dashboard-stat-icon.is-success {
            background: #ecf8ef;
            color: #159447;
        }

        .dashboard-stat-copy {
            min-width: 0;
        }

        .dashboard-stat-copy h3 {
            margin: 0 0 6px;
            color: #1f2937;
            font-size: 1.05rem;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: 0;
        }

        .dashboard-stat-copy p {
            margin: 0;
            color: #667085;
            font-size: 0.9rem;
            font-weight: 700;
            line-height: 1.55;
        }

        .dashboard-stat-value {
            min-width: 54px;
            padding-left: 20px;
            border-left: 1px dashed #d7e0ec;
            font-size: 2.25rem;
            font-weight: 900;
            line-height: 1;
            text-align: right;
        }

        .dashboard-stat-value.is-warning {
            color: #eda000;
        }

        .dashboard-stat-value.is-info {
            color: #1d73df;
        }

        .dashboard-stat-value.is-primary {
            color: #7254d9;
        }

        .dashboard-stat-value.is-success {
            color: #159447;
        }

        .dashboard-service-links {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .dashboard-service-link {
            display: flex;
            align-items: center;
            min-height: 72px;
            padding: 17px 20px;
            border: 0;
            border-radius: 12px;
            color: #ffffff;
            text-decoration: none;
            box-shadow: 0 9px 22px rgba(15, 23, 42, 0.16);
            transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
        }

        .dashboard-service-link:hover {
            color: #ffffff;
            box-shadow: 0 13px 28px rgba(15, 23, 42, 0.22);
            transform: translateY(-2px);
            filter: brightness(1.06);
        }

        .dashboard-service-link:nth-child(1) {
            background: linear-gradient(135deg, #0f9f8b, #087b70);
        }

        .dashboard-service-link:nth-child(2) {
            background: linear-gradient(135deg, #2563eb, #1746af);
        }

        .dashboard-service-link:nth-child(3) {
            background: linear-gradient(135deg, #7c3aed, #5621ad);
        }

        .dashboard-service-link:nth-child(4) {
            background: linear-gradient(135deg, #e87916, #bd4e0c);
        }

        .dashboard-service-link-copy {
            min-width: 0;
        }

        .dashboard-service-link-copy strong,
        .dashboard-service-link-copy small {
            display: block;
        }

        .dashboard-service-link-copy strong {
            font-size: .9rem;
            font-weight: 850;
            line-height: 1.25;
        }

        .dashboard-service-link-copy small {
            margin-top: 4px;
            color: rgba(255, 255, 255, 0.84);
            font-size: .75rem;
            line-height: 1.3;
        }

        .dashboard-empty-state {
            padding: 34px 20px;
            text-align: center;
        }

        .dashboard-empty-state i {
            margin-bottom: 11px;
            color: #94a3b8;
            font-size: 1.8rem;
        }

        .dashboard-empty-state p {
            margin: 0 0 14px;
            color: #667085;
        }

        .dashboard-section-card {
            overflow: hidden;
            border: 1px solid #d8e2ef;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        }

        .dashboard-section-head {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px 24px;
            background: #294d78;
            color: #ffffff;
        }

        .dashboard-section-icon {
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.13);
            border: 1px solid rgba(255, 255, 255, 0.14);
            color: #ffffff;
            font-size: 1rem;
        }

        .dashboard-section-icon svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            stroke-width: 2.25;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
        }

        .dashboard-section-title {
            margin: 0;
            color: #ffffff;
            font-size: 1.25rem;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: 0;
        }

        .dashboard-table-wrap {
            padding: 20px 22px;
        }

        .dashboard-table-box {
            overflow: hidden;
            border: 1px solid #d8e2ef;
            border-radius: 8px;
            background: #ffffff;
        }

        .dashboard-table {
            margin: 0;
            color: #111827;
            font-size: 0.92rem;
        }

        .dashboard-table thead th {
            padding: 13px 16px;
            border-bottom: 1px solid #d8e2ef;
            background: #f8fafc;
            color: #06164a;
            font-size: 0.9rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .dashboard-table tbody td {
            padding: 16px;
            border-bottom: 1px solid #d8e2ef;
            vertical-align: middle;
        }

        .dashboard-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .dashboard-table tbody tr:hover {
            background: #fbfdff;
        }

        .dashboard-primary-text {
            color: #061e5f;
            font-size: 0.98rem;
            font-weight: 800;
            line-height: 1.25;
        }

        .dashboard-subtext {
            display: block;
            margin-top: 4px;
            color: #667085;
            font-size: 0.82rem;
            line-height: 1.25;
        }

        .dashboard-logo-frame {
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            overflow: hidden;
            background: #f8fafc;
            border: 1px solid #d8e2ef;
        }

        .dashboard-logo-frame img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 4px;
            background: #ffffff;
        }

        .dashboard-trademark-cell {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 190px;
        }

        .dashboard-issues {
            max-width: 320px;
            color: #2d3748;
            line-height: 1.45;
        }

        .dashboard-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 10px;
            border-radius: 6px;
            color: #ffffff;
            font-size: 0.82rem;
            font-weight: 800;
            line-height: 1;
            white-space: nowrap;
            box-shadow: 0 3px 8px rgba(15, 23, 42, 0.14);
        }

        .dashboard-badge.status-dark {
            background: linear-gradient(135deg, #06184d, #00133f);
        }

        .dashboard-badge.status-orange {
            background: linear-gradient(135deg, #f59e0b, #ff8a00);
        }

        .dashboard-badge.status-purple {
            background: linear-gradient(135deg, #7c3aed, #5b2fd1);
        }

        .dashboard-badge.status-blue {
            background: linear-gradient(135deg, #2563eb, #0f65e9);
        }

        .dashboard-badge.status-green {
            background: linear-gradient(135deg, #059669, #00865f);
        }

        .dashboard-badge.status-red {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .dashboard-badge.status-gray {
            background: linear-gradient(135deg, #64748b, #475569);
        }

        .dashboard-audit-pill,
        .dashboard-payment-text {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 6px;
            font-size: 0.87rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .dashboard-audit-pill {
            padding: 7px 10px;
        }

        .dashboard-audit-pill.is-paid {
            background: #def7ec;
            color: #05845d;
        }

        .dashboard-audit-pill.is-pending {
            background: #fff4d8;
            color: #b56a00;
        }

        .dashboard-payment-text.is-success {
            color: #008a4b;
        }

        .dashboard-payment-text.is-danger {
            color: #c92a2a;
        }

        .dashboard-payment-text.is-warning {
            color: #b56a00;
        }

        .dashboard-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 36px;
            padding: 8px 14px;
            border: 0;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 800;
            line-height: 1;
            white-space: nowrap;
            box-shadow: none;
        }

        .dashboard-action-btn.btn-track,
        .dashboard-action-btn.btn-successish {
            background: linear-gradient(135deg, #06a871, #00875f);
            color: #ffffff;
        }

        .dashboard-action-btn.btn-view {
            background: linear-gradient(135deg, #0875d8, #065dc5);
            color: #ffffff;
        }

        .dashboard-action-btn.btn-pay,
        .dashboard-action-btn.btn-approval {
            background: linear-gradient(135deg, #0d6efd, #0757c7);
            color: #ffffff;
        }

        .dashboard-action-btn.btn-onboarding {
            background: linear-gradient(135deg, #ffc107, #f0a800);
            color: #111827;
        }

        .dashboard-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .dashboard-mobile-cards {
            display: none;
        }

        .dashboard-mobile-card {
            display: grid;
            gap: 14px;
            padding: 16px;
            border: 1px solid #d8e2ef;
            border-radius: 10px;
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
        }

        .dashboard-mobile-card-head {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .dashboard-mobile-card-main {
            min-width: 0;
        }

        .dashboard-mobile-card-main .dashboard-primary-text {
            display: block;
            overflow-wrap: anywhere;
        }

        .dashboard-mobile-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .dashboard-mobile-meta-item {
            min-width: 0;
            border: 1px solid #e3ebf6;
            border-radius: 8px;
            background: #f8fbff;
            padding: 10px;
        }

        .dashboard-mobile-meta-item span {
            display: block;
            margin-bottom: 5px;
            color: #667085;
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .dashboard-mobile-meta-item strong,
        .dashboard-mobile-meta-item div {
            color: #111827;
            font-size: 0.92rem;
            font-weight: 800;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }

        .dashboard-mobile-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .dashboard-application-card {
            gap: 0;
            padding: 16px;
            border-radius: 14px;
        }

        .dashboard-application-card .dashboard-mobile-card-head {
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding-bottom: 14px;
            border-bottom: 1px solid #e3ebf6;
        }

        .dashboard-application-title {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .dashboard-application-title .dashboard-logo-frame {
            width: 50px;
            height: 50px;
            flex-basis: 50px;
        }

        .dashboard-application-status {
            flex: 0 0 auto;
            max-width: 42%;
            border: 1px solid #fed7aa;
            background: #fff7ed !important;
            color: #ea7600 !important;
            box-shadow: none;
            white-space: normal;
            text-align: center;
            line-height: 1.15;
            text-transform: uppercase;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .dashboard-application-detail-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            border-bottom: 1px solid #e3ebf6;
        }

        .dashboard-application-detail {
            min-width: 0;
            padding: 14px 0;
            border-bottom: 1px solid #e3ebf6;
        }

        .dashboard-application-detail:nth-child(odd) {
            padding-right: 14px;
        }

        .dashboard-application-detail:nth-child(even) {
            padding-left: 14px;
            border-left: 1px solid #e3ebf6;
        }

        .dashboard-application-detail:nth-last-child(-n + 2) {
            border-bottom: 0;
        }

        .dashboard-application-icon {
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 1.1rem;
        }

        .dashboard-application-icon.icon-id,
        .dashboard-application-icon.icon-created {
            background: #eef6ff;
            color: #0d6efd;
        }

        .dashboard-application-icon.icon-type {
            background: #f1edff;
            color: #7357d8;
        }

        .dashboard-application-icon.icon-payment {
            background: #eaf8f1;
            color: #079455;
        }

        .dashboard-application-copy {
            min-width: 0;
        }

        .dashboard-application-copy span {
            display: block;
            margin-bottom: 4px;
            color: #667085;
            font-size: 0.73rem;
            font-weight: 900;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .dashboard-application-copy strong {
            display: block;
            color: #111827;
            font-size: 0.98rem;
            font-weight: 900;
            line-height: 1.22;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .dashboard-application-card .dashboard-mobile-actions {
            justify-content: flex-end;
            padding-top: 12px;
        }

        .dashboard-application-primary-action {
            width: auto;
            min-height: 30px;
            border-radius: 0;
            background: transparent;
            color: #0065d8;
            font-size: 0.98rem;
            font-weight: 900;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
        }

        @media (max-width: 991.98px) {
            .dashboard-service-links {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .dashboard-table-box {
                display: none;
            }

            .dashboard-stats-grid {
                --bs-gutter-x: 0.9rem;
                --bs-gutter-y: 0.9rem;
            }

            .dashboard-stat-body {
                gap: 14px;
                min-height: 96px;
                padding: 16px;
            }

            .dashboard-stat-icon {
                width: 52px;
                height: 52px;
                font-size: 1.35rem;
            }

            .dashboard-stat-icon iconify-icon {
                width: 27px;
                height: 27px;
            }

            .dashboard-stat-copy h3 {
                font-size: 0.98rem;
            }

            .dashboard-stat-copy p {
                font-size: 0.84rem;
                line-height: 1.45;
            }

            .dashboard-stat-value {
                min-width: 44px;
                padding-left: 14px;
                font-size: 1.9rem;
            }

            .dashboard-mobile-cards {
                display: grid;
                gap: 12px;
            }
        }

        @media (max-width: 767.98px) {
            .dashboard-section-head {
                padding: 16px 18px;
            }

            .dashboard-service-links {
                grid-template-columns: 1fr;
            }

            .dashboard-table-wrap {
                padding: 14px;
            }

            .dashboard-stats-grid {
                --bs-gutter-x: 0.65rem;
                --bs-gutter-y: 0.65rem;
            }

            .dashboard-stats-grid > [class*="col-"] {
                width: 50%;
                flex: 0 0 50%;
            }

            .dashboard-stat-card {
                border-radius: 10px;
            }

            .dashboard-stat-body {
                grid-template-columns: auto minmax(0, 1fr) auto;
                gap: 7px;
                min-height: 74px;
                padding: 10px;
            }

            .dashboard-stat-icon {
                width: 34px;
                height: 34px;
                font-size: 0.95rem;
            }

            .dashboard-stat-icon iconify-icon {
                width: 18px;
                height: 18px;
            }

            .dashboard-stat-copy h3 {
                margin-bottom: 2px;
                font-size: 0.72rem;
                line-height: 1.18;
            }

            .dashboard-stat-copy p {
                font-size: 0.62rem;
                line-height: 1.25;
            }

            .dashboard-stat-value {
                min-width: 24px;
                padding-left: 7px;
                font-size: 1.2rem;
            }

            .dashboard-mobile-meta {
                grid-template-columns: 1fr;
            }

            .dashboard-mobile-actions .dashboard-action-btn {
                width: 100%;
            }

            .dashboard-application-card {
                padding: 14px;
            }

            .dashboard-application-card .dashboard-mobile-card-head {
                gap: 10px;
            }

            .dashboard-application-title {
                gap: 10px;
            }

            .dashboard-application-title .dashboard-logo-frame {
                width: 44px;
                height: 44px;
                flex-basis: 44px;
            }

            .dashboard-application-status {
                max-width: 40%;
                padding: 6px 9px;
                font-size: 0.72rem;
            }

            .dashboard-application-detail {
                padding: 10px 0;
            }

            .dashboard-application-detail:nth-child(odd) {
                padding-right: 10px;
            }

            .dashboard-application-detail:nth-child(even) {
                padding-left: 10px;
            }

            .dashboard-application-copy strong {
                font-size: 0.9rem;
            }

            .dashboard-application-primary-action {
                min-height: 28px;
                font-size: 0.95rem;
            }
        }
    </style>
    <div class="container-fluid mt-4 dashboard-shell">
        @if ($isAdminPreview)
            <div class="alert alert-warning d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4" role="status">
                <div>
                    <div class="fw-bold"><i class="bi bi-eye me-2"></i>Read-only client preview</div>
                    <div class="small">You are viewing the dashboard shown to {{ $dashboardUser->name }} ({{ $dashboardUser->email }}). No client data can be changed from this page.</div>
                </div>
                <a href="{{ route('admin.view-application', $previewApplication->id) }}" class="btn btn-sm btn-outline-dark flex-shrink-0" data-admin-preview-link>
                    <i class="bi bi-arrow-left me-1"></i>Back to Admin Application
                </a>
            </div>
        @endif

        <!-- Dashboard Header -->
        <div class="row mb-4">
            <div class="col-md-12">
                <h2>Welcome, {{ $dashboardUser->name }}!</h2>
                <p class="text-muted">Your Trademark Applications Dashboard</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4 dashboard-stats-grid">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="dashboard-stat-card">
                    <div class="dashboard-stat-body">
                        <span class="dashboard-stat-icon is-warning"><iconify-icon icon="solar:wallet-money-linear"></iconify-icon></span>
                        <div class="dashboard-stat-copy">
                            <h3>Pending Payment</h3>
                            <p>Payments pending execution</p>
                        </div>
                        <div class="dashboard-stat-value is-warning">{{ $pendingPayments }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="dashboard-stat-card">
                    <div class="dashboard-stat-body">
                        <span class="dashboard-stat-icon is-info"><iconify-icon icon="solar:clock-circle-linear"></iconify-icon></span>
                        <div class="dashboard-stat-copy">
                            <h3>Under Review</h3>
                            <p>Applications under review</p>
                        </div>
                        <div class="dashboard-stat-value is-info">{{ $underReview }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="dashboard-stat-card">
                    <div class="dashboard-stat-body">
                        <span class="dashboard-stat-icon is-primary"><iconify-icon icon="solar:document-text-linear"></iconify-icon></span>
                        <div class="dashboard-stat-copy">
                            <h3>Total Applications</h3>
                            <p>All time applications</p>
                        </div>
                        <div class="dashboard-stat-value is-primary">{{ $applications->count() }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="dashboard-stat-card">
                    <div class="dashboard-stat-body">
                        <span class="dashboard-stat-icon is-success"><iconify-icon icon="solar:user-check-linear"></iconify-icon></span>
                        <div class="dashboard-stat-copy">
                            <h3>Registered</h3>
                            <p>Successfully registered</p>
                        </div>
                        <div class="dashboard-stat-value is-success">{{ $registered }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Service Quick Links -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="dashboard-service-links" aria-label="Start a trademark service">
                    <a href="{{ route('trademark.type-selection') }}" class="dashboard-service-link">
                        <span class="dashboard-service-link-copy"><strong>File a Trademark</strong><small>Start a new application</small></span>
                    </a>
                    <a href="{{ route('book-call.create') }}" class="dashboard-service-link">
                        <span class="dashboard-service-link-copy"><strong>Book a Consultation</strong><small>Request a paid call with our team</small></span>
                    </a>
                </div>
            </div>
        </div>

        @if (config('uk_site.legacy_services_enabled'))
        <!-- Historical case-management services (disabled on the UK site) -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="dashboard-section-card">
                        <div class="dashboard-section-head">
                            <span class="dashboard-section-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12 3 5 6v5c0 4.5 2.8 8.5 7 10 4.2-1.5 7-5.5 7-10V6l-7-3Z" />
                                    <path d="m9 12 2 2 4-5" />
                                </svg>
                            </span>
                            <h5 class="dashboard-section-title">Trademark Opposition Defence Cases</h5>
                        </div>
                        <div class="dashboard-table-wrap">
                            @if ($trademarkOppositionCases->count())
                            <div class="table-responsive dashboard-table-box">
                                <table class="table table-hover mb-0 align-middle dashboard-table">
                                    <thead>
                                        <tr>
                                            <th>Case</th>
                                            <th>Trademark</th>
                                            <th>Deadline</th>
                                            <th>Status</th>
                                            <th>Risk</th>
                                            <th>Payment</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($trademarkOppositionCases as $oppositionCase)
                                            @php
                                                $deadlineClass = match ($oppositionCase->deadline_status) {
                                                    'green' => 'status-green',
                                                    'yellow' => 'status-orange',
                                                    default => 'status-red',
                                                };
                                                $needsOppositionDocuments = !$oppositionCase->hasRequiredDocuments();
                                                $hasOppositionDraftForApproval = filled($oppositionCase->draft_path)
                                                    && $oppositionCase->client_approval_status !== 'approved';
                                            @endphp
                                            <tr>
                                                <td>
                                                    <span class="dashboard-primary-text">{{ $oppositionCase->case_number }}</span>
                                                    <span class="dashboard-subtext">{{ $oppositionCase->application_number }}</span>
                                                </td>
                                                <td>
                                                    <div class="dashboard-trademark-cell">
                                                        <span>{{ $oppositionCase->trademark_name }}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="dashboard-badge {{ $deadlineClass }}">
                                                        <i class="fas fa-clock"></i> {{ $oppositionCase->counter_statement_deadline->format('d M Y') }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="dashboard-badge status-blue">
                                                        <i class="fas fa-shield-alt"></i> {{ $oppositionCase->current_client_stage }}
                                                    </span>
                                                </td>
                                                <td>{{ $oppositionCase->risk_level ?: 'Pending' }}</td>
                                                <td>{{ ucfirst($oppositionCase->payment_status) }}</td>
                                                <td>
                                                    <div class="dashboard-actions">
                                                        @if ($needsOppositionDocuments)
                                                            <a href="{{ route('trademark-opposition.action-center', $oppositionCase) }}" class="dashboard-action-btn btn-onboarding">
                                                                <i class="fas fa-cloud-upload-alt"></i> Upload Documents
                                                            </a>
                                                        @elseif ($hasOppositionDraftForApproval)
                                                            <a href="{{ route('trademark-opposition.action-center', $oppositionCase) }}" class="dashboard-action-btn btn-onboarding">
                                                                <i class="fas fa-file-signature"></i> Review Documents
                                                            </a>
                                                        @endif
                                                        <a href="{{ route('trademark-opposition.show', $oppositionCase) }}" class="dashboard-action-btn btn-track">
                                                            <i class="fas fa-crosshairs"></i> Track
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="dashboard-mobile-cards">
                                @foreach ($trademarkOppositionCases as $oppositionCase)
                                    @php
                                        $deadlineClass = match ($oppositionCase->deadline_status) {
                                            'green' => 'status-green',
                                            'yellow' => 'status-orange',
                                            default => 'status-red',
                                        };
                                        $needsOppositionDocuments = !$oppositionCase->hasRequiredDocuments();
                                        $hasOppositionDraftForApproval = filled($oppositionCase->draft_path)
                                            && $oppositionCase->client_approval_status !== 'approved';
                                    @endphp
                                    <article class="dashboard-mobile-card dashboard-application-card">
                                        <div class="dashboard-mobile-card-head">
                                            <div class="dashboard-application-title">
                                                <div class="dashboard-mobile-card-main">
                                                    <span class="dashboard-primary-text">{{ $oppositionCase->trademark_name }}</span>
                                                    <span class="dashboard-subtext">{{ $oppositionCase->case_number }} · {{ $oppositionCase->application_number }}</span>
                                                </div>
                                            </div>
                                            <span class="dashboard-badge dashboard-application-status status-blue">
                                                {{ $oppositionCase->current_client_stage }}
                                            </span>
                                        </div>
                                        <div class="dashboard-application-detail-list">
                                            <div class="dashboard-application-detail">
                                                <div class="dashboard-application-copy">
                                                    <span>Deadline</span>
                                                    <strong class="{{ $deadlineClass }}">{{ $oppositionCase->counter_statement_deadline->format('d M Y') }}</strong>
                                                </div>
                                            </div>
                                            <div class="dashboard-application-detail">
                                                <div class="dashboard-application-copy">
                                                    <span>Risk</span>
                                                    <strong>{{ $oppositionCase->risk_level ?: 'Pending' }}</strong>
                                                </div>
                                            </div>
                                            <div class="dashboard-application-detail">
                                                <div class="dashboard-application-copy">
                                                    <span>Payment</span>
                                                    <strong>{{ ucfirst($oppositionCase->payment_status) }}</strong>
                                                </div>
                                            </div>
                                            <div class="dashboard-application-detail">
                                                <div class="dashboard-application-copy">
                                                    <span>Application</span>
                                                    <strong>{{ $oppositionCase->application_number }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="dashboard-mobile-actions">
                                            @if ($needsOppositionDocuments)
                                                <a href="{{ route('trademark-opposition.action-center', $oppositionCase) }}" class="dashboard-action-btn btn-onboarding">
                                                    <i class="fas fa-cloud-upload-alt"></i> Upload Documents
                                                </a>
                                            @elseif ($hasOppositionDraftForApproval)
                                                <a href="{{ route('trademark-opposition.action-center', $oppositionCase) }}" class="dashboard-action-btn btn-onboarding">
                                                    <i class="fas fa-file-signature"></i> Review Documents
                                                </a>
                                            @endif
                                            <a href="{{ route('trademark-opposition.show', $oppositionCase) }}" class="dashboard-action-btn btn-track">
                                                <i class="fas fa-crosshairs"></i> Track
                                            </a>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                            @else
                                <div class="dashboard-empty-state">
                                    <i class="fas fa-shield-alt"></i>
                                    <p>No opposition defence cases yet.</p>
                                    <a href="{{ route('trademark-opposition.create') }}" class="btn btn-primary">Defend My Trademark</a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="dashboard-section-card">
                        <div class="dashboard-section-head">
                            <span class="dashboard-section-icon"><i class="fas fa-gavel"></i></span>
                            <h5 class="dashboard-section-title">Trademark Opposition Filing Cases</h5>
                        </div>
                        <div class="dashboard-table-wrap">
                        @if ($trademarkOpposeCases->count())
                        <div class="table-responsive dashboard-table-box">
                            <table class="table table-hover mb-0 align-middle dashboard-table">
                                <thead><tr><th>Case</th><th>Your Trademark</th><th>Trademark Opposed</th><th>Status</th><th>Recommendation</th><th>Payment</th><th>Action</th></tr></thead>
                                <tbody>
                                    @foreach ($trademarkOpposeCases as $oppositionCase)
                                        <tr>
                                            <td><span class="dashboard-primary-text">{{ $oppositionCase->case_number }}</span><span class="dashboard-subtext">{{ $oppositionCase->opposed_application_number }}</span></td>
                                            <td>{{ $oppositionCase->trademark_you_own }}</td>
                                            <td>{{ $oppositionCase->trademark_to_oppose }}</td>
                                            <td><span class="dashboard-badge status-blue"><i class="fas fa-shield-alt"></i> {{ $oppositionCase->current_client_stage }}</span></td>
                                            <td>{{ $oppositionCase->recommendation_level ?: 'Pending' }}</td>
                                            <td>{{ ucfirst($oppositionCase->payment_status) }}</td>
                                            <td><a href="{{ route('trademark-opposition.oppose.show', $oppositionCase) }}" class="dashboard-action-btn btn-track"><i class="fas fa-crosshairs"></i> Open Case</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="dashboard-mobile-cards">
                            @foreach ($trademarkOpposeCases as $oppositionCase)
                                <article class="dashboard-mobile-card dashboard-application-card">
                                    <div class="dashboard-mobile-card-head">
                                        <div class="dashboard-application-title">
                                            <div class="dashboard-mobile-card-main">
                                                <span class="dashboard-primary-text">{{ $oppositionCase->trademark_you_own }}</span>
                                                <span class="dashboard-subtext">{{ $oppositionCase->case_number }} · {{ $oppositionCase->opposed_application_number }}</span>
                                            </div>
                                        </div>
                                        <span class="dashboard-badge dashboard-application-status status-blue">
                                            {{ $oppositionCase->current_client_stage }}
                                        </span>
                                    </div>
                                    <div class="dashboard-application-detail-list">
                                        <div class="dashboard-application-detail">
                                            <div class="dashboard-application-copy">
                                                <span>Trademark opposed</span>
                                                <strong>{{ $oppositionCase->trademark_to_oppose }}</strong>
                                            </div>
                                        </div>
                                        <div class="dashboard-application-detail">
                                            <div class="dashboard-application-copy">
                                                <span>Recommendation</span>
                                                <strong>{{ $oppositionCase->recommendation_level ?: 'Pending' }}</strong>
                                            </div>
                                        </div>
                                        <div class="dashboard-application-detail">
                                            <div class="dashboard-application-copy">
                                                <span>Payment</span>
                                                <strong>{{ ucfirst($oppositionCase->payment_status) }}</strong>
                                            </div>
                                        </div>
                                        <div class="dashboard-application-detail">
                                            <div class="dashboard-application-copy">
                                                <span>Application</span>
                                                <strong>{{ $oppositionCase->opposed_application_number }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dashboard-mobile-actions">
                                        <a href="{{ route('trademark-opposition.oppose.show', $oppositionCase) }}" class="dashboard-action-btn btn-track">
                                            <i class="fas fa-crosshairs"></i> Open Case
                                        </a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        @else
                            <div class="dashboard-empty-state">
                                <i class="fas fa-gavel"></i>
                                <p>No opposition filing cases yet.</p>
                                <a href="{{ route('trademark-opposition.oppose.create') }}" class="btn btn-primary">Oppose a Trademark</a>
                            </div>
                        @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="dashboard-section-card">
                        <div class="dashboard-section-head">
                            <span class="dashboard-section-icon"><i class="fas fa-file-signature"></i></span>
                            <h5 class="dashboard-section-title">Trademark Objection Reply Cases</h5>
                        </div>
                        <div class="dashboard-table-wrap">
                        @if ($examinationReplyCases->count())
                        <div class="table-responsive dashboard-table-box">
                            <table class="table table-hover mb-0 align-middle dashboard-table">
                                <thead><tr><th>Case</th><th>Trademark</th><th>Deadline</th><th>Status</th><th>Risk</th><th>Payment</th><th>Action</th></tr></thead>
                                <tbody>
                                    @foreach ($examinationReplyCases as $replyCase)
                                        @php
                                            $deadlineClass = match ($replyCase->deadline_status) {
                                                'green' => 'status-green',
                                                'yellow' => 'status-orange',
                                                default => 'status-red',
                                            };
                                        @endphp
                                        <tr>
                                            <td><span class="dashboard-primary-text">{{ $replyCase->case_number }}</span><span class="dashboard-subtext">{{ $replyCase->application_number }}</span></td>
                                            <td>{{ $replyCase->trademark_name }}</td>
                                            <td><span class="dashboard-badge {{ $deadlineClass }}"><i class="fas fa-clock"></i> {{ $replyCase->reply_deadline->format('d M Y') }}</span></td>
                                            <td><span class="dashboard-badge status-blue"><i class="fas fa-file-alt"></i> {{ $replyCase->current_client_stage }}</span></td>
                                            <td>{{ $replyCase->risk_level ?: 'Pending' }}</td>
                                            <td>{{ ucfirst($replyCase->payment_status) }}</td>
                                            <td><a href="{{ route('examination-reply.show', $replyCase) }}" class="dashboard-action-btn btn-track"><i class="fas fa-crosshairs"></i> Open Case</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="dashboard-mobile-cards">
                            @foreach ($examinationReplyCases as $replyCase)
                                @php
                                    $deadlineClass = match ($replyCase->deadline_status) {
                                        'green' => 'status-green',
                                        'yellow' => 'status-orange',
                                        default => 'status-red',
                                    };
                                @endphp
                                <article class="dashboard-mobile-card dashboard-application-card">
                                    <div class="dashboard-mobile-card-head">
                                        <div class="dashboard-application-title">
                                            <div class="dashboard-mobile-card-main">
                                                <span class="dashboard-primary-text">{{ $replyCase->trademark_name }}</span>
                                                <span class="dashboard-subtext">{{ $replyCase->case_number }} · {{ $replyCase->application_number }}</span>
                                            </div>
                                        </div>
                                        <span class="dashboard-badge dashboard-application-status status-blue">
                                            {{ $replyCase->current_client_stage }}
                                        </span>
                                    </div>
                                    <div class="dashboard-application-detail-list">
                                        <div class="dashboard-application-detail">
                                            <div class="dashboard-application-copy">
                                                <span>Deadline</span>
                                                <strong class="{{ $deadlineClass }}">{{ $replyCase->reply_deadline->format('d M Y') }}</strong>
                                            </div>
                                        </div>
                                        <div class="dashboard-application-detail">
                                            <div class="dashboard-application-copy">
                                                <span>Risk</span>
                                                <strong>{{ $replyCase->risk_level ?: 'Pending' }}</strong>
                                            </div>
                                        </div>
                                        <div class="dashboard-application-detail">
                                            <div class="dashboard-application-copy">
                                                <span>Payment</span>
                                                <strong>{{ ucfirst($replyCase->payment_status) }}</strong>
                                            </div>
                                        </div>
                                        <div class="dashboard-application-detail">
                                            <div class="dashboard-application-copy">
                                                <span>Application</span>
                                                <strong>{{ $replyCase->application_number }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dashboard-mobile-actions">
                                        <a href="{{ route('examination-reply.show', $replyCase) }}" class="dashboard-action-btn btn-track">
                                            <i class="fas fa-crosshairs"></i> Open Case
                                        </a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        @else
                            <div class="dashboard-empty-state">
                                <i class="fas fa-file-signature"></i>
                                <p>No trademark objection reply cases yet.</p>
                                <a href="{{ route('examination-reply.create') }}" class="btn btn-primary">Start an Examination Reply</a>
                            </div>
                        @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="dashboard-section-card">
                        <div class="dashboard-section-head">
                            <span class="dashboard-section-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M8 7h7.5a4.5 4.5 0 0 1 0 9H12" />
                                    <path d="M10 5 8 7l2 2" />
                                    <path d="M12 16l-2 2 2 2" />
                                    <path d="M5 4h4" />
                                    <path d="M5 20h5" />
                                    <path d="M6.5 4v16" />
                                    <path d="M18 10.5h2.5" />
                                    <path d="M18 13.5h2.5" />
                                </svg>
                            </span>
                            <h5 class="dashboard-section-title">Stuck Trademark Recovery Cases</h5>
                        </div>
                        <div class="dashboard-table-wrap">
                            @if ($stuckTrademarkCases->count())
                            <div class="table-responsive dashboard-table-box">
                                <table class="table table-hover mb-0 align-middle dashboard-table">
                                    <thead>
                                        <tr>
                                            <th>Case</th>
                                            <th>Trademark</th>
                                            <th>Issues</th>
                                            <th>Status</th>
                                            <th>Audit</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($stuckTrademarkCases as $recoveryCase)
                                            @php
                                                $needsClientOnboarding = $recoveryCase->status === \App\Support\StuckTrademarkWorkflow::CLIENT_ONBOARDING;
                                                $needsDocumentUpload = in_array($recoveryCase->status, [
                                                    \App\Support\StuckTrademarkWorkflow::PROBLEM_IDENTIFIED,
                                                    \App\Support\StuckTrademarkWorkflow::AWAITING_DOCUMENTS,
                                                    \App\Support\StuckTrademarkWorkflow::REUPLOAD_REQUIRED,
                                                ], true);
                                                $needsAuditReportReview = $recoveryCase->status === \App\Support\StuckTrademarkWorkflow::AUDIT_REPORT_REVIEW;
                                                $recoveryActionUrl = $needsClientOnboarding
                                                    ? route('stuck-trademark.onboarding', $recoveryCase)
                                                    : ($needsDocumentUpload ? route('stuck-trademark.documents', $recoveryCase) : route('stuck-trademark.show', $recoveryCase));
                                                $recoveryActionLabel = $needsClientOnboarding
                                                    ? 'Continue Onboarding'
                                                    : ($needsDocumentUpload ? 'Upload Documents' : ($needsAuditReportReview ? 'Approve Audit Report' : 'Track'));
                                                $recoveryActionIcon = $needsAuditReportReview ? 'fa-check-circle' : (($needsClientOnboarding || $needsDocumentUpload) ? 'fa-arrow-right' : 'fa-crosshairs');
                                            @endphp
                                            <tr>
                                                <td>
                                                    <span class="dashboard-primary-text">{{ $recoveryCase->case_number }}</span>
                                                    <span class="dashboard-subtext">{{ $recoveryCase->application_number ?: 'No application no.' }}</span>
                                                </td>
                                                <td>
                                                    <div class="dashboard-trademark-cell">
                                                        <span>{{ $recoveryCase->trademark_name }}</span>
                                                    </div>
                                                </td>
                                                <td><div class="dashboard-issues">{{ $recoveryCase->issue_summary ?: 'Not classified' }}</div></td>
                                                <td>
                                                    @php
                                                        $caseStatusClass = match ($recoveryCase->status) {
                                                            \App\Support\StuckTrademarkWorkflow::EXECUTION_PAYMENT_PENDING, \App\Support\StuckTrademarkWorkflow::MONITORING => 'status-dark',
                                                            \App\Support\StuckTrademarkWorkflow::AWAITING_APPROVAL, \App\Support\StuckTrademarkWorkflow::AUDIT_REPORT_REVIEW => 'status-orange',
                                                            \App\Support\StuckTrademarkWorkflow::AUDIT_PENDING => 'status-purple',
                                                            \App\Support\StuckTrademarkWorkflow::AUDIT_IN_PROGRESS, \App\Support\StuckTrademarkWorkflow::EXECUTION_ACTIVE => 'status-blue',
                                                            \App\Support\StuckTrademarkWorkflow::RESOLVED, \App\Support\StuckTrademarkWorkflow::DOCUMENTS_VERIFIED, \App\Support\StuckTrademarkWorkflow::AUDIT_COMPLETED => 'status-green',
                                                            \App\Support\StuckTrademarkWorkflow::REUPLOAD_REQUIRED, \App\Support\StuckTrademarkWorkflow::AUDIT_REPORT_REUPLOAD_REQUESTED => 'status-red',
                                                            default => 'status-gray',
                                                        };
                                                        $caseStatusIcon = match ($recoveryCase->status) {
                                                            \App\Support\StuckTrademarkWorkflow::AWAITING_APPROVAL, \App\Support\StuckTrademarkWorkflow::AUDIT_REPORT_REVIEW => 'fa-hourglass-half',
                                                            \App\Support\StuckTrademarkWorkflow::AUDIT_PENDING => 'fa-shield-alt',
                                                            \App\Support\StuckTrademarkWorkflow::AUDIT_IN_PROGRESS => 'fa-spinner',
                                                            \App\Support\StuckTrademarkWorkflow::EXECUTION_PAYMENT_PENDING => 'fa-clock',
                                                            default => 'fa-circle',
                                                        };
                                                    @endphp
                                                    <span class="dashboard-badge {{ $caseStatusClass }}">
                                                        <i class="fas {{ $caseStatusIcon }}"></i> {{ $recoveryCase->status_label }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if ($recoveryCase->audit_payment_status === 'paid')
                                                        <span class="dashboard-audit-pill is-paid"><i class="far fa-check-circle"></i> Paid</span>
                                                    @else
                                                        <span class="dashboard-audit-pill is-pending"><i class="far fa-clock"></i> {{ ucfirst($recoveryCase->audit_payment_status) }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <a href="{{ $recoveryActionUrl }}" class="dashboard-action-btn {{ ($needsClientOnboarding || $needsDocumentUpload) ? 'btn-onboarding' : 'btn-track' }}">
                                                        <i class="fas {{ $recoveryActionIcon }}"></i> {{ $recoveryActionLabel }}
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="dashboard-mobile-cards">
                                @foreach ($stuckTrademarkCases as $recoveryCase)
                                    @php
                                        $caseStatusClass = match ($recoveryCase->status) {
                                            \App\Support\StuckTrademarkWorkflow::EXECUTION_PAYMENT_PENDING, \App\Support\StuckTrademarkWorkflow::MONITORING => 'status-dark',
                                            \App\Support\StuckTrademarkWorkflow::AWAITING_APPROVAL, \App\Support\StuckTrademarkWorkflow::AUDIT_REPORT_REVIEW => 'status-orange',
                                            \App\Support\StuckTrademarkWorkflow::AUDIT_PENDING => 'status-purple',
                                            \App\Support\StuckTrademarkWorkflow::AUDIT_IN_PROGRESS, \App\Support\StuckTrademarkWorkflow::EXECUTION_ACTIVE => 'status-blue',
                                            \App\Support\StuckTrademarkWorkflow::RESOLVED, \App\Support\StuckTrademarkWorkflow::DOCUMENTS_VERIFIED, \App\Support\StuckTrademarkWorkflow::AUDIT_COMPLETED => 'status-green',
                                            \App\Support\StuckTrademarkWorkflow::REUPLOAD_REQUIRED, \App\Support\StuckTrademarkWorkflow::AUDIT_REPORT_REUPLOAD_REQUESTED => 'status-red',
                                            default => 'status-gray',
                                        };
                                        $caseStatusIcon = match ($recoveryCase->status) {
                                            \App\Support\StuckTrademarkWorkflow::AWAITING_APPROVAL, \App\Support\StuckTrademarkWorkflow::AUDIT_REPORT_REVIEW => 'fa-hourglass-half',
                                            \App\Support\StuckTrademarkWorkflow::AUDIT_PENDING => 'fa-shield-alt',
                                            \App\Support\StuckTrademarkWorkflow::AUDIT_IN_PROGRESS => 'fa-spinner',
                                            \App\Support\StuckTrademarkWorkflow::EXECUTION_PAYMENT_PENDING => 'fa-clock',
                                            default => 'fa-circle',
                                        };
                                        $needsClientOnboarding = $recoveryCase->status === \App\Support\StuckTrademarkWorkflow::CLIENT_ONBOARDING;
                                        $needsDocumentUpload = in_array($recoveryCase->status, [
                                            \App\Support\StuckTrademarkWorkflow::PROBLEM_IDENTIFIED,
                                            \App\Support\StuckTrademarkWorkflow::AWAITING_DOCUMENTS,
                                            \App\Support\StuckTrademarkWorkflow::REUPLOAD_REQUIRED,
                                        ], true);
                                        $needsAuditReportReview = $recoveryCase->status === \App\Support\StuckTrademarkWorkflow::AUDIT_REPORT_REVIEW;
                                        $recoveryActionUrl = $needsClientOnboarding
                                            ? route('stuck-trademark.onboarding', $recoveryCase)
                                            : ($needsDocumentUpload ? route('stuck-trademark.documents', $recoveryCase) : route('stuck-trademark.show', $recoveryCase));
                                        $recoveryActionLabel = $needsClientOnboarding
                                            ? 'Continue Onboarding'
                                            : ($needsDocumentUpload ? 'Upload Documents' : ($needsAuditReportReview ? 'Approve Audit Report' : 'Track'));
                                        $recoveryActionIcon = $needsAuditReportReview ? 'fa-check-circle' : 'fa-chevron-right';
                                    @endphp
                                    <article class="dashboard-mobile-card dashboard-application-card">
                                        <div class="dashboard-mobile-card-head">
                                            <div class="dashboard-application-title">
                                                <div class="dashboard-mobile-card-main">
                                                    <span class="dashboard-primary-text">{{ $recoveryCase->trademark_name }}</span>
                                                    <span class="dashboard-subtext">{{ $recoveryCase->case_number }} · {{ $recoveryCase->application_number ?: 'No application no.' }}</span>
                                                </div>
                                            </div>
                                            <span class="dashboard-badge dashboard-application-status {{ $caseStatusClass }}">{{ $recoveryCase->status_label }}</span>
                                        </div>
                                        <div class="dashboard-application-detail-list">
                                            <div class="dashboard-application-detail">
                                                <div class="dashboard-application-copy">
                                                    <span>Case</span>
                                                    <strong>{{ $recoveryCase->case_number }}</strong>
                                                </div>
                                            </div>
                                            <div class="dashboard-application-detail">
                                                <div class="dashboard-application-copy">
                                                    <span>Issues</span>
                                                    <strong>{{ $recoveryCase->issue_summary ?: 'Not classified' }}</strong>
                                                </div>
                                            </div>
                                            <div class="dashboard-application-detail">
                                                <div class="dashboard-application-copy">
                                                    <span>Audit</span>
                                                    <strong class="{{ $recoveryCase->audit_payment_status === 'paid' ? 'dashboard-payment-text is-success' : 'dashboard-payment-text is-warning' }}">
                                                        {{ ucfirst($recoveryCase->audit_payment_status) }}
                                                    </strong>
                                                </div>
                                            </div>
                                            <div class="dashboard-application-detail">
                                                <div class="dashboard-application-copy">
                                                    <span>Updated</span>
                                                    <strong>{{ $recoveryCase->updated_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="dashboard-mobile-actions">
                                            <a href="{{ $recoveryActionUrl }}" class="dashboard-application-primary-action">
                                                <i class="fas {{ $recoveryActionIcon }}"></i>
                                                {{ $recoveryActionLabel }}
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                            @else
                                <div class="dashboard-empty-state">
                                    <i class="fas fa-undo-alt"></i>
                                    <p>No stuck trademark recovery cases yet.</p>
                                    <a href="{{ route('stuck-trademark.landing') }}" class="btn btn-primary">Recover a Stuck Trademark</a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="dashboard-section-card">
                    <div class="dashboard-section-head">
                        <span class="dashboard-section-icon"><i class="fas fa-file-alt"></i></span>
                        <h5 class="dashboard-section-title">My Trademark Search Reports</h5>
                    </div>
                    <div class="dashboard-table-wrap">
                        @if ($trademarkSearchReportRequests->isNotEmpty())
                            <div class="table-responsive dashboard-table-box">
                                <table class="table table-hover mb-0 dashboard-table">
                                    <thead><tr><th>Brand</th><th>Requested</th><th>Fee</th><th>Payment</th><th>Report Status</th><th>Action</th></tr></thead>
                                    <tbody>
                                        @foreach ($trademarkSearchReportRequests as $reportRequest)
                                            <tr>
                                                <td><span class="dashboard-primary-text">{{ $reportRequest->brand_name }}</span><span class="dashboard-subtext">Trademark Search Report</span></td>
                                                <td><span class="dashboard-primary-text">{{ $reportRequest->created_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}</span></td>
                                                <td><span class="dashboard-primary-text">£{{ number_format((float) $reportRequest->amount, 2) }}</span></td>
                                                <td><span class="dashboard-payment-text {{ $reportRequest->payment_status === 'paid' ? 'is-success' : 'is-warning' }}"><i class="fas {{ $reportRequest->payment_status === 'paid' ? 'fa-check-circle' : 'fa-clock' }}"></i> {{ $reportRequest->payment_status === 'paid' ? 'Paid' : 'Pending' }}</span></td>
                                                <td><span class="dashboard-badge {{ in_array($reportRequest->report_status, ['report_ready', 'completed'], true) ? 'status-blue' : 'status-orange' }}">{{ $reportRequest->report_status_label }}</span></td>
                                                <td>
                                                    @if ($isAdminPreview)
                                                        <span class="dashboard-subtext">Client action</span>
                                                    @elseif ($reportRequest->payment_status !== 'paid')
                                                        <a href="{{ route('trademark-search-report.payment', $reportRequest) }}" class="dashboard-action-btn btn-pay"><i class="fas fa-credit-card"></i> Pay Now</a>
                                                    @elseif ($reportRequest->documents->isNotEmpty())
                                                        <a href="{{ route('trademark-search-report.success', $reportRequest) }}" class="dashboard-action-btn btn-view"><i class="fas fa-file-download"></i> View Reports ({{ $reportRequest->documents->count() }})</a>
                                                    @else
                                                        <a href="{{ route('trademark-search-report.success', $reportRequest) }}" class="dashboard-action-btn btn-view"><i class="fas fa-eye"></i> View Status</a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="dashboard-mobile-cards">
                                @foreach ($trademarkSearchReportRequests as $reportRequest)
                                    <article class="dashboard-mobile-card dashboard-application-card">
                                        <div class="dashboard-mobile-card-head">
                                            <div class="dashboard-mobile-card-main"><span class="dashboard-primary-text">{{ $reportRequest->brand_name }}</span><span class="dashboard-subtext">Trademark Search Report</span></div>
                                            <span class="dashboard-badge dashboard-application-status {{ in_array($reportRequest->report_status, ['report_ready', 'completed'], true) ? 'status-blue' : 'status-orange' }}">{{ $reportRequest->report_status_label }}</span>
                                        </div>
                                        <div class="dashboard-application-detail-list">
                                            <div class="dashboard-application-detail"><div class="dashboard-application-copy"><span>Requested</span><strong>{{ $reportRequest->created_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}</strong></div></div>
                                            <div class="dashboard-application-detail"><div class="dashboard-application-copy"><span>Fee</span><strong>£{{ number_format((float) $reportRequest->amount, 2) }}</strong></div></div>
                                            <div class="dashboard-application-detail"><div class="dashboard-application-copy"><span>Payment</span><strong class="dashboard-payment-text {{ $reportRequest->payment_status === 'paid' ? 'is-success' : 'is-warning' }}">{{ $reportRequest->payment_status === 'paid' ? 'Paid' : 'Pending' }}</strong></div></div>
                                        </div>
                                        @unless ($isAdminPreview)
                                            <div class="dashboard-mobile-actions">
                                                @if ($reportRequest->payment_status !== 'paid')
                                                    <a href="{{ route('trademark-search-report.payment', $reportRequest) }}" class="dashboard-application-primary-action"><i class="fas fa-credit-card"></i> Pay Now <i class="fas fa-chevron-right"></i></a>
                                                @else
                                                    @if ($reportRequest->documents->isNotEmpty())
                                                        <a href="{{ route('trademark-search-report.success', $reportRequest) }}" class="dashboard-application-primary-action"><i class="fas fa-file-download"></i> View Reports ({{ $reportRequest->documents->count() }}) <i class="fas fa-chevron-right"></i></a>
                                                    @else
                                                        <a href="{{ route('trademark-search-report.success', $reportRequest) }}" class="dashboard-application-primary-action"><i class="fas fa-eye"></i> View Status <i class="fas fa-chevron-right"></i></a>
                                                    @endif
                                                @endif
                                            </div>
                                        @endunless
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <div class="dashboard-empty-state"><i class="fas fa-search"></i><p>No trademark search reports requested yet.</p><a href="{{ route('trademark-search-report.create') }}" class="btn btn-primary">Request a Search Report</a></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="dashboard-section-card">
                    <div class="dashboard-section-head">
                        <span class="dashboard-section-icon"><i class="fas fa-calendar-check"></i></span>
                        <h5 class="dashboard-section-title">My Consultation Calls</h5>
                    </div>
                    <div class="dashboard-table-wrap">
                        @if ($consultationBookings->isNotEmpty())
                            <div class="table-responsive dashboard-table-box">
                                <table class="table table-hover mb-0 dashboard-table">
                                    <thead><tr><th>Consultation</th><th>Preferred Slot</th><th>Fee</th><th>Payment</th><th>Status</th><th>Action</th></tr></thead>
                                    <tbody>
                                        @foreach ($consultationBookings as $consultation)
                                            <tr>
                                                <td><span class="dashboard-primary-text">{{ $consultation->topic_label }}</span><span class="dashboard-subtext">Requested {{ $consultation->created_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}</span></td>
                                                <td><span class="dashboard-primary-text">{{ $consultation->preferred_date->format('d M Y') }}</span><span class="dashboard-subtext">{{ $consultation->time_slot_label }}</span></td>
                                                <td><span class="dashboard-primary-text">£{{ number_format((float) $consultation->amount, 2) }}</span></td>
                                                <td>
                                                    <span class="dashboard-payment-text {{ $consultation->payment_status === 'paid' ? 'is-success' : 'is-warning' }}">
                                                        <i class="fas {{ $consultation->payment_status === 'paid' ? 'fa-check-circle' : 'fa-clock' }}"></i>
                                                        {{ $consultation->payment_status === 'paid' ? 'Paid' : 'Pending' }}
                                                    </span>
                                                </td>
                                                <td><span class="dashboard-badge {{ $consultation->payment_status === 'paid' ? 'status-blue' : 'status-orange' }}">{{ $consultation->consultation_status_label }}</span></td>
                                                <td>
                                                    @if ($consultation->payment_status === 'paid')
                                                        <a href="{{ route('book-call.success', $consultation) }}" class="dashboard-action-btn btn-view"><i class="fas fa-eye"></i> View Confirmation</a>
                                                    @else
                                                        <a href="{{ route('book-call.payment', $consultation) }}" class="dashboard-action-btn btn-pay"><i class="fas fa-credit-card"></i> Pay Now</a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="dashboard-mobile-cards">
                                @foreach ($consultationBookings as $consultation)
                                    <article class="dashboard-mobile-card dashboard-application-card">
                                        <div class="dashboard-mobile-card-head">
                                            <div class="dashboard-mobile-card-main"><span class="dashboard-primary-text">{{ $consultation->topic_label }}</span><span class="dashboard-subtext">Consultation call</span></div>
                                            <span class="dashboard-badge dashboard-application-status {{ $consultation->payment_status === 'paid' ? 'status-blue' : 'status-orange' }}">{{ $consultation->consultation_status_label }}</span>
                                        </div>
                                        <div class="dashboard-application-detail-list">
                                            <div class="dashboard-application-detail"><div class="dashboard-application-copy"><span>Preferred Date</span><strong>{{ $consultation->preferred_date->format('d M Y') }}</strong></div></div>
                                            <div class="dashboard-application-detail"><div class="dashboard-application-copy"><span>Preferred Time</span><strong>{{ $consultation->time_slot_label }}</strong></div></div>
                                            <div class="dashboard-application-detail"><div class="dashboard-application-copy"><span>Fee</span><strong>£{{ number_format((float) $consultation->amount, 2) }}</strong></div></div>
                                            <div class="dashboard-application-detail"><div class="dashboard-application-copy"><span>Payment</span><strong class="dashboard-payment-text {{ $consultation->payment_status === 'paid' ? 'is-success' : 'is-warning' }}">{{ $consultation->payment_status === 'paid' ? 'Paid' : 'Pending' }}</strong></div></div>
                                        </div>
                                        <div class="dashboard-mobile-actions">
                                            @if ($consultation->payment_status === 'paid')
                                                <a href="{{ route('book-call.success', $consultation) }}" class="dashboard-application-primary-action"><i class="fas fa-eye"></i> View Confirmation <i class="fas fa-chevron-right"></i></a>
                                            @else
                                                <a href="{{ route('book-call.payment', $consultation) }}" class="dashboard-application-primary-action"><i class="fas fa-credit-card"></i> Pay Now <i class="fas fa-chevron-right"></i></a>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <div class="dashboard-empty-state"><i class="fas fa-calendar-plus"></i><p>No consultation calls booked yet.</p><a href="{{ route('book-call.create') }}" class="btn btn-primary">Book a Consultation</a></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="dashboard-section-card">
                    <div class="dashboard-section-head">
                        <span class="dashboard-section-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M8 6V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v1" />
                                <path d="M4 7h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z" />
                                <path d="M9 13h6" />
                                <path d="M12 10v6" />
                            </svg>
                        </span>
                        <h5 class="dashboard-section-title">My Applications</h5>
                    </div>
                    <div class="dashboard-table-wrap">
                        @if ($applications->count())
                            <div class="table-responsive dashboard-table-box">
                                <table class="table table-hover mb-0 dashboard-table">
                                    <thead>
                                        <tr>
                                            <th>Trademark</th>
                                            <th>Application ID</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Payment</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($applications as $app)
                                            <tr>
                                                <td>
                                                    @php
                                                        $trademarkImagePath = $app->logo_path ?: data_get($app->members_details, 'trademark_details.image_of_trademark');
                                                    @endphp
                                                    <div class="dashboard-trademark-cell">
                                                        @if ($trademarkImagePath)
                                                            <span class="dashboard-logo-frame">
                                                                <img src="{{ $trademarkImageUrl($app) }}" alt="{{ $app->brand_name }} trademark logo">
                                                            </span>
                                                        @endif
                                                        <span>
                                                            <span class="dashboard-primary-text">{{ $app->brand_name }}</span>
                                                            <span class="dashboard-subtext">Application No: {{ $app->application_number ?? 'Awaiting assignment' }}</span>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>#{{ $app->id }}</td>
                                                <td>{{ $app->entity_type === 'individual' ? 'Individual / Proprietor / Trader' : ucfirst($app->entity_type) }}</td>
                                                <td>
                                                    @php
                                                        $appStatusClass = match ($app->status_label) {
                                                            'Registered' => 'status-green',
                                                            'Opposed', 'Withdrawn or Closed' => 'status-red',
                                                            default => match ($app->current_status) {
                                                                'ONBOARDING_PENDING', 'PAYMENT_PENDING_FINAL', 'FILED', 'POST_FILING' => 'status-blue',
                                                                'AWAITING_APPROVAL', 'APPROVED_FOR_FILING', 'PAYMENT_COMPLETED' => 'status-green',
                                                                'UNDER_REVIEW', 'APPLICATION_SUBMITTED', 'STRATEGY_COMPLETED', 'DRAFT_READY' => 'status-orange',
                                                                'CHANGES_REQUESTED', 'REJECTED' => 'status-red',
                                                                'STRATEGY_IN_PROGRESS' => 'status-dark',
                                                                default => 'status-gray',
                                                            },
                                                        };
                                                    @endphp
                                                    <span class="dashboard-badge {{ $appStatusClass }}">
                                                        {{ $app->status_label }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @php
                                                        $payments = $app->payments->sortByDesc('id');
                                                        $completedPayments = $payments->filter(fn ($payment) => in_array(strtolower((string) $payment->status), ['completed', 'approved'], true));
                                                        $hasFinalPayment = $completedPayments->contains(function ($payment) {
                                                            $paymentType = strtolower((string) ($payment->payment_type ?? ''));
                                                            $percentage = strtolower((string) ($payment->percentage ?? ''));

                                                            return in_array($paymentType, ['final', 'full'], true) || $percentage === '100%';
                                                        });
                                                        $hasAdvancePayment = $completedPayments->contains(function ($payment) {
                                                            $paymentType = strtolower((string) ($payment->payment_type ?? ''));
                                                            $percentage = strtolower((string) ($payment->percentage ?? ''));

                                                            return $paymentType === 'advance' || $percentage === '50%';
                                                        });
                                                        $rejectedPayment = $payments->firstWhere('status', 'rejected');
                                                    @endphp
                                                    @if ($hasFinalPayment)
                                                        <span class="dashboard-payment-text is-success"><i class="fas fa-check"></i> Full Payment Done</span>
                                                    @elseif ($hasAdvancePayment)
                                                        <span class="dashboard-payment-text is-success"><i class="fas fa-check"></i> Half Payment Done</span>
                                                    @elseif ($rejectedPayment)
                                                        <span class="dashboard-payment-text is-danger"><i class="fas fa-times-circle"></i>
                                                            Rejected</span>
                                                    @else
                                                        <span class="dashboard-payment-text is-warning"><i class="fas fa-clock"></i>
                                                            Pending</span>
                                                    @endif
                                                </td>
                                                <td><i class="far fa-calendar-alt text-muted me-1"></i>{{ $app->created_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}</td>
                                                <td>
                                                    <div class="dashboard-actions">
                                                        @if ($app->current_status === $workflow::DRAFT)
                                                            <a href="{{ route('payment.show', $app->id) }}"
                                                                class="dashboard-action-btn btn-pay" title="Complete Payment">
                                                                <i class="fas fa-credit-card"></i> Pay
                                                            </a>
                                                        @elseif($app->current_status === $workflow::APPLICATION_SUBMITTED)
                                                            <a href="{{ $trademarkStatusUrl($app) }}"
                                                                class="dashboard-action-btn btn-view" title="Track Application">
                                                                <i class="fas fa-eye"></i> Track Application
                                                            </a>
                                                        @elseif($app->current_status === $workflow::ONBOARDING_PENDING)
                                                            <a href="{{ $trademarkStatusUrl($app, true) }}#stage-action"
                                                                class="dashboard-action-btn btn-onboarding" title="Submit Signatures">
                                                                <i class="fas fa-rocket"></i> Onboarding
                                                            </a>
                                                        @elseif($app->current_status === $workflow::AWAITING_APPROVAL)
                                                            <a href="{{ $trademarkStatusUrl($app, true) }}#stage-action"
                                                                class="dashboard-action-btn btn-successish" title="Review Draft">
                                                                <i class="fas fa-check-circle"></i> Client Approval
                                                            </a>
                                                        @elseif($app->current_status === $workflow::PAYMENT_PENDING_FINAL)
                                                            <a href="{{ route('payment.show', $app->id) }}"
                                                                class="dashboard-action-btn btn-pay" title="Complete Final Payment">
                                                                <i class="fas fa-credit-card"></i> Final Pay
                                                            </a>
                                                        @endif
                                                        <a href="{{ $trademarkStatusUrl($app) }}"
                                                            class="dashboard-action-btn btn-view" title="Track Application">
                                                            <i class="fas fa-eye"></i> Track Application
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="dashboard-mobile-cards">
                                @foreach ($applications as $app)
                                    @php
                                        $trademarkImagePath = $app->logo_path ?: data_get($app->members_details, 'trademark_details.image_of_trademark');
                                        $appStatusClass = match ($app->status_label) {
                                            'Registered' => 'status-green',
                                            'Opposed', 'Withdrawn or Closed' => 'status-red',
                                            default => match ($app->current_status) {
                                                'ONBOARDING_PENDING', 'PAYMENT_PENDING_FINAL', 'FILED', 'POST_FILING' => 'status-blue',
                                                'AWAITING_APPROVAL', 'APPROVED_FOR_FILING', 'PAYMENT_COMPLETED' => 'status-green',
                                                'UNDER_REVIEW', 'APPLICATION_SUBMITTED', 'STRATEGY_COMPLETED', 'DRAFT_READY' => 'status-orange',
                                                'CHANGES_REQUESTED', 'REJECTED' => 'status-red',
                                                'STRATEGY_IN_PROGRESS' => 'status-dark',
                                                default => 'status-gray',
                                            },
                                        };
                                        $payments = $app->payments->sortByDesc('id');
                                        $completedPayments = $payments->filter(fn ($payment) => in_array(strtolower((string) $payment->status), ['completed', 'approved'], true));
                                        $hasFinalPayment = $completedPayments->contains(function ($payment) {
                                            $paymentType = strtolower((string) ($payment->payment_type ?? ''));
                                            $percentage = strtolower((string) ($payment->percentage ?? ''));

                                            return in_array($paymentType, ['final', 'full'], true) || $percentage === '100%';
                                        });
                                        $hasAdvancePayment = $completedPayments->contains(function ($payment) {
                                            $paymentType = strtolower((string) ($payment->payment_type ?? ''));
                                            $percentage = strtolower((string) ($payment->percentage ?? ''));

                                            return $paymentType === 'advance' || $percentage === '50%';
                                        });
                                        $rejectedPayment = $payments->firstWhere('status', 'rejected');
                                        $paymentLabel = $hasFinalPayment
                                            ? 'Full Payment Done'
                                            : ($hasAdvancePayment
                                                ? 'Half Payment Done'
                                                : ($rejectedPayment ? 'Rejected' : 'Pending'));
                                        $paymentStateClass = $hasFinalPayment || $hasAdvancePayment
                                            ? 'is-success'
                                            : ($rejectedPayment ? 'is-danger' : 'is-warning');
                                        $primaryActionUrl = $trademarkStatusUrl($app);
                                        $primaryActionLabel = 'Track Application';
                                        $primaryActionIcon = 'fa-eye';

                                        if ($app->current_status === $workflow::DRAFT) {
                                            $primaryActionUrl = route('payment.show', $app->id);
                                            $primaryActionLabel = 'Pay';
                                            $primaryActionIcon = 'fa-credit-card';
                                        } elseif ($app->current_status === $workflow::ONBOARDING_PENDING) {
                                            $primaryActionUrl = $trademarkStatusUrl($app, true) . '#stage-action';
                                            $primaryActionLabel = 'Onboarding';
                                            $primaryActionIcon = 'fa-rocket';
                                        } elseif ($app->current_status === $workflow::AWAITING_APPROVAL) {
                                            $primaryActionUrl = $trademarkStatusUrl($app, true) . '#stage-action';
                                            $primaryActionLabel = 'Review Draft';
                                            $primaryActionIcon = 'fa-check-circle';
                                        } elseif ($app->current_status === $workflow::PAYMENT_PENDING_FINAL) {
                                            $primaryActionUrl = route('payment.show', $app->id);
                                            $primaryActionLabel = 'Final Pay';
                                            $primaryActionIcon = 'fa-credit-card';
                                        }
                                    @endphp
                                    <article class="dashboard-mobile-card dashboard-application-card">
                                        <div class="dashboard-mobile-card-head">
                                            <div class="dashboard-application-title">
                                                @if ($trademarkImagePath)
                                                    <span class="dashboard-logo-frame">
                                                        <img src="{{ $trademarkImageUrl($app) }}" alt="{{ $app->brand_name }} trademark logo">
                                                    </span>
                                                @endif
                                                <div class="dashboard-mobile-card-main">
                                                    <span class="dashboard-primary-text">{{ $app->brand_name }}</span>
                                                    <span class="dashboard-subtext">Application No: {{ $app->application_number ?? 'Awaiting assignment' }}</span>
                                                </div>
                                            </div>
                                            <span class="dashboard-badge dashboard-application-status {{ $appStatusClass }}">{{ $app->status_label }}</span>
                                        </div>
                                        <div class="dashboard-application-detail-list">
                                            <div class="dashboard-application-detail">
                                                <div class="dashboard-application-copy">
                                                    <span>Application ID</span>
                                                    <strong>#{{ $app->id }}</strong>
                                                </div>
                                            </div>
                                            <div class="dashboard-application-detail">
                                                <div class="dashboard-application-copy">
                                                    <span>Type</span>
                                                    <strong>{{ $app->entity_type === 'individual' ? 'Individual / Proprietor / Trader' : ucfirst($app->entity_type) }}</strong>
                                                </div>
                                            </div>
                                            <div class="dashboard-application-detail">
                                                <div class="dashboard-application-copy">
                                                    <span>Payment</span>
                                                    <strong class="dashboard-payment-text {{ $paymentStateClass }}">{{ $paymentLabel }}</strong>
                                                </div>
                                            </div>
                                            <div class="dashboard-application-detail">
                                                <div class="dashboard-application-copy">
                                                    <span>Created</span>
                                                    <strong>{{ $app->created_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="dashboard-mobile-actions">
                                            <a href="{{ $primaryActionUrl }}" class="dashboard-application-primary-action">
                                                <i class="fas {{ $primaryActionIcon }}"></i>
                                                {{ $primaryActionLabel }}
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <div class="p-5 text-center">
                                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No applications yet.</p>
                                <a href="{{ route('trademark.type-selection') }}" class="btn btn-primary">
                                    Start Your First Application
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($isAdminPreview)
        <style>
            .admin-client-dashboard-preview-disabled {
                cursor: not-allowed !important;
                filter: grayscale(.45);
                opacity: .58;
            }
        </style>
        <script>
            (() => {
                const root = document.querySelector('.dashboard-shell');
                if (!root) return;

                root.querySelectorAll('a').forEach((link) => {
                    const target = new URL(link.href, window.location.origin);
                    const isPreviewNavigation = link.hasAttribute('data-admin-preview-link')
                        || target.pathname.includes('/client-action-center');

                    if (isPreviewNavigation) return;

                    link.classList.add('admin-client-dashboard-preview-disabled');
                    link.setAttribute('aria-disabled', 'true');
                    link.removeAttribute('target');
                    link.addEventListener('click', (event) => event.preventDefault());
                });

                root.querySelectorAll('form').forEach((form) => {
                    form.addEventListener('submit', (event) => event.preventDefault(), true);
                });
            })();
        </script>
    @endif
@endsection
