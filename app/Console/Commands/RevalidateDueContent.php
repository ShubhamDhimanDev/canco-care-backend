<?php

namespace App\Console\Commands;

use App\Jobs\RevalidateFrontend;
use App\Models\Event;
use App\Models\Story;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('content:revalidate-due {--minutes=2 : How far back to look for content that just went live}')]
#[Description('Refresh the website for scheduled content whose publish time has just passed')]
class RevalidateDueContent extends Command
{
    /**
     * A story scheduled for the future changes nothing in the database when its time
     * arrives, so no model event fires. This runs every minute (see routes/console.php)
     * and tells the website about anything that has just become public.
     */
    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));

        $since = now()->subMinutes($minutes);
        $stories = Story::published()->where('published_at', '>', $since)->pluck('slug');
        $events = Event::visible()->where('published_at', '>', $since)->pluck('slug');

        if ($stories->isEmpty() && $events->isEmpty()) {
            return self::SUCCESS;
        }

        // The list tag first, then each item's own tag.
        $tags = array_merge(
            $stories->isEmpty() ? [] : ['stories'],
            array_values(array_map(fn (string $slug): string => "story:{$slug}", $stories->all())),
            $events->isEmpty() ? [] : ['events'],
            array_values(array_map(fn (string $slug): string => "event:{$slug}", $events->all())),
        );

        RevalidateFrontend::fire($tags);

        $this->components->info("Revalidating {$stories->count()} story(ies) and {$events->count()} event(s) that just went live.");

        return self::SUCCESS;
    }
}
