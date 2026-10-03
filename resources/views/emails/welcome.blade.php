@extends('emails.layouts.simple')

@section('email_title', 'Welcome')
@section('status_icon', '✓')
@section('heading')
    Welcome!
@endsection

@section('body')
    <p>Dear {{ $user->name }},</p>

    <p>Your account has been created and you can now start or track your trademark and IP workflows from your dashboard.</p>

    <div class="summary-card">
        <div class="summary-title">Account Summary</div>
        <table class="summary-table">
            <tr>
                <th>Name</th>
                <td>{{ $user->name }}</td>
            </tr>
            <tr>
                <th>Email</th>
                <td>{{ $user->email }}</td>
            </tr>
            <tr>
                <th>Registration Date</th>
                <td>{{ $user->created_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}</td>
            </tr>
            <tr>
                <th>Status</th>
                <td><span class="status-pill">Account Created</span></td>
            </tr>
        </table>
    </div>

    <div class="cta-wrap">
        <a href="{{ route('dashboard') }}" class="primary-button">Go to Dashboard</a>
    </div>
@endsection
