<?php

namespace App\Services\Donations;

use App\Enums\DonationStatus;

/**
 * The outcome of one charge attempt, in our terms (not any provider's).
 */
final readonly class GatewayResult
{
    public function __construct(
        /** Succeeded or Failed. */
        public DonationStatus $status,
        public ?string $transactionId = null,
        public ?string $code = null,
        public ?string $message = null,
        public ?string $cardBrand = null,
        public ?string $cardLast4 = null,
        /** Safe to show the donor ("Your card was declined"). */
        public ?string $failureReason = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->status === DonationStatus::Succeeded;
    }
}
