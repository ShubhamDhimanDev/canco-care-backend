<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DonationController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\HomePageController;
use App\Http\Controllers\Admin\InquiryController;
use App\Http\Controllers\Admin\InquiryTypeController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Admin\StoryController;
use Illuminate\Support\Facades\Route;

// The admin has no public landing page: send visitors to the dashboard (which asks guests to log in).
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Stories (blog). Trashed stories are only reachable through restore / permanent delete.
    Route::resource('stories', StoryController::class)->except('show');
    Route::patch('stories/{story}/restore', [StoryController::class, 'restore'])->withTrashed()->name('stories.restore');
    Route::delete('stories/{story}/force', [StoryController::class, 'forceDelete'])->withTrashed()->name('stories.force-delete');

    // Events.
    Route::resource('events', EventController::class)->except('show');
    Route::patch('events/{event}/restore', [EventController::class, 'restore'])->withTrashed()->name('events.restore');
    Route::delete('events/{event}/force', [EventController::class, 'forceDelete'])->withTrashed()->name('events.force-delete');

    // Inquiries inbox (`export` is declared before the {inquiry} routes).
    Route::get('inquiries', [InquiryController::class, 'index'])->name('inquiries.index');
    Route::get('inquiries/export', [InquiryController::class, 'export'])->name('inquiries.export');
    Route::get('inquiries/{inquiry}', [InquiryController::class, 'show'])->name('inquiries.show');
    Route::patch('inquiries/{inquiry}', [InquiryController::class, 'update'])->name('inquiries.update');
    Route::delete('inquiries/{inquiry}', [InquiryController::class, 'destroy'])->name('inquiries.destroy');

    // Administrator-only settings.
    Route::middleware('can:admin')->group(function () {
        Route::get('settings/site', [SiteSettingsController::class, 'edit'])->name('site-settings.edit');
        Route::put('settings/site', [SiteSettingsController::class, 'update'])->name('site-settings.update');

        // Donations: administrators only. `export` is declared before the {donation} routes.
        Route::get('donations', [DonationController::class, 'index'])->name('donations.index');
        Route::get('donations/export', [DonationController::class, 'export'])->name('donations.export');
        Route::get('donations/{donation}', [DonationController::class, 'show'])->name('donations.show');
        Route::get('donations/{donation}/receipt', [DonationController::class, 'receipt'])->name('donations.receipt');
        Route::post('donations/{donation}/resend', [DonationController::class, 'resend'])->name('donations.resend');

        Route::get('settings/inquiries', [InquiryTypeController::class, 'index'])->name('inquiry-types.index');
        Route::post('settings/inquiries/types', [InquiryTypeController::class, 'store'])->name('inquiry-types.store');
        Route::patch('settings/inquiries/types/{inquiry_type}', [InquiryTypeController::class, 'update'])->name('inquiry-types.update');
        Route::delete('settings/inquiries/types/{inquiry_type}', [InquiryTypeController::class, 'destroy'])->name('inquiry-types.destroy');
        Route::put('settings/inquiries/fallback', [InquiryTypeController::class, 'updateFallback'])->name('inquiry-types.fallback');
    });

    // Home page copy and pictures.
    Route::get('home-page', [HomePageController::class, 'edit'])->name('home-page.edit');
    Route::put('home-page', [HomePageController::class, 'update'])->name('home-page.update');

    // Media library. `library` is the JSON feed for the picker dialog (declared before the {media} routes).
    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::get('media/library', [MediaController::class, 'library'])->name('media.library');
    Route::post('media', [MediaController::class, 'store'])->name('media.store');
    Route::patch('media/{media}', [MediaController::class, 'update'])->name('media.update');
    Route::post('media/{media}/convert', [MediaController::class, 'convert'])->name('media.convert');
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
});

require __DIR__.'/settings.php';
