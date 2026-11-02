<?php

namespace App\Mail;

use App\Models\Inquiry;
use App\Services\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "We got your message" for the person who wrote in, with the reference they can quote.
 */
class InquiryAutoReply extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Inquiry $inquiry) {}

    public function envelope(): Envelope
    {
        $replyTo = app(Settings::class)->string('contact.inquiry_email');

        return new Envelope(
            subject: 'We received your message — Canco Cares',
            replyTo: $replyTo === '' ? [] : [new Address($replyTo, 'Canco Cares')],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.inquiry-auto-reply', text: 'mail.inquiry-auto-reply-text');
    }
}
