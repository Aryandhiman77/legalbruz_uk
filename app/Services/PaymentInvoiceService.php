<?php

namespace App\Services;

use App\Models\ConsultationBooking;
use App\Models\ExaminationReportReplyCase;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\StuckTrademarkCase;
use App\Models\TrademarkOppositionCase;
use App\Models\TrademarkSearchReportRequest;
use App\Support\GeneratedPaymentInvoice;
use App\Support\TrademarkOppositionWorkflow;
use Illuminate\Support\Carbon;

class PaymentInvoiceService
{
    public function forTransaction(PaymentTransaction $transaction): GeneratedPaymentInvoice
    {
        $transaction->loadMissing(['user', 'payable']);
        $payable = $transaction->payable;

        return match (true) {
            $payable instanceof Payment => $this->forApplicationPayment($payable, $transaction),
            $payable instanceof ConsultationBooking => $this->forConsultation($transaction, $payable),
            $payable instanceof TrademarkSearchReportRequest => $this->forSearchReport($transaction, $payable),
            $payable instanceof TrademarkOppositionCase => $this->forOpposition($transaction, $payable),
            $payable instanceof StuckTrademarkCase => $this->forRecovery($transaction, $payable),
            $payable instanceof ExaminationReportReplyCase => $this->forExaminationReply($transaction, $payable),
            default => $this->forGenericTransaction($transaction),
        };
    }

    public function forApplicationPayment(Payment $payment, ?PaymentTransaction $transaction = null): GeneratedPaymentInvoice
    {
        $payment->loadMissing(['application', 'user']);
        $payment = clone $payment;
        if ($transaction) {
            $payment->forceFill([
                'status' => 'completed',
                'transaction_id' => $transaction->provider_payment_id ?: $payment->transaction_id,
                'reference_number' => $transaction->provider_order_id ?: $payment->reference_number,
                'paid_at' => $transaction->paid_at ?: $payment->paid_at,
            ]);
        }
        $issuedAt = $transaction?->paid_at ?? $payment->paid_at ?? $payment->created_at ?? now();
        $number = 'INV-'.$issuedAt->format('Y').'-'.$payment->application_id.'-'.$payment->id;

        return $this->render('emails.attachments.invoice', [
            'application' => $payment->application,
            'user' => $payment->user,
            'payment' => $payment,
            'invoiceNumber' => $number,
            'paymentLabel' => $this->applicationPaymentLabel($payment),
            'issuedAt' => $issuedAt,
            ...$this->firmDetails(),
        ], 'invoice-'.$payment->application_id.'-'.$payment->id.'.pdf', $number);
    }

    private function forConsultation(PaymentTransaction $transaction, ConsultationBooking $booking): GeneratedPaymentInvoice
    {
        $issuedAt = $this->issuedAt($transaction, $booking->paid_at);
        $number = 'CON-INV-'.$issuedAt->format('Y').'-'.$booking->id;

        return $this->render('consultations.payment-invoice', [
            'booking' => $booking,
            'transaction' => $transaction,
            'invoiceNumber' => $number,
            'issuedAt' => $issuedAt,
            ...$this->firmDetails(),
        ], 'consultation-invoice-'.$booking->id.'.pdf', $number);
    }

    private function forSearchReport(PaymentTransaction $transaction, TrademarkSearchReportRequest $report): GeneratedPaymentInvoice
    {
        $issuedAt = $this->issuedAt($transaction, $report->paid_at);
        $number = 'TSR-INV-'.$issuedAt->format('Y').'-'.$report->id;

        return $this->render('trademark-search-report.payment-invoice', [
            'report' => $report,
            'transaction' => $transaction,
            'invoiceNumber' => $number,
            'issuedAt' => $issuedAt,
            ...$this->firmDetails(),
        ], 'trade-mark-search-invoice-'.$report->id.'.pdf', $number);
    }

    private function forOpposition(PaymentTransaction $transaction, TrademarkOppositionCase $case): GeneratedPaymentInvoice
    {
        $case->loadMissing('user');
        $case = clone $case;
        $case->forceFill([
            'payment_reference' => $transaction->provider_order_id ?: $case->payment_reference,
            'transaction_id' => $transaction->provider_payment_id ?: $case->transaction_id,
            'paid_at' => $transaction->paid_at ?: $case->paid_at,
            'original_package_price' => data_get($transaction->metadata, 'original_amount', $case->original_package_price),
            'paid_amount' => data_get($transaction->metadata, 'payable_amount', $transaction->amount),
            'discount_amount' => data_get($transaction->metadata, 'discount_amount', $case->discount_amount),
            'coupon_label' => data_get($transaction->metadata, 'coupon_label', $case->coupon_label),
        ]);
        $issuedAt = $this->issuedAt($transaction, $case->paid_at);
        $isFiling = $case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE;
        $number = ($isFiling ? 'OPF-INV-' : 'OPD-INV-').$issuedAt->format('Y').'-'.$case->id;
        $filename = ($isFiling ? 'opposition-filing-invoice-' : 'opposition-defence-invoice-').$case->case_number.'.pdf';

        return $this->render('trademark-opposition.payment-invoice', [
            'case' => $case,
            'user' => $case->user,
            'invoiceNumber' => $number,
            'issuedAt' => $issuedAt,
            ...$this->firmDetails(),
        ], $filename, $number);
    }

