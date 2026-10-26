<?php

namespace App\Mail;

use App\Support\GeneratedPaymentInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $serviceLabel,
        public GeneratedPaymentInvoice $invoice,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Payment received – '.$this->serviceLabel.' – '.$this->invoice->number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payment-invoice');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->invoice->contents, $this->invoice->filename)
                ->withMime('application/pdf'),
        ];
    }
}
