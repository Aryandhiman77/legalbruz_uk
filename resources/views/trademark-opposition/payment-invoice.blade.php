@php
    $originalAmount = (float) ($case->original_package_price ?: $case->package_price);
    $discountAmount = (float) ($case->discount_amount ?: 0);
    $paidAmount = (float) ($case->paid_amount ?: max($originalAmount - $discountAmount, 0));
    $isOppositionFiling = $case->flow_type === \App\Support\TrademarkOppositionWorkflow::FLOW_OPPOSE;
    $serviceTitle = $isOppositionFiling ? 'Trademark Opposition Filing' : 'Trademark Opposition Defence';
    $defaultPackageName = $isOppositionFiling ? 'Notice of Opposition Filing Package' : 'Opposition Defence Package';
    $invoiceTrademark = $isOppositionFiling ? $case->trademark_to_oppose : $case->trademark_name;
    $invoiceApplicationNumber = $isOppositionFiling ? $case->opposed_application_number : $case->application_number;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoiceNumber }}</title>
    <style>
        body { margin: 0; padding: 32px; color: #1f2937; font-family: Arial, sans-serif; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; }
        .title { color: #111827; font-size: 24px; font-weight: bold; }
        .muted { color: #6b7280; }
        .text-right { text-align: right; }
        .section { margin-top: 24px; }
        .line-items th, .line-items td { padding: 10px 12px; border: 1px solid #d1d5db; text-align: left; }
        .line-items th { background: #f3f4f6; }
        .grand-total { font-size: 16px; font-weight: bold; }
        .discount-row { color: #047857; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td>
                <div class="title">{{ $firmName }}</div>
                <div class="muted">{{ $firmEmail }}</div>
            </td>
            <td class="text-right">
                <div><strong>Invoice No:</strong> {{ $invoiceNumber }}</div>
                <div><strong>Issued:</strong> {{ $issuedAt?->format('d M Y') }}</div>
                <div><strong>Case No:</strong> {{ $case->case_number }}</div>
            </td>
        </tr>
    </table>

    <table class="section">
        <tr>
            <td width="50%">
                <strong>Billed To</strong><br>
                {{ $case->applicant_name ?: $user?->name }}<br>
                {{ $case->email ?: $user?->email }}<br>
                {{ $case->mobile_number ?: $user?->phone }}
            </td>
            <td width="50%" class="text-right">
                <strong>{{ $serviceTitle }}</strong><br>
                {{ $invoiceTrademark }}<br>
                <span class="muted">Application {{ $invoiceApplicationNumber ?: 'not provided' }} · Class {{ $case->trademark_class ?: 'N/A' }}</span>
            </td>
        </tr>
    </table>

    <div class="section">
        <strong>Payment Summary</strong>
        <table class="line-items" style="margin-top: 12px;">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Reference</th>
                    <th>Paid On</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $case->package_name ?: $defaultPackageName }}</td>
                    <td>{{ $case->transaction_id ?: ($case->payment_reference ?: 'N/A') }}</td>
                    <td>{{ $case->paid_at?->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}</td>
                    <td class="text-right">INR {{ number_format($paidAmount, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <table class="section">
        <tr>
            <td width="70%"></td>
            <td width="30%">
                <table>
                    <tr>
                        <td>Original amount</td>
                        <td class="text-right">INR {{ number_format($originalAmount, 2) }}</td>
                    </tr>
                    <tr class="discount-row">
                        <td>Discount{{ $case->coupon_label ? ' (' . $case->coupon_label . ')' : '' }}</td>
                        <td class="text-right">- INR {{ number_format($discountAmount, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="grand-total">Amount paid</td>
                        <td class="text-right grand-total">INR {{ number_format($paidAmount, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="section muted">
        This invoice reflects the {{ strtolower($serviceTitle) }} package payment completed for the trademark opposition case.<br>
        <strong>Government fees are not included.</strong> Any applicable Government or Registry fee is payable separately.
    </div>
</body>
</html>
