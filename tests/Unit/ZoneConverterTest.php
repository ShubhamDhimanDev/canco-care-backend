<?php

use App\Support\ZoneConverter;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/*
| These must pass whatever tz database PHP was built with: the BC rule comes from config/timezones.php,
| not from the server (docs/plan/14 #5).
*/

uses(TestCase::class);

test('after 1 Nov 2026 Vancouver stays on UTC-7 all year, even on a server that still thinks it is UTC-8', function () {
    expect(ZoneConverter::toUtc('2026-12-05 18:00:00', 'America/Vancouver')->format('Y-m-d H:i'))->toBe('2026-12-06 01:00')
        ->and(ZoneConverter::toUtc('2027-01-15 09:00:00', 'America/Vancouver')->format('Y-m-d H:i'))->toBe('2027-01-15 16:00')
        ->and(ZoneConverter::toWallClock(CarbonImmutable::parse('2026-12-06 01:00:00', 'UTC'), 'America/Vancouver'))->toBe('2026-12-05 18:00:00');
});

test('an all-day Dec 1 event stays on Dec 1', function () {
    $start = ZoneConverter::toUtc('2026-12-01 00:00:00', 'America/Vancouver');

    expect($start->format('Y-m-d H:i'))->toBe('2026-12-01 07:00')
        ->and(ZoneConverter::toWallClock($start, 'America/Vancouver'))->toBe('2026-12-01 00:00:00');
});

test('the conversion round-trips around the change itself', function () {
    foreach (['2026-10-31 23:30:00', '2026-11-01 01:30:00', '2026-11-01 02:00:00', '2026-11-01 03:00:00', '2027-03-14 12:00:00'] as $wall) {
        expect(ZoneConverter::toWallClock(ZoneConverter::toUtc($wall, 'America/Vancouver'), 'America/Vancouver'))->toBe($wall);
    }
});

test('before the change the server rules apply, and other zones are untouched', function () {
    expect(ZoneConverter::toUtc('2026-07-04 12:00:00', 'America/Vancouver')->format('Y-m-d H:i'))->toBe('2026-07-04 19:00')
        ->and(ZoneConverter::toUtc('2027-04-10 18:00:00', 'America/Vancouver')->format('Y-m-d H:i'))->toBe('2027-04-11 01:00')
        ->and(ZoneConverter::toUtc('2026-12-05 18:00:00', 'America/Toronto')->format('Y-m-d H:i'))->toBe('2026-12-05 23:00')
        ->and(ZoneConverter::toUtc('2026-12-05 18:00:00', 'UTC')->format('Y-m-d H:i'))->toBe('2026-12-05 18:00');
});
