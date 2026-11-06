<?php

namespace App\Observers;

use App\Jobs\RevalidateFrontend;
use App\Models\Story;

/**
 * Refreshes the website's cached copy whenever a story is created, edited, deleted
 * or restored.
 *
 * The observer itself runs immediately (so it can still see the previous slug after a
 * rename); only the queued job waits for any surrounding transaction to commit, so the
 * website never fetches data that is not visible yet.
 */
class StoryObserver
{
    public function saved(Story $story): void
    {
        $tags = $this->tags($story);

        // A rename also changes what the old URL should do.
        if ($story->wasChanged('slug') && is_string($story->getOriginal('slug'))) {
            $tags[] = 'story:'.$story->getOriginal('slug');
        }

        RevalidateFrontend::fire($tags);
    }

    public function deleted(Story $story): void
    {
        RevalidateFrontend::fire($this->tags($story));
    }

    public function restored(Story $story): void
    {
        RevalidateFrontend::fire($this->tags($story));
    }

    /**
     * @return list<string>
     */
    private function tags(Story $story): array
    {
        return ['stories', 'story:'.$story->slug];
    }
}
