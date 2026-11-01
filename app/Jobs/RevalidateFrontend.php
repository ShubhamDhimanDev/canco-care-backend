<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tells the Next.js site to drop cached content (ADR-003). The website caches API reads
 * with tags; this posts the tags that changed to its /api/revalidate route handler.
 *
 * Retried with backoff; if it never succeeds the website's own cache lifetime still
 * brings the content up to date, just later.
 */
class RevalidateFrontend implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<string>  $tags  e.g. ["stories", "story:community-matters"]
     */
    public function __construct(public array $tags) {}

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

        Http::withToken($secret)
            ->acceptJson()
            ->timeout(10)
            ->post(rtrim($url, '/').'/api/revalidate', ['tags' => array_values(array_unique($this->tags))])
            ->throw();
    }
}
