<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ReminderMail extends ClientRequestMail
{
    public function __construct(string $title, string $requestUrl, string $businessName, string $contactEmail, ?string $contactName, public array $missing, public int $completed, public int $total, public string $stopUrl)
    {
        parent::__construct($title, $requestUrl, $businessName, $contactEmail, $contactName);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'A few things are still needed', from: new Address(config('mail.from.address'), $this->businessName.' via AskOnce'), replyTo: [new Address($this->contactEmail)]);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.reminder');
    }
}
