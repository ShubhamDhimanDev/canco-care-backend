<?php

use App\Enums\UserRole;
use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

test('new users default to the least-privileged role', function () {
    // Bypass the factory (which sets a role) to prove the database default.
    $id = DB::table('users')->insertGetId([
        'name' => 'Plain',
        'email' => 'plain@example.com',
        'password' => 'x',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(User::findOrFail($id)->role)->toBe(UserRole::Editor);
});

test('role cannot be mass assigned', function () {
    $user = new User(['name' => 'N', 'email' => 'n@example.com', 'password' => 'x', 'role' => 'admin']);

    expect($user->role ?? null)->not->toBe(UserRole::Admin);
});

test('the admin gate is open to administrators only', function () {
    $admin = User::factory()->admin()->create();
    $editor = User::factory()->create();

    expect(Gate::forUser($admin)->allows('admin'))->toBeTrue()
        ->and(Gate::forUser($editor)->allows('admin'))->toBeFalse();
});

test('routes behind can:admin are closed to editors and guests', function () {
    Route::middleware(['web', 'auth', 'can:admin'])->get('/_admin-only', fn () => 'secret');

    $this->get('/_admin-only')->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())->get('/_admin-only')->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())->get('/_admin-only')->assertOk();
});

test('both roles can manage stories but only administrators can force-delete them', function () {
    $admin = User::factory()->admin()->create();
    $editor = User::factory()->create();
    $story = Story::factory()->create();

    foreach (['viewAny', 'create'] as $ability) {
        expect($editor->can($ability, Story::class))->toBeTrue();
    }

    foreach (['view', 'update', 'delete', 'restore'] as $ability) {
        expect($editor->can($ability, $story))->toBeTrue();
    }

    expect($editor->can('forceDelete', $story))->toBeFalse()
        ->and($admin->can('forceDelete', $story))->toBeTrue();
});

test('the database seeder creates no accounts with known credentials', function () {
    $this->seed();

    expect(User::count())->toBe(0);
});
