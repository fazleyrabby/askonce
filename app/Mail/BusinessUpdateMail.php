<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $title, public string $updateMessage, public string $requestUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'AskOnce · '.$this->title);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.business-update');
    }
}
