<?php

namespace App\Mail;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the facilities/complaints team every time a customer submits the
 * complaint form (mirrors the old uaa.ae WordPress site, which emailed new
 * complaints out from complaints@uaa.ae via WP Mail SMTP).
 */
class NewComplaintNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Complaint $complaint)
    {
    }

    public function build(): self
    {
        return $this->subject("New Complaint [{$this->complaint->ticket_number}] — {$this->complaint->name}")
            ->view('emails.complaint-notification');
    }
}
