@extends('layouts.app')

@section('content')
    <div class="container py-4" style="max-width:1000px;">
        <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1" style="color:#1D3557;">{{ $application->full_name }}</h1>
                <p class="text-muted mb-0">Applied for {{ $application->job->title }} on {{ $application->created_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y, h:i A T') }}</p>
            </div>
            <a href="{{ route('admin.career-applications.index') }}" class="btn btn-outline-secondary btn-sm align-self-start">Back to Applications</a>
        </div>

        @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">Applicant details</h2>
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><a href="mailto:{{ $application->email }}">{{ $application->email }}</a></dd>
                            <dt class="col-sm-4">Phone</dt><dd class="col-sm-8"><a href="tel:{{ $application->phone }}">{{ $application->phone }}</a></dd>
                            <dt class="col-sm-4">Current location</dt><dd class="col-sm-8">{{ $application->current_location ?: 'Not provided' }}</dd>
                            <dt class="col-sm-4">Experience</dt><dd class="col-sm-8">{{ $application->years_experience !== null ? $application->years_experience.' years' : 'Not provided' }}</dd>
                            <dt class="col-sm-4">LinkedIn</dt><dd class="col-sm-8">@if($application->linkedin_url)<a href="{{ $application->linkedin_url }}" target="_blank" rel="noopener">Open profile</a>@else Not provided @endif</dd>
                            <dt class="col-sm-4">Portfolio</dt><dd class="col-sm-8">@if($application->portfolio_url)<a href="{{ $application->portfolio_url }}" target="_blank" rel="noopener">Open portfolio</a>@else Not provided @endif</dd>
                        </dl>
                    </div>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="h5">Cover letter</h2>
                        <div class="p-3 rounded" style="background:#f7f9fb;white-space:pre-wrap;line-height:1.7;">{{ $application->cover_letter }}</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-4">
                        <h2 class="h5">Résumé</h2>
                        <p class="small text-muted text-break">{{ $application->resume_original_name }}</p>
                        <a href="{{ route('admin.career-applications.resume', $application) }}" class="btn btn-outline-primary w-100"><i class="fas fa-download"></i> Download Résumé</a>
                    </div>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <form method="POST" action="{{ route('admin.career-applications.update', $application) }}">
                            @csrf @method('PATCH')
                            <label for="status" class="form-label fw-bold">Application status</label>
                            <select id="status" name="status" class="form-select mb-3" required>
                                @foreach (['new','reviewing','shortlisted','interview','offered','rejected','hired'] as $status)
                                    <option value="{{ $status }}" @selected($application->status === $status)>{{ Str::headline($status) }}</option>
                                @endforeach
                            </select>
                            <label for="admin_notes" class="form-label fw-bold">Internal notes</label>
                            <textarea id="admin_notes" name="admin_notes" class="form-control mb-3" rows="6" maxlength="5000">{{ old('admin_notes', $application->admin_notes) }}</textarea>
                            <button class="btn btn-primary w-100" style="background:#2A9D8F;border:0;">Save Review</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
