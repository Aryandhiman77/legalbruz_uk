<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoiceNumber }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #1f2937;
            font-size: 13px;
            margin: 0;
            padding: 32px;
        }

        .header,
        .summary,
        .totals,
        .footer {
            width: 100%;
        }

        .header td,
        .summary td,
        .totals td {
            vertical-align: top;
            padding: 4px 0;
        }

        .title {
            font-size: 24px;
            font-weight: bold;
            color: #111827;
        }

        .muted {
            color: #6b7280;
        }

        .section {
            margin-top: 24px;
        }

        .line-items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .line-items th,
        .line-items td {
            border: 1px solid #d1d5db;
            padding: 10px 12px;
            text-align: left;
        }

        .line-items th {
            background: #f3f4f6;
        }

        .text-right {
            text-align: right;
        }

        .grand-total {
            font-size: 16px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <div class="title">{{ $firmName }}</div>
                <div class="muted">{{ $firmEmail }}</div>
            </td>
            <td class="text-right">
                <div><strong>Invoice No:</strong> {{ $invoiceNumber }}</div>
                <div><strong>Issued:</strong> {{ $issuedAt?->format('d M Y') }}</div>
                <div><strong>Application ID:</strong> #{{ $application->id }}</div>
            </td>
        </tr>
    </table>

    <table class="summary section">
        <tr>
            <td width="50%">
                <strong>Billed To</strong><br>
                {{ $user->name }}<br>
                {{ $user->email }}<br>
                {{ $application->applicant_name }}
            </td>
            <td width="50%" class="text-right">
                <strong>Brand</strong><br>
                {{ $application->brand_name ?? 'Trademark Application' }}<br>
                <span class="muted">{{ $application->entity_type === 'individual' ? 'Individual / Proprietor / Trader' : ucfirst($application->entity_type) }} applicant</span>
            </td>
        </tr>
    </table>

    <div class="section">
        <strong>Payment Summary</strong>
        <table class="line-items">
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
                    <td>{{ $paymentLabel }} for UK trade mark application services</td>
                    <td>{{ $payment->transaction_id ?: ($payment->reference_number ?: 'N/A') }}</td>
                    <td>{{ ($payment->paid_at ?? $payment->created_at)?->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}</td>
                    <td class="text-right">GBP {{ number_format((float) $payment->amount, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <table class="totals section">
        <tr>
            <td width="70%"></td>
            <td width="30%">
                @php
                    $invoiceDiscountAmount = $payment->invoiceDiscountAmount();
                    $invoiceDiscountLabel = $payment->invoiceDiscountLabel();
                    $isAdvanceInvoice = $payment->isAdvanceInvoice();
                    $advanceBaseAmount = $payment->invoiceAdvanceBaseAmount();
                @endphp
                <table style="width: 100%;">
                    <tr>
                        <td>Total service amount</td>
                        <td class="text-right">GBP {{ number_format((float) $payment->total_amount, 2) }}</td>
                    </tr>
                    @if ($isAdvanceInvoice)
                        <tr>
                            <td>50% advance payment</td>
                            <td class="text-right">GBP {{ number_format($advanceBaseAmount, 2) }}</td>
                        </tr>
                    @endif
                    @if ($invoiceDiscountAmount > 0)
                        <tr>
                            <td>Coupon discount{{ $invoiceDiscountLabel ? ' (' . $invoiceDiscountLabel . ')' : '' }}</td>
                            <td class="text-right">- GBP {{ number_format($invoiceDiscountAmount, 2) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td>Amount paid now</td>
                        <td class="text-right">GBP {{ number_format((float) $payment->amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="grand-total">Invoice total</td>
                        <td class="text-right grand-total">GBP {{ number_format((float) $payment->amount, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer section muted">
        This invoice reflects the payment selected and completed for this application at the time of approval email issuance.<br>
        <strong>UKIPO official fees are not included.</strong> Any applicable official fee is payable separately.
    </div>
</body>
</html>
