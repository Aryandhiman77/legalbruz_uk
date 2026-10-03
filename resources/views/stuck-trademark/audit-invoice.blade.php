@php
    $auditOriginalAmount = (float) ($case->audit_original_fee ?: $case->audit_fee);
    $auditDiscountAmount = (float) ($case->audit_discount_amount ?: 0);
    $auditPaidAmount = (float) ($case->audit_paid_amount ?: max($auditOriginalAmount - $auditDiscountAmount, 0));
    $hasAuditDiscount = $auditDiscountAmount > 0 && $auditPaidAmount < $auditOriginalAmount;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoiceNumber }}</title>
    <style>
        body {
            margin: 0;
            padding: 32px;
            color: #1f2937;
            font-family: Arial, sans-serif;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .title {
            color: #111827;
            font-size: 24px;
            font-weight: bold;
        }

        .muted {
            color: #6b7280;
        }

        .text-right {
            text-align: right;
        }

        .section {
            margin-top: 24px;
        }

        .line-items th,
        .line-items td {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            text-align: left;
        }

        .line-items th {
            background: #f3f4f6;
        }

        .grand-total {
            font-size: 16px;
            font-weight: bold;
        }

        .discount-row {
            color: #047857;
        }

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
                {{ $case->applicant_name }}<br>
                {{ $case->email }}<br>
                {{ $case->phone }}
            </td>
            <td width="50%" class="text-right">
                <strong>Trademark Recovery Case</strong><br>
                {{ $case->trademark_name }}<br>
                <span class="muted">Application {{ $case->application_number ?: 'not provided' }}</span>
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
                    <td>Stuck / delayed trademark recovery audit package</td>
                    <td>{{ $case->audit_transaction_id ?: ($case->audit_payment_reference ?: 'N/A') }}</td>
                    <td>{{ $case->audit_paid_at?->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}</td>
                    <td class="text-right">INR {{ number_format($auditPaidAmount, 2) }}</td>
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
                        <td>Audit package amount</td>
                        <td class="text-right">INR {{ number_format($auditOriginalAmount, 2) }}</td>
                    </tr>
                    @if ($hasAuditDiscount)
                        <tr class="discount-row">
                            <td>Discount{{ $case->audit_coupon_label ? ' (' . $case->audit_coupon_label . ')' : '' }}</td>
                            <td class="text-right">- INR {{ number_format($auditDiscountAmount, 2) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="grand-total">Invoice total</td>
                        <td class="text-right grand-total">INR {{ number_format($auditPaidAmount, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="section muted">
        This invoice reflects the audit package payment completed for the stuck / delayed trademark recovery case.<br>
        <strong>Government fees are not included.</strong> Any applicable Government or Registry fee is payable separately.
    </div>
</body>
</html>
