<?php

namespace App\Mail;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The staff notification for a new inquiry. Replying goes straight to the sender.
 */
class InquiryReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Inquiry $inquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Canco Cares] New inquiry from '.$this->inquiry->fullName(),
            replyTo: [new Address($this->inquiry->email, $this->inquiry->fullName())],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.inquiry-received',
            text: 'mail.inquiry-received-text',
            with: ['adminUrl' => url('/inquiries/'.$this->inquiry->id)],
        );
    }
}
