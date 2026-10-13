<?php

namespace App\Mail;

use App\Models\StaffingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffingRequestSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public StaffingRequest $staffingRequest;

    public function __construct(StaffingRequest $staffingRequest)
    {
        $this->staffingRequest = $staffingRequest;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Staffing Request Submitted',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.staffing-request-submitted',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
