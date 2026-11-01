<?php

namespace App\Console\Commands;

use App\Jobs\RevalidateFrontend;
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

        $slugs = Story::published()
            ->where('published_at', '>', now()->subMinutes($minutes))
            ->pluck('slug');

        if ($slugs->isEmpty()) {
            return self::SUCCESS;
        }

        $tags = ['stories'];
        foreach ($slugs as $slug) {
            $tags[] = "story:{$slug}";
        }

        RevalidateFrontend::dispatch($tags);

        $this->components->info("Revalidating {$slugs->count()} story(ies) that just went live.");

        return self::SUCCESS;
    }
}
