<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Needs the scheduler cron entry (`php artisan schedule:run` every minute) — see docs/plan/11-deployment.md.
Schedule::command('content:revalidate-due')->everyMinute()->withoutOverlapping();

// Keeps the audit trail to AUDIT_RETENTION_DAYS (default 365).
Schedule::command('audit:prune')->dailyAt('03:30')->withoutOverlapping();
