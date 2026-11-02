<?php

use App\Http\Controllers\Api\V1\DonationController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\InquiryController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\StoryController;
use App\Http\Controllers\Api\V1\TagController;
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
        Route::get('home', [HomeController::class, 'show'])->name('home.show');
        Route::get('events', [EventController::class, 'index'])->name('events.index');
        Route::get('events/{slug}', [EventController::class, 'show'])
            ->where('slug', '[a-z0-9\-]+')
            ->name('events.show');
        Route::get('inquiry-types', [InquiryController::class, 'types'])->name('inquiry-types.index');
        Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
        Route::get('donation-options', [DonationController::class, 'options'])->name('donations.options');
        Route::get('tags', [TagController::class, 'index'])->name('tags.index');
        Route::get('stories', [StoryController::class, 'index'])->name('stories.index');
        Route::get('stories/{slug}', [StoryController::class, 'show'])
            ->where('slug', '[a-z0-9\-]+')
            ->name('stories.show');
    });

    // Writes come only from the website's server (it holds the key) and have their own limits.
    Route::middleware(['frontend.key', 'throttle:inquiries'])->group(function () {
        Route::post('inquiries', [InquiryController::class, 'store'])->name('inquiries.store');
    });

    // A donation's status is read fresh every time (never cached).
    Route::get('donations/{reference}', [DonationController::class, 'show'])
        ->whereUuid('reference')
        ->middleware('cache.headers:private;no_store')
        ->name('donations.show');

    Route::middleware('frontend.key')->group(function () {
        Route::post('donations', [DonationController::class, 'store'])->middleware('throttle:donations')->name('donations.store');
        Route::post('donations/{reference}/confirm', [DonationController::class, 'confirm'])
            ->whereUuid('reference')
            ->middleware('throttle:donation-confirm')
            ->name('donations.confirm');
    });
});
