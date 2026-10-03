@extends('layouts.app')

@section('content')
    <div class="container-fluid py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1" style="color:#1D3557;">Career Applications</h1>
                <p class="text-muted mb-0">Review applicants, download résumés, and track hiring progress.</p>
            </div>
            <a href="{{ route('admin.career-jobs.index') }}" class="btn btn-outline-secondary btn-sm">Manage Job Roles</a>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-5"><input name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="Search applicant name or email"></div>
                    <div class="col-md-3">
                        <select name="job" class="form-select form-select-sm">
                            <option value="">All job roles</option>
                            @foreach ($jobs as $job)<option value="{{ $job->id }}" @selected((string) request('job') === (string) $job->id)>{{ $job->title }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All statuses</option>
                            @foreach (['new','reviewing','shortlisted','interview','offered','rejected','hired'] as $status)
                                <x-admin-status-option :value="$status" :selected="request('status') === $status" />
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-grow-1" style="background:#2A9D8F;border:0;">Filter</button>
                        @if (request()->hasAny(['search','job','status']))<a href="{{ route('admin.career-applications.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>@endif
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                @if ($applications->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle admin-list-table">
                            <thead><tr><th>Applicant</th><th>Job role</th><th>Experience</th><th>Applied</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($applications as $application)
                                    <tr>
                                        <td><strong>{{ $application->full_name }}</strong><br><small class="text-muted">{{ $application->email }}</small></td>
                                        <td>{{ $application->job->title }}</td>
                                        <td>{{ $application->years_experience !== null ? $application->years_experience.' years' : 'Not provided' }}</td>
                                        <td><small>{{ $application->created_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y, h:i A T') }}</small></td>
                                        <td>
                                            <x-admin-status :status="$application->status" />
                                        </td>
                                        <td class="text-end"><a href="{{ route('admin.career-applications.show', $application) }}" class="btn btn-sm btn-outline-primary">Review</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{ $applications->links() }}
                @else
                    <div class="text-center py-5"><h5>No applications found</h5><p class="text-muted mb-0">New career applications will appear here.</p></div>
                @endif
            </div>
        </div>
    </div>
@endsection
