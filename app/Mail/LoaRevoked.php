<?php

namespace App\Mail;

use App\Models\Loa;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoaRevoked extends Mailable
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
            subject: 'LOA Revoked',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.loa-revoked',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
