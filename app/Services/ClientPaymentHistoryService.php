<?php

namespace App\Services;

use App\Models\ConsultationBooking;
use App\Models\ExaminationReportReplyCase;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\StuckTrademarkCase;
use App\Models\TrademarkOppositionCase;
use App\Models\TrademarkSearchReportRequest;
use App\Models\User;
use App\Support\TrademarkOppositionWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ClientPaymentHistoryService
{
    public function paginate(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $transactions = PaymentTransaction::query()
            ->where('user_id', $user->id)
            ->with('payable')
            ->latest('id')
            ->get();
        $usedTransactionIds = [];
        $entries = collect();

        $user->payments()
            ->whereIn('status', ['completed', 'approved'])
            ->with('application')
            ->get()
            ->each(function (Payment $payment) use ($transactions, &$usedTransactionIds, $entries): void {
                $transaction = $this->matchingTransaction($transactions, $payment);
                $this->markUsed($transaction, $usedTransactionIds);
                $entries->push($this->entry(
                    'payment:'.$payment->id,
                    $payment->paid_at ?? $payment->approved_at ?? $payment->created_at,
                    $this->applicationPaymentLabel($payment),
                    $transaction?->provider_payment_id ?: $payment->transaction_id ?: $transaction?->provider_order_id ?: $payment->reference_number,
                    (float) $payment->amount,
                    'GBP',
                    'completed',
                    route('payment.invoice', $payment),
                ));
            });

        ConsultationBooking::query()
            ->visibleTo($user)
            ->where('payment_status', 'paid')
            ->get()
            ->each(function (ConsultationBooking $booking) use ($transactions, &$usedTransactionIds, $entries): void {
                $transaction = $this->matchingTransaction($transactions, $booking, 'consultation');
                $this->markUsed($transaction, $usedTransactionIds);
                $entries->push($this->entry(
                    'consultation:'.$booking->id,
                    $booking->paid_at ?? $booking->updated_at,
                    'Consultation call',
                    $transaction?->provider_payment_id ?: $booking->transaction_id ?: $booking->payment_reference,
                    (float) $booking->amount,
                    $booking->currency ?: 'GBP',
                    'completed',
                    $this->transactionInvoiceUrl($transaction),
                ));
            });

        TrademarkSearchReportRequest::query()
            ->visibleTo($user)
            ->where('payment_status', 'paid')
            ->get()
            ->each(function (TrademarkSearchReportRequest $report) use ($transactions, &$usedTransactionIds, $entries): void {
                $transaction = $this->matchingTransaction($transactions, $report, 'trademark_search_report');
                $this->markUsed($transaction, $usedTransactionIds);
                $entries->push($this->entry(
                    'search-report:'.$report->id,
                    $report->paid_at ?? $report->updated_at,
                    'Trade mark search report',
                    $transaction?->provider_payment_id ?: $report->transaction_id ?: $report->payment_reference,
                    (float) $report->amount,
                    $report->currency ?: 'GBP',
                    'completed',
                    $this->transactionInvoiceUrl($transaction),
                ));
            });

        $user->trademarkOppositionCases()
            ->where('payment_status', 'paid')
            ->get()
            ->each(function (TrademarkOppositionCase $case) use ($transactions, &$usedTransactionIds, $entries): void {
                $isFiling = $case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE;
                $transaction = $this->matchingTransaction($transactions, $case, $isFiling ? 'opposition_filing' : 'opposition_defence');
                $this->markUsed($transaction, $usedTransactionIds);
                $entries->push($this->entry(
                    'opposition:'.$case->id,
                    $case->paid_at ?? $case->updated_at,
                    $isFiling ? 'Opposition filing' : 'Opposition defence',
                    $transaction?->provider_payment_id ?: $case->transaction_id ?: $case->payment_reference,
                    (float) ($case->paid_amount ?: $case->package_price ?: $case->total_amount),
                    'GBP',
                    'completed',
                    route($isFiling ? 'trademark-opposition.oppose.payment.invoice' : 'trademark-opposition.payment.invoice', $case),
                ));
            });

        $user->stuckTrademarkCases()->get()->each(function (StuckTrademarkCase $case) use ($transactions, &$usedTransactionIds, $entries): void {
            if ($case->audit_payment_status === 'paid') {
                $transaction = $this->matchingTransaction($transactions, $case, 'stuck_trademark_audit');
                $this->markUsed($transaction, $usedTransactionIds);
                $entries->push($this->entry(
                    'stuck-audit:'.$case->id,
                    $case->audit_paid_at ?? $case->updated_at,
                    'Stuck trade mark audit',
                    $transaction?->provider_payment_id ?: $case->audit_transaction_id ?: $case->audit_payment_reference,
                    (float) ($case->audit_paid_amount ?: $case->audit_fee),
                    'GBP',
                    'completed',
                    route('stuck-trademark.audit-payment.invoice', $case),
                ));
            }

            if ($case->execution_payment_status === 'paid') {
                $transaction = $this->matchingTransaction($transactions, $case, 'stuck_trademark_execution');
                $this->markUsed($transaction, $usedTransactionIds);
                $entries->push($this->entry(
                    'stuck-execution:'.$case->id,
                    $case->execution_paid_at ?? $case->updated_at,
                    'Stuck trade mark execution',
                    $transaction?->provider_payment_id ?: $transaction?->provider_order_id,
                    (float) ($case->execution_paid_amount ?: $case->execution_fee),
                    'GBP',
                    'completed',
                    $this->transactionInvoiceUrl($transaction),
                ));
            }
        });

        $user->examinationReportReplyCases()
            ->where('payment_status', 'paid')
            ->get()
            ->each(function (ExaminationReportReplyCase $case) use ($transactions, &$usedTransactionIds, $entries): void {
                $transaction = $this->matchingTransaction($transactions, $case, 'examination_reply');
                $this->markUsed($transaction, $usedTransactionIds);
                $entries->push($this->entry(
                    'examination-reply:'.$case->id,
                    $case->paid_at ?? $case->updated_at,
                    'Trade mark objection reply',
                    $transaction?->provider_payment_id ?: $case->transaction_id ?: $case->payment_reference,
                    (float) ($case->paid_amount ?: $case->package_price),
                    'GBP',
                    'completed',
                    route('examination-reply.payment.invoice', $case),
                ));
            });

        $transactions
            ->reject(fn (PaymentTransaction $transaction) => in_array($transaction->id, $usedTransactionIds, true))
            ->each(function (PaymentTransaction $transaction) use ($entries): void {
                $entries->push($this->entry(
                    'transaction:'.$transaction->id,
                    $transaction->paid_at ?? $transaction->created_at,
                    $transaction->service_label,
                    $transaction->provider_order_id,
                    (float) $transaction->amount,
                    $transaction->currency,
                    $transaction->status,
                    $this->transactionInvoiceUrl($transaction),
                ));
            });

        $entries = $entries
            ->filter(fn (array $entry) => $entry['occurred_at'] !== null)
            ->sortByDesc(fn (array $entry) => $entry['occurred_at']->getTimestamp())
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $entries->forPage($page, $perPage)->values(),
            $entries->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ],
        );
    }

    private function matchingTransaction(Collection $transactions, Model $payable, ?string $service = null): ?PaymentTransaction
    {
        return $transactions->first(function (PaymentTransaction $transaction) use ($payable, $service): bool {
            return $transaction->payable_type === $payable::class
                && (string) $transaction->payable_id === (string) $payable->getKey()
                && ($service === null || $transaction->service === $service);
        });
    }

    private function markUsed(?PaymentTransaction $transaction, array &$usedTransactionIds): void
    {
        if ($transaction) {
            $usedTransactionIds[] = $transaction->id;
        }
    }

    private function transactionInvoiceUrl(?PaymentTransaction $transaction): ?string
    {
        return $transaction?->status === 'completed'
            ? route('payment.transaction.invoice', $transaction)
            : null;
    }

    private function applicationPaymentLabel(Payment $payment): string
    {
        $type = strtolower((string) ($payment->payment_type ?? ''));

        if ($type === '') {
            $type = (float) $payment->total_amount > 0 && (float) $payment->amount >= (float) $payment->total_amount
                ? 'full'
                : ((string) $payment->percentage === '100%' ? 'final' : 'advance');
        }

        return match ($type) {
            'final' => 'Trade mark application — final balance',
            'full' => 'Trade mark application — full payment',
            default => 'Trade mark application — advance',
        };
    }

    private function entry(
        string $key,
        mixed $occurredAt,
        string $service,
        mixed $reference,
        float $amount,
        string $currency,
        string $status,
        ?string $invoiceUrl,
    ): array {
        return [
            'key' => $key,
            'occurred_at' => $occurredAt,
            'service' => $service,
            'reference' => filled($reference) ? (string) $reference : '—',
            'amount' => $amount,
            'currency' => strtoupper($currency ?: 'GBP'),
            'status' => $status,
            'invoice_url' => $invoiceUrl,
        ];
    }
}
