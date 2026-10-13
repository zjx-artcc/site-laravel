<?php

namespace App\Mail;

use App\Models\Loa;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoaDenied extends Mailable
{
    use Queueable, SerializesModels;

    public Loa $loa;

    public function __construct(Loa $loa)
    {
        $this->loa = $loa;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'LOA Request Denied',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.loa-denied',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
