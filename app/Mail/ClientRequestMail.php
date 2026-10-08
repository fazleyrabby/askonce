<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $title, public string $requestUrl, public string $businessName, public string $contactEmail, public ?string $contactName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->businessName.' needs a few things from you', from: new Address(config('mail.from.address'), $this->businessName.' via AskOnce'), replyTo: [new Address($this->contactEmail)]);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.request');
    }
}
