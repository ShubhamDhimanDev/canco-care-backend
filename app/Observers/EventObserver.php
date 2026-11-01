<?php

namespace App\Observers;

use App\Jobs\RevalidateFrontend;
use App\Models\Event;

/**
 * Refreshes the website's cached events whenever one is created, edited, deleted or restored. The
 * donation form lists events too, so its cache ("events") is the same tag.
 */
class EventObserver
{
    public function saved(Event $event): void
    {
        $tags = $this->tags($event);

        if ($event->wasChanged('slug') && is_string($event->getOriginal('slug'))) {
            $tags[] = 'event:'.$event->getOriginal('slug');
        }

        RevalidateFrontend::dispatch($tags)->afterCommit();
    }

    public function deleted(Event $event): void
    {
        RevalidateFrontend::dispatch($this->tags($event))->afterCommit();
    }

    public function restored(Event $event): void
    {
        RevalidateFrontend::dispatch($this->tags($event))->afterCommit();
    }

    /**
     * @return list<string>
     */
    private function tags(Event $event): array
    {
        return ['events', 'event:'.$event->slug];
    }
}
