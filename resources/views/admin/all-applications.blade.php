@extends('layouts.app')

@section('content')
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0" style="color: #1D3557;">Trademark Applications</h1>
                        <p class="text-muted mb-0">Complete list of trademark applications</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="application-summary-grid mb-4">
            <a class="application-summary-card pending" href="{{ route('admin.applications') }}">
                <span class="application-summary-icon"><i class="bi bi-hourglass-split"></i></span>
                <span><strong>{{ $applicationStats['pending'] }}</strong><small>Pending Review</small></span>
            </a>
            <a class="application-summary-card approved" href="{{ route('admin.all-applications', ['status' => \App\Support\TrademarkWorkflow::ONBOARDING_PENDING]) }}">
                <span class="application-summary-icon"><i class="bi bi-check2-circle"></i></span>
                <span><strong>{{ $applicationStats['approved'] }}</strong><small>Approved</small></span>
            </a>
            <a class="application-summary-card filed" href="{{ route('admin.all-applications', ['status' => \App\Support\TrademarkWorkflow::FILED]) }}">
                <span class="application-summary-icon"><i class="bi bi-folder-check"></i></span>
                <span><strong>{{ $applicationStats['filed'] }}</strong><small>Filed</small></span>
            </a>
            <a class="application-summary-card registered" href="{{ route('admin.all-applications', ['status' => \App\Support\TrademarkWorkflow::REGISTRY_REGISTERED]) }}">
                <span class="application-summary-icon"><i class="bi bi-patch-check"></i></span>
                <span><strong>{{ $applicationStats['registered'] }}</strong><small>Registered</small></span>
            </a>
        </div>

        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form method="GET" action="{{ route('admin.all-applications') }}" class="d-flex gap-2 flex-wrap">
                            <div class="flex-grow-1" style="min-width: 200px;">
                                <input type="text" name="search" class="form-control form-control-sm"
                                    placeholder="Search by name, email, trademark..." value="{{ request('search') }}">
                            </div>
                            <select name="status" class="form-select form-select-sm" style="max-width: 150px;">
                                <option value="">All Status</option>
                                @foreach ($applicationStatuses as $value => $label)
                                    <x-admin-status-option :value="$value" :label="$label"
                                        :selected="request('status') === $value" />
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm"
                                style="background-color: #2A9D8F; border: none;">
                                🔍 Filter
                            </button>
                            @if (request('search') || request('status'))
                                <a href="{{ route('admin.all-applications') }}" class="btn btn-outline-secondary btn-sm">
                                    ✕ Clear
                                </a>
                            @endif
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Message -->
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>✅ Success!</strong> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Applications Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                @if ($applications->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle admin-list-table">
                            <thead style="background-color: #f8f9fa; border-bottom: 2px solid #2A9D8F;">
                                <tr>
                                    <th style="color: #1D3557; font-weight: 600;">ID</th>
                                    <th style="color: #1D3557; font-weight: 600;">Applicant</th>
                                    <th style="color: #1D3557; font-weight: 600;">Trademark</th>
                                    <th style="color: #1D3557; font-weight: 600;">Status</th>
                                    <th style="color: #1D3557; font-weight: 600;">Submitted</th>
                                    <th style="color: #1D3557; font-weight: 600;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($applications as $app)
                                    <tr>
                                        <td>
                                            <span class="badge" style="background-color: #2A9D8F;">
                                                #{{ $app->id }}
                                            </span>
                                        </td>
                                        <td>
                                            <div>
                                                <strong>{{ $app->user->name ?? 'N/A' }}</strong>
                                                <br>
                                                <small class="text-muted">{{ $app->user->email ?? 'N/A' }}</small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-truncate">{{ $app->brand_name ?? 'N/A' }}</span>
                                        </td>
                                        <td>
                                            @php
                                                $adminStatusLabel = ($app->registry_status === \App\Support\TrademarkWorkflow::REGISTRY_REGISTERED || filled($app->registered_at))
                                                    ? 'Registered'
                                                    : $app->status_label;
                                            @endphp
                                            <x-admin-status :status="$adminStatusLabel" />
                                        </td>
                                        <td>
                                            <small>{{ $app->created_at->timezone(config('app.timezone', 'Europe/London'))->format('M d, Y') }}</small>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.review-application', $app->id) }}"
                                                class="btn btn-sm btn-primary"
                                                style="background-color: #2A9D8F; border: none;">
                                                👁️ View
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center mt-4">
                        {{ $applications->links() }}
                    </div>
                @else
                    <div class="text-center py-5">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                        <h5 style="color: #1D3557;">No applications found</h5>
                        <p class="text-muted mb-0">Try adjusting your filters or search criteria</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <style>
        .application-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .application-summary-card {
            display: flex;
            align-items: center;
            gap: 14px;
            min-height: 104px;
            padding: 20px;
            border: 1px solid #e0e7ef;
            border-radius: 14px;
            color: #172b46;
            background: #fff;
            box-shadow: 0 8px 24px rgba(7, 31, 72, .055);
            text-decoration: none;
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .application-summary-card:hover {
            color: #172b46;
            box-shadow: 0 13px 28px rgba(7, 31, 72, .09);
            transform: translateY(-2px);
        }

        .application-summary-icon {
            display: grid;
            place-items: center;
            flex: 0 0 44px;
            height: 44px;
            border-radius: 11px;
            font-size: 1.05rem;
        }

        .application-summary-card strong,
        .application-summary-card small {
            display: block;
        }

        .application-summary-card strong {
            color: #102a4c;
            font-size: 1.35rem;
            line-height: 1;
        }

        .application-summary-card small {
            margin-top: 7px;
            color: #718096;
            font-size: .7rem;
            font-weight: 750;
        }

        .application-summary-card.pending .application-summary-icon { color: #a86e00; background: #fff6dc; }
        .application-summary-card.approved .application-summary-icon { color: #178653; background: #e7f8ef; }
        .application-summary-card.filed .application-summary-icon { color: #167a9a; background: #e6f7fb; }
        .application-summary-card.registered .application-summary-icon { color: #117a55; background: #e5f7f0; }

        table tbody tr:hover {
            background-color: #f8f9fa !important;
        }

        .btn-primary {
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(42, 157, 143, 0.3);
        }

        @media (max-width: 1050px) {
            .application-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 560px) {
            .application-summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endsection
