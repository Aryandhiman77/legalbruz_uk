@extends('emails.layouts.simple')

@section('email_title', 'Document Reupload Requested')
@section('status_icon', '!')
@section('heading')
    Document <strong>Needs Reupload</strong>
@endsection

@section('body')
    <p>Dear {{ $user->name }},</p>

    <p>Your document for <strong>{{ $application->brand_name }}</strong> needs changes before it can be approved. Please review the note and reupload the corrected document.</p>

    <div class="info-box">
        The reviewed document is attached to help you identify what needs to be corrected.
    </div>

    <div class="summary-card">
        <div class="summary-title">Document Summary</div>
        <table class="summary-table">
            <tr>
                <th>Document Type</th>
                <td>{{ ucwords(str_replace('_', ' ', $document->document_type)) }}</td>
            </tr>
            <tr>
                <th>Application ID</th>
                <td>#{{ $application->id }}</td>
            </tr>
            <tr>
                <th>Application</th>
                <td>{{ $application->brand_name }}</td>
            </tr>
            <tr>
                <th>Updated On</th>
                <td>{{ $document->updated_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y, h:i A T') }}</td>
            </tr>
            <tr>
                <th>Status</th>
                <td><span class="status-pill warning">Reupload Requested</span></td>
            </tr>
        </table>
    </div>

    @if ($document->verification_notes)
        <div class="admin-note">
            <strong>Admin Note:</strong>
            <div>{{ $document->verification_notes }}</div>
        </div>
    @endif

    <div class="cta-wrap">
        <a href="{{ route('trademark.status', $application->id) }}" class="primary-button">Open Application Status</a>
    </div>
@endsection
