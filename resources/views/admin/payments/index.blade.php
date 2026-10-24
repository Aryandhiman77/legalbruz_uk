@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-end mb-4"><div><span class="text-uppercase text-muted small">Finance</span><h1 class="h3 mb-0">Payments & invoices</h1></div></div>
    <form class="card card-body border-0 shadow-sm mb-3" method="GET"><div class="row g-2">
        <div class="col-md-3"><select class="form-select" name="status"><option value="">All statuses</option>@foreach(['pending','processing','completed','failed','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
        <div class="col-md-3"><input class="form-control" name="service" value="{{ request('service') }}" placeholder="Service key"></div>
        <div class="col-auto"><button class="btn btn-primary">Filter</button></div>
    </div></form>
    <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Date</th><th>Customer</th><th>Service</th><th>Revolut references</th><th>Amount</th><th>Status</th><th></th></tr></thead>
        <tbody>@forelse($transactions as $transaction)<tr>
            <td>{{ $transaction->created_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</td>
            <td>{{ $transaction->user?->name ?: 'Guest' }}<small class="d-block text-muted">{{ $transaction->user?->email }}</small></td>
            <td>{{ $transaction->service_label }}</td>
            <td><small>Order</small> <code class="d-block">{{ $transaction->provider_order_id }}</code>@if($transaction->provider_payment_id)<small>Payment</small> <code class="d-block">{{ $transaction->provider_payment_id }}</code>@endif</td>
            <td>{{ $transaction->currency }} {{ number_format((float) $transaction->amount, 2) }}</td>
            <td><x-admin-status :status="$transaction->status" /></td>
            <td class="text-end">@if($transaction->status === 'completed')<a class="btn btn-sm btn-outline-primary" target="_blank" href="{{ route('admin.payment.transaction.invoice', $transaction) }}">Invoice</a>@endif</td>
        </tr>@empty<tr><td colspan="7" class="text-center text-muted py-5">No payment transactions found.</td></tr>@endforelse</tbody>
    </table></div></div>
    <div class="mt-3">{{ $transactions->links() }}</div>
</div>
@endsection
