@extends('emails.layouts.simple')

@section('email_title', 'Payment ' . ucfirst($status))
@section('status_icon', $status === 'approved' ? '✓' : '!')
@section('heading')
    Payment <strong>{{ ucfirst($status) }}</strong>
@endsection

@section('body')
    <p>Dear {{ $user->name }},</p>

    @if ($status === 'approved')
        <p>Your payment has been approved successfully. You can continue tracking your trademark application from the application status page.</p>
    @else
        <p>Your payment could not be approved. Please review the payment details and try again, or contact support for assistance.</p>
    @endif

    <div class="summary-card">
        <div class="summary-title">Payment Summary</div>
        <table class="summary-table">
            <tr>
                <th>Application</th>
                <td>{{ $payment->application->brand_name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Amount</th>
                <td>£{{ number_format($payment->amount, 2) }}</td>
            </tr>
            <tr>
                <th>Transaction ID</th>
                <td>{{ $payment->transaction_id ?? 'N/A' }}</td>
            </tr>
            @if ($status === 'approved')
                <tr>
                    <th>Date</th>
                    <td>{{ $payment->paid_at?->timezone(config('app.timezone', 'Europe/London'))->format('d M Y, h:i A T') ?? now(config('app.timezone', 'Europe/London'))->format('d M Y, h:i A T') }}</td>
                </tr>
            @endif
            <tr>
                <th>Status</th>
                <td><span class="status-pill {{ $status === 'approved' ? '' : 'danger' }}">{{ ucfirst($status) }}</span></td>
            </tr>
        </table>
    </div>

    <div class="admin-note">
        <strong>Government fees are not included.</strong>
        <div>Any applicable Government or Registry fee must be paid separately.</div>
    </div>

    @if ($reason)
        <div class="admin-note">
            <strong>Reason:</strong>
            <div>{{ $reason }}</div>
        </div>
    @endif

    <div class="cta-wrap">
        <a href="{{ route('trademark.status', $payment->application_id) }}" class="primary-button">Open Application Status</a>
    </div>
@endsection
