@extends('layouts.app')

@section('content')
    <div class="container-fluid py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1" style="color:#1D3557;">Contact Messages</h1>
                <p class="text-muted mb-0">Enquiries submitted through the website contact form.</p>
            </div>
            <a href="{{ route('contact') }}" target="_blank" class="btn btn-outline-secondary btn-sm">View Contact Page</a>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.contact-messages.index') }}" class="row g-2">
                    <div class="col-md-7">
                        <input name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="Search name, email, business, or service">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All statuses</option>
                            @foreach (['new' => 'New', 'in_progress' => 'In progress', 'resolved' => 'Resolved'] as $value => $label)
                                <x-admin-status-option :value="$value" :label="$label"
                                    :selected="request('status') === $value" />
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-grow-1" style="background:#2A9D8F;border:0;">Filter</button>
                        @if (request()->hasAny(['search', 'status'])) <a href="{{ route('admin.contact-messages.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a> @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                @if ($messages->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle admin-list-table">
                            <thead><tr><th>From</th><th>Business</th><th>Service Interested In</th><th>Payment</th><th>Received</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($messages as $message)
                                    @php
                                        $consultation = $message->consultationBooking;
                                        $paymentRecord = $consultation;
                                        $isConsultation = $consultation || in_array($message->service_interested, ['Consultation Call', 'Book a Call'], true);
                                        $isPaidService = $isConsultation;
                                    @endphp
                                    <tr>
                                        <td><strong>{{ $message->name }}</strong><br><small class="text-muted">{{ $message->email }}</small></td>
                                        <td>{{ $message->business_name ?: 'Not provided' }}</td>
                                        <td>{{ $message->service_interested ?: $message->subject }}<br><small class="text-muted">{{ Str::limit($message->message, 70) }}</small></td>
                                        <td>
                                            @if ($paymentRecord)
                                                <x-admin-status :status="$paymentRecord->payment_status" />
                                                <small class="text-muted d-block mt-1">£{{ number_format((float) $paymentRecord->amount, 2) }}</small>
                                            @elseif ($isPaidService)
                                                <span class="badge text-bg-light">Not initiated</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td><small>{{ $message->created_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y, h:i A T') }}</small></td>
                                        <td>
                                            <x-admin-status :status="$message->status" />
                                        </td>
                                        <td class="text-end"><a href="{{ route('admin.contact-messages.show', $message) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{ $messages->links() }}
                @else
                    <div class="text-center py-5"><h5>No contact messages found</h5><p class="text-muted mb-0">New website enquiries will appear here.</p></div>
                @endif
            </div>
        </div>
    </div>
@endsection
