@extends('layouts.app')

@section('content')
    <div class="container-fluid py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <span class="text-uppercase fw-bold" style="color:#159485;font-size:.7rem;letter-spacing:.12em;">Paid service</span>
                <h1 class="h3 mt-2 mb-1" style="color:#1D3557;">Trademark Search Reports</h1>
                <p class="text-muted mb-0">Review requests, confirm payments, update progress and deliver private PDF reports.</p>
            </div>
            <a href="{{ route('trademark-search-report.create') }}" target="_blank" class="btn btn-outline-secondary btn-sm">View Public Form</a>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.trademark-search-reports.index') }}" class="row g-2">
                    <div class="col-lg-5"><input name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="Search name, email, brand or transaction"></div>
                    <div class="col-lg-2">
                        <select name="payment_status" class="form-select form-select-sm">
                            <option value="">All payments</option>
                            <option value="pending" @selected(request('payment_status') === 'pending')>Pending</option>
                            <option value="paid" @selected(request('payment_status') === 'paid')>Paid</option>
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <select name="report_status" class="form-select form-select-sm">
                            <option value="">All report statuses</option>
                            @foreach ($reportStatuses as $value => $label)
                                <option value="{{ $value }}" @selected(request('report_status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-grow-1" style="background:#2A9D8F;border:0;">Filter</button>
                        @if (request()->hasAny(['search', 'payment_status', 'report_status']))<a href="{{ route('admin.trademark-search-reports.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>@endif
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                @if ($reportRequests->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle admin-list-table">
                            <thead><tr><th>Client</th><th>Brand</th><th>Payment</th><th>Report Status</th><th>PDF</th><th>Requested</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($reportRequests as $reportRequest)
                                    <tr>
                                        <td><strong>{{ $reportRequest->name }}</strong><br><small class="text-muted">{{ $reportRequest->email }}</small></td>
                                        <td><strong>{{ $reportRequest->brand_name }}</strong><br><small class="text-muted">{{ Str::limit($reportRequest->business_activity, 65) }}</small></td>
                                        <td><x-admin-status :status="$reportRequest->payment_status" /><small class="text-muted d-block mt-1">£{{ number_format((float) $reportRequest->amount, 2) }}</small></td>
                                        <td><x-admin-status :status="$reportRequest->report_status" /></td>
                                        <td>@if($reportRequest->documents_count)<span class="badge text-bg-success"><i class="bi bi-file-earmark-pdf"></i> {{ $reportRequest->documents_count }} {{ Str::plural('file', $reportRequest->documents_count) }}</span>@else<span class="text-muted">Pending</span>@endif</td>
                                        <td><small>{{ $reportRequest->created_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y, h:i A T') }}</small></td>
                                        <td class="text-end"><a href="{{ route('admin.trademark-search-reports.show', $reportRequest) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{ $reportRequests->links() }}
                @else
                    <div class="text-center py-5"><i class="bi bi-file-earmark-pdf d-block mb-3" style="font-size:2rem;color:#159485;"></i><h5>No search report requests found</h5><p class="text-muted mb-0">New paid report requests will appear here.</p></div>
                @endif
            </div>
        </div>
    </div>
@endsection
