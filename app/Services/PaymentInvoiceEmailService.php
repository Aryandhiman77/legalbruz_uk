<?php

namespace App\Services;

use App\Mail\PaymentInvoiceMail;
use App\Models\Admin;
use App\Models\ConsultationBooking;
use App\Models\ExaminationReportReplyCase;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\StuckTrademarkCase;
use App\Models\TrademarkOppositionCase;
use App\Models\TrademarkSearchReportRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentInvoiceEmailService
{
    public function __construct(private readonly PaymentInvoiceService $invoices) {}

    public function sendToClientAndAdmins(PaymentTransaction $transaction): void
    {
        try {
            $this->deliver($transaction);
        } catch (\Throwable $exception) {
            Log::warning('Payment invoice delivery could not be completed.', [
                'payment_transaction_id' => $transaction->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function deliver(PaymentTransaction $transaction): void
    {
        $transaction->refresh()->loadMissing(['user', 'payable']);

        if ($transaction->status !== 'completed') {
            return;
        }

        $alreadySent = collect(data_get($transaction->metadata, 'invoice_email_recipients', []))
            ->map(fn ($email) => strtolower((string) $email));
        $recipients = collect([$this->clientRecipient($transaction)])
            ->merge(Admin::query()->get(['name', 'email'])->map(fn (Admin $admin) => [
                'name' => $admin->name ?: 'Administrator',
                'email' => $admin->email,
            ]))
            ->filter(fn ($recipient) => filled($recipient['email'] ?? null))
            ->unique(fn ($recipient) => strtolower((string) $recipient['email']))
            ->reject(fn ($recipient) => $alreadySent->contains(strtolower((string) $recipient['email'])));

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            $invoice = $this->invoices->forTransaction($transaction);
        } catch (\Throwable $exception) {
            Log::warning('Payment invoice PDF could not be prepared.', [
                'payment_transaction_id' => $transaction->id,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient['email'])->send(new PaymentInvoiceMail(
                    $recipient['name'] ?: 'Customer',
                    $transaction->service_label,
                    $invoice,
                ));
                $alreadySent->push(strtolower((string) $recipient['email']));
            } catch (\Throwable $exception) {
                Log::warning('Payment invoice email could not be sent.', [
                    'payment_transaction_id' => $transaction->id,
                    'recipient' => $recipient['email'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $metadata = $transaction->metadata ?? [];
        $metadata['invoice_email_recipients'] = $alreadySent->unique()->values()->all();
        $metadata['invoice_email_last_attempted_at'] = now()->toIso8601String();
        $transaction->update(['metadata' => $metadata]);
    }

    private function clientRecipient(PaymentTransaction $transaction): ?array
    {
        $payable = $transaction->payable;

        return match (true) {
            $payable instanceof Payment => $this->recipient($payable->user?->name, $payable->user?->email),
            $payable instanceof ConsultationBooking => $this->recipient($payable->name, $payable->email),
            $payable instanceof TrademarkSearchReportRequest => $this->recipient($payable->name, $payable->email),
            $payable instanceof TrademarkOppositionCase => $this->recipient($payable->applicant_name ?: $payable->user?->name, $payable->email ?: $payable->user?->email),
            $payable instanceof StuckTrademarkCase => $this->recipient($payable->applicant_name ?: $payable->user?->name, $payable->email ?: $payable->user?->email),
            $payable instanceof ExaminationReportReplyCase => $this->recipient($payable->applicant_name ?: $payable->user?->name, $payable->user?->email),
            default => $this->recipient($transaction->user?->name, $transaction->user?->email),
        };
    }

    private function recipient(?string $name, ?string $email): ?array
    {
        return filled($email) ? ['name' => $name ?: 'Customer', 'email' => $email] : null;
    }
}
