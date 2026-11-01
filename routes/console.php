<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Needs the scheduler cron entry (`php artisan schedule:run` every minute) — see docs/plan/11-deployment.md.
Schedule::command('content:revalidate-due')->everyMinute()->withoutOverlapping();
