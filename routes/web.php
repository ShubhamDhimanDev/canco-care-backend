<?php

use App\Http\Controllers\Admin\StoryController;
use Illuminate\Support\Facades\Route;

// The admin has no public landing page: send visitors to the dashboard (which asks guests to log in).
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Stories (blog). Trashed stories are only reachable through restore / permanent delete.
    Route::resource('stories', StoryController::class)->except('show');
    Route::patch('stories/{story}/restore', [StoryController::class, 'restore'])->withTrashed()->name('stories.restore');
    Route::delete('stories/{story}/force', [StoryController::class, 'forceDelete'])->withTrashed()->name('stories.force-delete');
});

require __DIR__.'/settings.php';
