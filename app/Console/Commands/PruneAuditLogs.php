<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('audit:prune {--days= : Keep this many days (default: AUDIT_RETENTION_DAYS, 365)}')]
#[Description('Delete audit-log entries older than the retention period')]
class PruneAuditLogs extends Command
{
    public function handle(): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : (int) config('audit.retention_days');

        if ($days <= 0) {
            $this->components->info('Retention is unlimited; nothing deleted.');

            return self::SUCCESS;
        }

        $deleted = AuditLog::query()->where('created_at', '<', now()->subDays($days))->delete();

        $this->components->info("Deleted {$deleted} audit entr".($deleted === 1 ? 'y' : 'ies')." older than {$days} days.");

        return self::SUCCESS;
    }
}
