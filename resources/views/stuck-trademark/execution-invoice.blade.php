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
    <table><tr>
        <td><div class="title">{{ $firmName }}</div><div class="muted">{{ $firmEmail }}</div>@include('invoices.partials.company-registration')</td>
        <td class="text-right"><strong>Invoice No:</strong> {{ $invoiceNumber }}<br><strong>Issued:</strong> {{ $issuedAt->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}<br><strong>Case No:</strong> {{ $case->case_number }}</td>
    </tr></table>

    <table class="details section"><tr>
        <td width="50%"><strong>Billed To</strong><br>{{ $case->applicant_name }}<br>{{ $case->email }}<br>{{ $case->phone }}</td>
        <td width="50%" class="text-right"><strong>Trade Mark Recovery Execution</strong><br>{{ $case->trademark_name }}<br><span class="muted">Application {{ $case->application_number ?: 'not provided' }}</span></td>
    </tr></table>

    @php
        $originalAmount = (float) ($case->execution_original_fee ?: $case->execution_fee);
        $discountAmount = (float) ($case->execution_discount_amount ?: 0);
        $paidAmount = (float) ($case->execution_paid_amount ?: $transaction->amount);
    @endphp
    <div class="section"><strong>Payment Summary</strong><table class="line-items">
        <thead><tr><th>Description</th><th>Reference</th><th>Paid On</th><th class="text-right">Amount</th></tr></thead>
        <tbody><tr><td>Recovery execution package</td><td>{{ $transaction->provider_payment_id ?: $transaction->provider_order_id }}</td><td>{{ $issuedAt->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}</td><td class="text-right">GBP {{ number_format($paidAmount, 2) }}</td></tr></tbody>
    </table></div>
    <div class="section text-right">Original amount: GBP {{ number_format($originalAmount, 2) }}<br>@if($discountAmount > 0)Discount: - GBP {{ number_format($discountAmount, 2) }}<br>@endif<span class="grand-total">Total paid: GBP {{ number_format($paidAmount, 2) }}</span></div>
</body>
</html>
