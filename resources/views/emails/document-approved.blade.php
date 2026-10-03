@extends('emails.layouts.simple')

@section('email_title', 'Document Approved')
@section('status_icon', '✓')
@section('heading')
    Document <strong>Approved!</strong>
@endsection

@section('body')
    <p>Dear {{ $user->name }},</p>

    <p>Your document for <strong>{{ $application->brand_name }}</strong> has been approved by our admin team.</p>

    <div class="info-box">
        The approved document is attached to this email for your records.
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
                <th>Approved On</th>
                <td>{{ $document->updated_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y, h:i A T') }}</td>
            </tr>
            <tr>
                <th>Status</th>
                <td><span class="status-pill">Approved</span></td>
            </tr>
        </table>
    </div>

    <div class="cta-wrap">
        <a href="{{ route('trademark.status', $application->id) }}" class="primary-button">Open Application Status</a>
    </div>
@endsection
