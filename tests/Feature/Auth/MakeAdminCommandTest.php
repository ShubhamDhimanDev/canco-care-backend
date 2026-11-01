<?php

use App\Enums\UserRole;
use App\Models\User;

test('it creates a verified administrator', function () {
    $this->artisan('make:admin', [
        'email' => 'boss@example.com',
        '--name' => 'Boss',
        '--password' => 'a-Long-Passw0rd!',
    ])->assertSuccessful();

    $user = User::where('email', 'boss@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Admin)
        ->and($user->isAdmin())->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->not->toBe('a-Long-Passw0rd!'); // hashed
});

test('the created administrator can log in and reach the dashboard', function () {
    $this->artisan('make:admin', [
        'email' => 'boss@example.com',
        '--name' => 'Boss',
        '--password' => 'a-Long-Passw0rd!',
    ])->assertSuccessful();

    $this->post(route('login.store'), ['email' => 'boss@example.com', 'password' => 'a-Long-Passw0rd!'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->get(route('dashboard'))->assertOk();
});

test('it promotes an existing editor without touching their password', function () {
    $editor = User::factory()->create(['email' => 'editor@example.com']);
    $passwordHash = $editor->password;

    $this->artisan('make:admin', ['email' => 'editor@example.com'])->assertSuccessful();

    $editor->refresh();

    expect($editor->role)->toBe(UserRole::Admin)
        ->and($editor->password)->toBe($passwordHash)
        ->and(User::count())->toBe(1);
});

test('it rejects an invalid email and creates nobody', function () {
    $this->artisan('make:admin', ['email' => 'not-an-email', '--name' => 'X', '--password' => 'a-Long-Passw0rd!'])
        ->assertFailed();

    expect(User::count())->toBe(0);
});

test('it rejects a password that fails the rules', function () {
    $this->artisan('make:admin', ['email' => 'boss@example.com', '--name' => 'Boss', '--password' => 'short'])
        ->assertFailed();

    expect(User::count())->toBe(0);
});
