<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

// This is an admin-only system: accounts are created by an administrator
// (`php artisan make:admin` or the Users screen), never by the public.

test('public registration is switched off in the Fortify config', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse();
});

test('there is no registration route', function () {
    expect(Route::has('register'))->toBeFalse()
        ->and(Route::has('register.store'))->toBeFalse();
});

test('the registration screen is not reachable', function () {
    $this->get('/register')->assertNotFound();
});

test('nobody can create an account by posting to the registration endpoint', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    expect(User::count())->toBe(0);
});
