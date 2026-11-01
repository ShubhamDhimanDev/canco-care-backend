<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\StoryController;
use Illuminate\Support\Facades\Route;

/*
| Public JSON API, version 1 — the contract is docs/plan/03-api-spec.md.
| Reads are public (called server-side by the Next.js app). Writes added later
| must sit behind the `frontend.key` middleware.
*/
Route::prefix('v1')->name('api.v1.')->middleware('throttle:public-api')->group(function () {
    Route::get('health', HealthController::class)->name('health');

    // Cacheable reads: browsers/CDNs may reuse for 60 s and serve stale for 5 min while revalidating.
    Route::middleware('cache.headers:public;max_age=60;stale_while_revalidate=300;etag')->group(function () {
        Route::get('stories', [StoryController::class, 'index'])->name('stories.index');
        Route::get('stories/{slug}', [StoryController::class, 'show'])
            ->where('slug', '[a-z0-9\-]+')
            ->name('stories.show');
    });
});
