<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Converts between an organiser's wall-clock time in a zone and a UTC instant, applying the rules in
 * `config/timezones.php` before falling back to the server's time-zone database. Used for events so the
 * result does not depend on how new the server's tz data is (docs/plan/14 #5).
 */
final class ZoneConverter
{
    /**
     * A wall-clock time ("2026-12-05 18:00:00", no zone) in `$timezone` as a UTC instant.
     */
    public static function toUtc(string $wallClock, string $timezone): CarbonImmutable
    {
        $override = self::override($timezone);

        if ($override !== null) {
            $candidate = CarbonImmutable::parse($wallClock, 'UTC')->subMinutes($override['offset_minutes']);

            if ($candidate->greaterThanOrEqualTo($override['from_utc'])) {
                return $candidate;
            }
        }

        return CarbonImmutable::parse($wallClock, $timezone)->utc();
    }

    /**
     * A UTC instant as the wall-clock time in `$timezone`, "2026-12-05 18:00:00".
     */
    public static function toWallClock(DateTimeInterface $instant, string $timezone): string
    {
        $utc = CarbonImmutable::instance($instant)->utc();
        $override = self::override($timezone);

        if ($override !== null && $utc->greaterThanOrEqualTo($override['from_utc'])) {
            return $utc->addMinutes($override['offset_minutes'])->format('Y-m-d H:i:s');
        }

        return $utc->setTimezone($timezone)->format('Y-m-d H:i:s');
    }

    /**
     * @return array{from_utc: CarbonImmutable, offset_minutes: int}|null
     */
    private static function override(string $timezone): ?array
    {
        $rule = config("timezones.overrides.{$timezone}");

        if (! is_array($rule)) {
            return null;
        }

        return [
            'from_utc' => CarbonImmutable::parse((string) $rule['from_utc'], 'UTC'),
            'offset_minutes' => (int) $rule['offset_minutes'],
        ];
    }
}
