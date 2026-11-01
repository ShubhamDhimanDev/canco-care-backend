<?php

namespace App\Jobs;

use App\Enums\DonationStatus;
use App\Mail\DonationReceipt;
use App\Models\Donation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the tax receipt to the donor once a donation has succeeded. Safe to run twice (resend):
 * it only ever sends for a succeeded donation that has a receipt number.
 */
class SendDonationReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $donationId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(): void
    {
        $donation = Donation::find($this->donationId);

        if ($donation === null || $donation->receipt_number === null || ! in_array($donation->status, [DonationStatus::Succeeded, DonationStatus::PartiallyRefunded], true)) {
            return;
        }

        Mail::to($donation->donor_email, $donation->donorName())->send(new DonationReceipt($donation));

        $donation->forceFill(['receipt_sent_at' => now()])->save();
    }
}
