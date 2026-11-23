<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The short zone name printed next to an event time ("6:00 PM PT").
 *
 * Canadian zones get a generic label (PT, MT, CT, ET, AT, NT) on purpose: the abbreviation a tz
 * database prints (PST/PDT/MST…) depends on the rules the server happens to know, and rules change —
 * British Columbia moved to permanent UTC-7 on 1 Nov 2026. A generic label is right under either rule.
 * Other zones fall back to what PHP prints for that date.
 */
final class ZoneLabel
{
    private const GENERIC = [
        'America/Vancouver' => 'PT',
        'America/Edmonton' => 'MT',
        'America/Winnipeg' => 'CT',
        'America/Regina' => 'CT',
        'America/Toronto' => 'ET',
        'America/Halifax' => 'AT',
        'America/St_Johns' => 'NT',
    ];

    public static function for(string $timezone, CarbonInterface $at): string
    {
        return self::GENERIC[$timezone] ?? $at->copy()->setTimezone($timezone)->format('T');
    }
}
