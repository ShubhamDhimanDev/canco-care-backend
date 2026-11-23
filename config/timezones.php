<?php

/*
| Zone rules the application applies itself, so a server with an old time-zone database (PHP's own, or the
| operating system's `tzdata`) still converts event times correctly. An override only takes effect from
| `from_utc` on; before that the server's own rules apply. When the server's database already knows the
| rule, the override gives the same answer, so it is safe to leave in place.
|
| British Columbia moved to permanent Pacific time (UTC-7, "Pacific Daylight Time all year") as of
| 2026-11-01: the clocks were NOT put back at 02:00 PDT that day, i.e. from 09:00 UTC onwards.
*/

return [

    'overrides' => [
        'America/Vancouver' => [
            'from_utc' => '2026-11-01 09:00:00',
            'offset_minutes' => -420,
        ],
    ],

];
