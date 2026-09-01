<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RosterRemovalFailed extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public string $reason;

    public string $error;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $reason, string $error)
    {
        $this->user = $user;
        $this->reason = $reason;
        $this->error = $error;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Roster Removal Failed: {$this->user->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.roster-removal-failed',
        );
    }
}
