<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the Next.js site to drop cached content (ADR-003). The website caches API reads
 * with tags; this posts the tags that changed to its /api/revalidate route handler.
 *
 * Content changes must show on the website immediately, so `fire()` (used by every observer and
 * controller) runs the request right away, once the database transaction has committed, instead of
 * waiting for a queue worker. Only if that attempt fails (the website is down or restarting) is the
 * same job handed to the real queue, which retries with backoff. If even that never succeeds, the
 * website's own cache lifetime (`cacheLife` in front/next.config.ts) brings the content up to date.
 */
class RevalidateFrontend implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<string>  $tags  e.g. ["stories", "story:community-matters"]
     * @param  bool  $immediate  true for the first attempt made inside the request; a failure then falls back to the queue
     */
    public function __construct(public array $tags, public bool $immediate = false) {}

    /**
     * Refresh the website now (after commit), falling back to the queue if it cannot be reached.
     *
     * @param  list<string>  $tags
     */
    public static function fire(array $tags): void
    {
        static::dispatch($tags, true)->onConnection('sync')->afterCommit();
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(): void
    {
        $url = (string) config('services.frontend.url');
        $secret = (string) config('services.frontend.revalidate_secret');

        if ($url === '' || $secret === '') {
            Log::warning('Frontend revalidation skipped: FRONT_URL or REVALIDATE_SECRET is not configured.', ['tags' => $this->tags]);

            return;
        }

        try {
            Http::withToken($secret)
                ->acceptJson()
                // An editor is waiting on this request, so the first attempt gives up quickly.
                ->connectTimeout($this->immediate ? 2 : 5)
                ->timeout($this->immediate ? 4 : 10)
                ->post(rtrim($url, '/').'/api/revalidate', ['tags' => array_values(array_unique($this->tags))])
                ->throw();
        } catch (Throwable $exception) {
            if (! $this->immediate) {
                throw $exception;
            }

            Log::warning('Frontend revalidation failed; queued for retry.', ['tags' => $this->tags, 'error' => $exception->getMessage()]);

            static::dispatch($this->tags);
        }
    }
}