    private function forRecovery(PaymentTransaction $transaction, StuckTrademarkCase $case): GeneratedPaymentInvoice
    {
        $case->loadMissing('user');
        $case = clone $case;
        $isAudit = $transaction->service === 'stuck_trademark_audit';
        if ($isAudit) {
            $case->forceFill([
                'audit_payment_reference' => $transaction->provider_order_id ?: $case->audit_payment_reference,
                'audit_transaction_id' => $transaction->provider_payment_id ?: $case->audit_transaction_id,
                'audit_paid_at' => $transaction->paid_at ?: $case->audit_paid_at,
                'audit_original_fee' => data_get($transaction->metadata, 'original_amount', $case->audit_original_fee),
                'audit_paid_amount' => data_get($transaction->metadata, 'payable_amount', $transaction->amount),
                'audit_discount_amount' => data_get($transaction->metadata, 'discount_amount', $case->audit_discount_amount),
                'audit_coupon_label' => data_get($transaction->metadata, 'coupon_label', $case->audit_coupon_label),
            ]);
        } else {
            $case->forceFill([
                'execution_payment_reference' => $transaction->provider_order_id ?: $case->execution_payment_reference,
                'execution_transaction_id' => $transaction->provider_payment_id ?: $case->execution_transaction_id,
                'execution_paid_at' => $transaction->paid_at ?: $case->execution_paid_at,
                'execution_original_fee' => data_get($transaction->metadata, 'original_amount', $case->execution_original_fee),
                'execution_paid_amount' => data_get($transaction->metadata, 'payable_amount', $transaction->amount),
                'execution_discount_amount' => data_get($transaction->metadata, 'discount_amount', $case->execution_discount_amount),
                'execution_coupon_label' => data_get($transaction->metadata, 'coupon_label', $case->execution_coupon_label),
            ]);
        }
        $issuedAt = $this->issuedAt($transaction, $isAudit ? $case->audit_paid_at : $case->execution_paid_at);
        $number = ($isAudit ? 'STR-INV-' : 'STR-EXE-INV-').$issuedAt->format('Y').'-'.$case->id;

        return $this->render(
            $isAudit ? 'stuck-trademark.audit-invoice' : 'stuck-trademark.execution-invoice',
            [
                'case' => $case,
                'user' => $case->user,
                'transaction' => $transaction,
                'invoiceNumber' => $number,
                'issuedAt' => $issuedAt,
                ...$this->firmDetails(),
            ],
            ($isAudit ? 'audit-invoice-' : 'execution-invoice-').$case->case_number.'.pdf',
            $number,
        );
    }

    private function forExaminationReply(PaymentTransaction $transaction, ExaminationReportReplyCase $case): GeneratedPaymentInvoice
    {
        $case->loadMissing('user');
        $case = clone $case;
        $case->forceFill([
            'payment_reference' => $transaction->provider_order_id ?: $case->payment_reference,
            'transaction_id' => $transaction->provider_payment_id ?: $case->transaction_id,
            'paid_at' => $transaction->paid_at ?: $case->paid_at,
            'original_package_price' => data_get($transaction->metadata, 'original_amount', $case->original_package_price),
            'paid_amount' => data_get($transaction->metadata, 'payable_amount', $transaction->amount),
            'discount_amount' => data_get($transaction->metadata, 'discount_amount', $case->discount_amount),
            'coupon_label' => data_get($transaction->metadata, 'coupon_label', $case->coupon_label),
        ]);
        $issuedAt = $this->issuedAt($transaction, $case->paid_at);
        $number = 'ERR-INV-'.$issuedAt->format('Y').'-'.$case->id;

        return $this->render('examination-reply.payment-invoice', [
            'case' => $case,
            'user' => $case->user,
            'invoiceNumber' => $number,
            'issuedAt' => $issuedAt,
            ...$this->firmDetails(),
        ], 'objection-reply-invoice-'.$case->case_number.'.pdf', $number);
    }

    private function forGenericTransaction(PaymentTransaction $transaction): GeneratedPaymentInvoice
    {
        $issuedAt = $this->issuedAt($transaction);
        $number = 'PAY-INV-'.$issuedAt->format('Y').'-'.$transaction->id;

        return $this->render('payments.transaction-invoice', [
            'transaction' => $transaction,
            'invoiceNumber' => $number,
            'issuedAt' => $issuedAt,
            ...$this->firmDetails(),
        ], 'payment-invoice-'.$transaction->id.'.pdf', $number);
    }

    private function render(string $view, array $data, string $filename, string $number): GeneratedPaymentInvoice
    {
        $contents = \PDF::loadView($view, $data)->setPaper('a4')->output();

        return new GeneratedPaymentInvoice($number, $filename, $contents);
    }

    private function issuedAt(PaymentTransaction $transaction, mixed $fallback = null): Carbon
    {
        return $transaction->paid_at ?? $fallback ?? $transaction->created_at ?? now();
    }

    private function firmDetails(): array
    {
        return [
            'firmName' => config('app.name', 'Legal Bruz'),
            'firmEmail' => config('mail.from.address'),
        ];
    }

    private function applicationPaymentLabel(Payment $payment): string
    {
        $paymentType = strtolower((string) ($payment->payment_type ?? ''));

        if (! in_array($paymentType, ['advance', 'final', 'full'], true)) {
            $paymentType = (float) $payment->total_amount > 0 && (float) $payment->amount >= (float) $payment->total_amount
                ? 'full'
                : ((string) $payment->percentage === '100%' ? 'final' : 'advance');
        }

        return match ($paymentType) {
            'full' => 'Full Payment',
            'final' => 'Final Payment',
            default => 'Advance Payment (50%)',
        };
    }
}
