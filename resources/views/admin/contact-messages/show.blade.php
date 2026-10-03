@extends('layouts.app')

@section('content')
    <div class="container py-4" style="max-width:900px;">
        <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1" style="color:#1D3557;">{{ $contactMessage->service_interested ?: $contactMessage->subject }}</h1>
                <p class="text-muted mb-0">Received {{ $contactMessage->created_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y, h:i A T') }}</p>
            </div>
            <a href="{{ route('admin.contact-messages.index') }}" class="btn btn-outline-secondary btn-sm align-self-start">Back to Inbox</a>
        </div>

        @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <dl class="row mb-4">
                    <dt class="col-sm-3">Name</dt><dd class="col-sm-9">{{ $contactMessage->name }}</dd>
                    <dt class="col-sm-3">Email</dt><dd class="col-sm-9"><a href="mailto:{{ $contactMessage->email }}">{{ $contactMessage->email }}</a></dd>
                    <dt class="col-sm-3">Phone</dt><dd class="col-sm-9">{{ $contactMessage->phone ?: 'Not provided' }}</dd>
                    <dt class="col-sm-3">Business Name</dt><dd class="col-sm-9">{{ $contactMessage->business_name ?: 'Not provided' }}</dd>
                    <dt class="col-sm-3">Service Interested In</dt><dd class="col-sm-9">{{ $contactMessage->service_interested ?: $contactMessage->subject }}</dd>
                </dl>
                <h2 class="h5">Message</h2>
                <div class="p-3 rounded" style="background:#f7f9fb;white-space:pre-wrap;line-height:1.7;">{{ $contactMessage->message }}</div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.contact-messages.update', $contactMessage) }}" class="d-flex flex-wrap align-items-end gap-3">
                    @csrf @method('PATCH')
                    <div class="flex-grow-1">
                        <label for="status" class="form-label fw-bold">Enquiry status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="new" @selected($contactMessage->status === 'new')>New</option>
                            <option value="in_progress" @selected($contactMessage->status === 'in_progress')>In progress</option>
                            <option value="resolved" @selected($contactMessage->status === 'resolved')>Resolved</option>
                        </select>
                    </div>
                    <a href="mailto:{{ $contactMessage->email }}?subject={{ rawurlencode('Re: '.($contactMessage->service_interested ?: $contactMessage->subject)) }}" class="btn btn-outline-primary">Reply by Email</a>
                    <button class="btn btn-primary" style="background:#2A9D8F;border:0;">Update Status</button>
                </form>
            </div>
        </div>
    </div>
@endsection
