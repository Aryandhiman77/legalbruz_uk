@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h1 class="h3 mb-1">Payment history</h1><p class="text-muted mb-0">All your completed service payments and recent payment attempts.</p></div>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Back to dashboard</a>
    </div>
    <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Date</th><th>Service</th><th>Reference</th><th>Amount</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($payments as $payment)
            <tr>
                <td>{{ $payment['occurred_at']->timezone(config('app.timezone'))->format('d M Y, H:i') }}</td>
                <td>{{ $payment['service'] }}</td>
                <td><code>{{ $payment['reference'] }}</code></td>
                <td>{{ $payment['currency'] }} {{ number_format($payment['amount'], 2) }}</td>
                <td><span class="badge {{ $payment['status'] === 'completed' ? 'bg-success' : ($payment['status'] === 'failed' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ ucfirst($payment['status']) }}</span></td>
                <td class="text-end">@if($payment['invoice_url'])<a class="btn btn-sm btn-outline-primary" target="_blank" href="{{ $payment['invoice_url'] }}">Invoice</a>@endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-5">No payment history yet.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
    <div class="mt-3">{{ $payments->links() }}</div>
</div>
@endsection
