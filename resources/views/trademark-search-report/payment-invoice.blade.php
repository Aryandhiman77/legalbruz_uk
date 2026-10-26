<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoiceNumber }}</title>
    <style>
        body { margin: 0; padding: 32px; color: #1f2937; font-family: Arial, sans-serif; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; }
        .title { color: #111827; font-size: 24px; font-weight: bold; }
        .muted { color: #6b7280; }
        .text-right { text-align: right; }
        .section { margin-top: 24px; }
        .details { background: #f3f4f6; }
        .details td { padding: 14px 16px; }
        .line-items { margin-top: 12px; }
        .line-items th, .line-items td { padding: 10px 12px; border: 1px solid #d1d5db; text-align: left; }
        .line-items th { background: #f3f4f6; }
        .grand-total { padding-top: 18px; font-size: 16px; font-weight: bold; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td>
                <div class="title">{{ $firmName }}</div>
                <div class="muted">{{ $firmEmail }}</div>
                @include('invoices.partials.company-registration')
            </td>
            <td class="text-right">
                <div><strong>Invoice No:</strong> {{ $invoiceNumber }}</div>
                <div><strong>Issued:</strong> {{ $issuedAt->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}</div>
                <div><strong>Request ID:</strong> #{{ $report->id }}</div>
            </td>
        </tr>
    </table>

    <table class="details section">
        <tr>
            <td width="50%">
                <strong>Billed To</strong><br>
                {{ $report->name }}<br>
                {{ $report->email }}<br>
                {{ $report->phone }}
            </td>
            <td width="50%" class="text-right">
                <strong>Trade Mark Search</strong><br>
                {{ $report->brand_name }}<br>
                <span class="muted">{{ $report->business_activity }}</span>
            </td>
        </tr>
    </table>

    <div class="section">
        <strong>Payment Summary</strong>
        <table class="line-items">
            <thead><tr><th>Description</th><th>Reference</th><th>Paid On</th><th class="text-right">Amount</th></tr></thead>
            <tbody><tr>
                <td>UK trade mark search report</td>
                <td>{{ $transaction->provider_payment_id ?: $report->transaction_id ?: $transaction->provider_order_id ?: $report->payment_reference }}</td>
                <td>{{ $issuedAt->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}</td>
                <td class="text-right">{{ strtoupper($report->currency ?: $transaction->currency ?: 'GBP') }} {{ number_format((float) $report->amount, 2) }}</td>
            </tr></tbody>
        </table>
    </div>

    <div class="grand-total text-right">Total paid: {{ strtoupper($report->currency ?: $transaction->currency ?: 'GBP') }} {{ number_format((float) $report->amount, 2) }}</div>
</body>
</html>
