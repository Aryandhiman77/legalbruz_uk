<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminWorkflowNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $title,
        public string $notificationMessage,
        public ?string $actionUrl = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-workflow-notification',
            with: [
                'notificationMessage' => $this->notificationMessage,
                'actionUrl' => $this->actionUrl,
                'actionText' => 'Review Application',
            ],
        );
    }
}
