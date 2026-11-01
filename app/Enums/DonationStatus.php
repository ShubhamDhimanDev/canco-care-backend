<?php

namespace App\Enums;

enum DonationStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Canceled = 'canceled';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
            self::Canceled => 'Canceled',
            self::Refunded => 'Refunded',
            self::PartiallyRefunded => 'Partly refunded',
        };
    }

    /**
     * The only legal moves. A succeeded donation never goes back to pending or failed, and a
     * failed one is final (the donor starts a new attempt).
     *
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::Pending => [self::Succeeded, self::Failed, self::Canceled],
            self::Succeeded => [self::PartiallyRefunded, self::Refunded],
            self::PartiallyRefunded => [self::Refunded],
            default => [],
        };
    }

    public function canBecome(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    /**
     * Counts towards "money raised" (a partly refunded gift still counts, net of the refund).
     */
    public function counts(): bool
    {
        return in_array($this, [self::Succeeded, self::PartiallyRefunded], true);
    }
}
