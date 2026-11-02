<?php

namespace App\Support;

use App\Models\Donation;
use App\Services\Settings;

/**
 * The "OUR IMPACT $80,000 AND COUNTING!" figure (docs/plan/13 §7). An administrator chooses:
 *
 *  - custom: the text exactly as typed ("$80,000", "Over $1M");
 *  - actual: a starting amount (money raised before this website existed) plus the real donations
 *    taken here, net of refunds. Test donations never count.
 */
final class ImpactFigure
{
    public const CUSTOM = 'custom';

    public const ACTUAL = 'actual';

    public function __construct(private readonly Settings $settings) {}

    public function mode(): string
    {
        return $this->settings->string('impact.mode') === self::ACTUAL ? self::ACTUAL : self::CUSTOM;
    }

    /**
     * What the website prints.
     */
    public function amountText(): string
    {
        if ($this->mode() === self::CUSTOM) {
            return $this->settings->string('impact.amount_text');
        }

        return self::format($this->settings->int('impact.actual_offset_cents') + $this->raisedCents());
    }

    /**
     * Net real donations, in cents.
     */
    public function raisedCents(): int
    {
        return (int) Donation::raised()->selectRaw('COALESCE(SUM(amount_cents - refunded_cents), 0) as total')->value('total');
    }

    /**
     * 8_012_345 → "$80,123" (whole dollars, rounded down: a figure is never overstated).
     */
    public static function format(int $cents): string
    {
        return '$'.number_format(intdiv(max(0, $cents), 100));
    }
}
