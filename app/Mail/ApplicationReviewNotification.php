<?php

namespace App\Mail;

use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationReviewNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Application $application,
        public string $decision,
        public ?string $note = null
    ) {
    }

    public function envelope(): Envelope
    {
        $applicationName = $this->application->brand_name ?? 'Trademark Application';
        $subject = match ($this->decision) {
            'approved' => '✅ Application Approved - ' . $applicationName,
            'changes_requested' => 'Application Changes Requested - ' . $applicationName,
            default => 'Application Review Update - ' . $applicationName,
        };

        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.application-review-status',
        );
    }

    public function attachments(): array
    {
        if ($this->decision !== 'approved') {
            return [];
        }

        $documentTypes = [
            'engagement_letter',
            'other_document',
        ];

        $attachments = $this->application->documents()
            ->whereIn('document_type', $documentTypes)
            ->get()
            ->filter(fn ($document) => filled($document->file_path))
            ->map(function ($document) {
                $attachment = Attachment::fromStorageDisk('public', $document->file_path)
                    ->as($document->file_name);

                if ($document->file_type === 'html') {
                    $attachment = $attachment->withMime('text/html');
                }

                return $attachment;
            })
            ->values()
            ->all();

        if ($invoiceAttachment = $this->buildInvoiceAttachment()) {
            $attachments[] = $invoiceAttachment;
        }

        return $attachments;
    }

    private function buildInvoiceAttachment(): ?Attachment
    {
        $payment = $this->initialPayment();

        if (!$payment) {
            return null;
        }

        $pdfBytes = \PDF::loadView('emails.attachments.invoice', [
            'application' => $this->application,
            'user' => $this->user,
            'payment' => $payment,
            'invoiceNumber' => $this->invoiceNumber($payment),
            'paymentLabel' => $this->paymentLabel($payment),
            'issuedAt' => $payment->paid_at ?? $payment->created_at,
            'firmName' => config('app.name', 'Legal Bruz'),
            'firmEmail' => config('mail.from.address'),
        ])->setPaper('a4')->output();

        return Attachment::fromData(
            fn () => $pdfBytes,
            'invoice-' . $this->application->id . '-' . $payment->id . '.pdf'
        )->withMime('application/pdf');
    }

    private function initialPayment(): ?Payment
    {
        return $this->application->payments()
            ->where('status', 'completed')
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get()
            ->first(function (Payment $payment) {
                return $this->paymentKind($payment) !== 'final';
            });
    }

    private function paymentKind(Payment $payment): string
    {
        $paymentType = strtolower((string) ($payment->payment_type ?? ''));

        if ($paymentType === 'final') {
            return 'final';
        }

        if ($paymentType === 'full') {
            return 'full';
        }

        if ((float) $payment->amount >= (float) $payment->total_amount && (float) $payment->total_amount > 0) {
            return 'full';
        }

        return 'advance';
    }

    private function paymentLabel(Payment $payment): string
    {
        return match ($this->paymentKind($payment)) {
            'full' => 'Full Payment',
            'final' => 'Final Payment',
            default => 'Advance Payment (50%)',
        };
    }

    private function invoiceNumber(Payment $payment): string
    {
        return 'INV-' . now()->format('Y') . '-' . $this->application->id . '-' . $payment->id;
    }
}
