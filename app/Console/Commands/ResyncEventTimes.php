<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\EventWriter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('events:resync-utc {--dry-run : Show what would change without writing}')]
#[Description("Re-derive every event's UTC start/end from its wall-clock time and zone (run after a time-zone database update)")]
class ResyncEventTimes extends Command
{
    public function handle(): int
    {
        $this->components->info('Using time-zone database '.timezone_version_get().'.');
        $changed = 0;

        Event::query()->withTrashed()->whereNotNull('starts_local')->each(function (Event $event) use (&$changed): void {
            $starts = EventWriter::toUtc(CarbonImmutable::parse($event->starts_local, 'UTC'), $event->timezone);
            $ends = $event->ends_local === null ? null : EventWriter::toUtc(CarbonImmutable::parse($event->ends_local, 'UTC'), $event->timezone);

            $same = $event->starts_at->equalTo($starts)
                && ($ends === null ? $event->ends_at === null : $event->ends_at?->equalTo($ends) === true);

            if ($same) {
                return;
            }

            $changed++;
            $this->line(sprintf('#%d %s: %s → %s (UTC)', $event->id, $event->slug, $event->starts_at->utc()->format('Y-m-d H:i'), $starts->format('Y-m-d H:i')));

            if (! $this->option('dry-run')) {
                $event->forceFill(['starts_at' => $starts, 'ends_at' => $ends])->save();
            }
        });

        $this->components->info($this->option('dry-run') ? "{$changed} events would change." : "{$changed} events updated.");

        return self::SUCCESS;
    }
}
