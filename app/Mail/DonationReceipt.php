<?php

namespace App\Mail;

use App\Models\Donation;
use App\Services\Donations\ReceiptData;
use App\Services\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The official donation receipt for income tax purposes. A receipt from a test gateway carries a
 * "not valid for tax purposes" banner, so a sandbox run can never produce something that looks real.
 * (Sent from inside SendDonationReceipt, which is already queued.)
 */
class DonationReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Donation $donation) {}

    public function envelope(): Envelope
    {
        $settings = app(Settings::class);
        $replyTo = $settings->string('receipts.reply_to');

        return new Envelope(
            subject: ($this->donation->is_test ? '[TEST] ' : '').'Your donation receipt from '.$settings->string('receipts.charity_name').' (#'.$this->donation->receipt_number.')',
            replyTo: $replyTo === '' ? [] : [new Address($replyTo, $settings->string('receipts.charity_name'))],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'receipts.donation',
            text: 'receipts.donation-text',
            with: ['receipt' => ReceiptData::for($this->donation, app(Settings::class))],
        );
    }
}
