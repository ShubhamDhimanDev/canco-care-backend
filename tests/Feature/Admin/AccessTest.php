<?php

use App\Enums\UserRole;
use App\Models\Story;
use App\Models\User;
use App\Services\PermissionSync;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** A sub-admin holding a role made of exactly these permissions. */
function subAdminWith(array $permissions, string $roleName = 'Custom'): User
{
    (new PermissionSync)->run();

    $role = Role::findOrCreate($roleName, 'web');
    $role->syncPermissions($permissions);

    $user = User::factory()->withoutRoles()->create();
    $user->assignRole($role);

    return $user;
}

describe('permissions', function () {
    test('the catalog is written to the database and the sync is repeatable', function () {
        $this->artisan('permissions:sync')->assertSuccessful();
        $this->artisan('permissions:sync')->expectsOutputToContain('up to date')->assertSuccessful();

        expect(Permission::count())->toBe(count(PermissionCatalog::all()))
            ->and(Role::where('name', 'Editor')->first()->permissions()->count())->toBe(count(PermissionCatalog::editorDefaults()));
    });

    test('the sync never overwrites a role the owner has edited', function () {
        (new PermissionSync)->run();
        Role::findByName('Editor')->syncPermissions(['stories.view']);

        (new PermissionSync)->run();

        expect(Role::findByName('Editor')->permissions->pluck('name')->all())->toBe(['stories.view']);
    });

    test('every default editor permission exists in the catalog', function () {
        expect(array_diff(PermissionCatalog::editorDefaults(), PermissionCatalog::all()))->toBe([]);
    });

    test('a super administrator passes every check without holding any role', function () {
        $admin = User::factory()->admin()->create();

        expect($admin->getRoleNames())->toHaveCount(0)
            ->and($admin->can('stories.force-delete'))->toBeTrue()
            ->and($admin->can('donations.view'))->toBeTrue();
    });

    test('a sub-admin has only what their roles give', function () {
        $user = subAdminWith(['stories.view', 'stories.create']);
        $story = Story::factory()->create();

        expect($user->can('viewAny', Story::class))->toBeTrue()
            ->and($user->can('create', Story::class))->toBeTrue()
            ->and($user->can('update', $story))->toBeFalse()
            ->and($user->can('delete', $story))->toBeFalse()
            ->and($user->can('forceDelete', $story))->toBeFalse();
    });

    test('a default editor can work on content but not on money, settings or permanent deletion', function () {
        $editor = User::factory()->create();
        $story = Story::factory()->create();

        expect($editor->can('update', $story))->toBeTrue()
            ->and($editor->can('forceDelete', $story))->toBeFalse()
            ->and($editor->can('donations.view'))->toBeFalse()
            ->and($editor->can('site-settings.manage'))->toBeFalse();
    });

    test('a change to a role takes effect on the very next request', function () {
        $user = subAdminWith(['stories.view']);
        $this->actingAs($user)->get('/events')->assertForbidden();

        Role::findByName('Custom')->givePermissionTo('events.view');

        $this->actingAs($user->fresh())->get('/events')->assertOk();
    });
});

describe('routes', function () {
    test('a sub-admin sees only the areas their role allows', function () {
        $user = subAdminWith(['stories.view']);

        $this->actingAs($user)->get('/stories')->assertOk();
        $this->actingAs($user)->get('/events')->assertForbidden();
        $this->actingAs($user)->get('/inquiries')->assertForbidden();
        $this->actingAs($user)->get('/media')->assertForbidden();
        $this->actingAs($user)->get('/home-page')->assertForbidden();
        $this->actingAs($user)->get('/donations')->assertForbidden();
        $this->actingAs($user)->get('/settings/site')->assertForbidden();
        $this->actingAs($user)->get('/settings/inquiries')->assertForbidden();
        $this->actingAs($user)->get('/stories/create')->assertForbidden();
    });

    test('a sub-admin with no role can sign in to the dashboard and nothing else', function () {
        $user = User::factory()->withoutRoles()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get('/stories')->assertForbidden();
        $this->actingAs($user)->get('/inquiries')->assertForbidden();
    });

    test('donations can be viewed without being exported or resent', function () {
        $user = subAdminWith(['donations.view']);

        $this->actingAs($user)->get('/donations')->assertOk();
        $this->actingAs($user)->get('/donations/export')->assertForbidden();
    });

    test('inquiry export has its own permission', function () {
        $viewer = subAdminWith(['inquiries.view'], 'Viewer');
        $exporter = subAdminWith(['inquiries.view', 'inquiries.export'], 'Exporter');

        $this->actingAs($viewer)->get('/inquiries/export')->assertForbidden();
        $this->actingAs($exporter)->get('/inquiries/export')->assertOk();
    });

    test('someone who can edit stories can pick and upload pictures without the media screen', function () {
        $writer = subAdminWith(['stories.view', 'stories.update']);
        $viewerOnly = subAdminWith(['stories.view'], 'Viewer');

        $this->actingAs($writer)->getJson('/media/library')->assertOk();
        $this->actingAs($writer)->get('/media')->assertForbidden();
        $this->actingAs($viewerOnly)->getJson('/media/library')->assertForbidden();
    });

    test('permissions are shared with the pages', function () {
        $user = subAdminWith(['stories.view']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('permissions', ['stories.view'])
            ->where('isAdmin', false));

        $this->actingAs($admin)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('permissions', PermissionCatalog::all())
            ->where('isAdmin', true));
    });

    test('the dashboard hides donation figures from people without the donations permission', function () {
        $this->actingAs(subAdminWith(['stories.view']))->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('donations', null));

        $this->actingAs(subAdminWith(['donations.view'], 'Money'))->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('donations', fn ($value) => $value !== null));
    });
});

describe('users and roles screens', function () {
    test('are for super administrators only', function () {
        $editor = User::factory()->create();

        foreach (['/users', '/users/create', '/roles', '/roles/create'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        foreach (['/users', '/users/create', '/roles', '/roles/create'] as $url) {
            $this->actingAs($editor)->get($url)->assertForbidden();
        }

        $this->actingAs($editor)->post('/roles', ['name' => 'Sneaky', 'permissions' => ['donations.view']])->assertForbidden();
        $this->actingAs($editor)->post('/users', [])->assertForbidden();
    });

    test('lists accounts and roles', function () {
        $this->actingAs(User::factory()->admin()->create());
        subAdminWith(['stories.view']);

        $this->get('/users')->assertOk()->assertInertia(fn (Assert $page) => $page->component('users/index')->has('users', 2));
        $this->get('/roles')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('roles/index')
            ->where('totalPermissions', count(PermissionCatalog::all())));
    });
});

describe('roles', function () {
    beforeEach(function () {
        (new PermissionSync)->run();
        $this->admin = User::factory()->admin()->create();
    });

    test('are created with a set of permissions', function () {
        $this->actingAs($this->admin)
            ->post('/roles', ['name' => 'Content writer', 'permissions' => ['stories.view', 'stories.create']])
            ->assertRedirect('/roles');

        expect(Role::findByName('Content writer')->permissions->pluck('name')->sort()->values()->all())
            ->toBe(['stories.create', 'stories.view']);
    });

    test('reject unknown permissions and duplicate names', function () {
        $this->actingAs($this->admin)
            ->post('/roles', ['name' => 'Bad', 'permissions' => ['everything.at.once']])
            ->assertSessionHasErrors('permissions.0');

        $this->actingAs($this->admin)
            ->post('/roles', ['name' => 'Editor', 'permissions' => []])
            ->assertSessionHasErrors('name');
    });

    test('can be renamed and have permissions removed', function () {
        $role = Role::findByName('Editor');

        $this->actingAs($this->admin)
            ->put("/roles/{$role->id}", ['name' => 'Staff', 'permissions' => ['inquiries.view']])
            ->assertRedirect();

        $role->refresh();

        expect($role->name)->toBe('Staff')
            ->and($role->permissions->pluck('name')->all())->toBe(['inquiries.view']);
    });

    test('keep their own name when saved again', function () {
        $role = Role::findByName('Editor');

        $this->actingAs($this->admin)
            ->put("/roles/{$role->id}", ['name' => 'Editor', 'permissions' => []])
            ->assertSessionHasNoErrors();
    });

    test('can be deleted, and the people who held them lose that access', function () {
        $user = subAdminWith(['stories.view']);
        $role = Role::findByName('Custom');

        $this->actingAs($this->admin)->delete("/roles/{$role->id}")->assertRedirect('/roles');

        expect(Role::where('name', 'Custom')->exists())->toBeFalse();
        $this->actingAs($user->fresh())->get('/stories')->assertForbidden();
    });

    test('the edit screen lists every permission group', function () {
        $role = Role::findByName('Editor');

        $this->actingAs($this->admin)->get("/roles/{$role->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->component('roles/edit')
            ->has('groups', count(PermissionCatalog::groups()))
            ->where('role.name', 'Editor')
            ->has('role.permissions', count(PermissionCatalog::editorDefaults())));
    });
});

describe('users', function () {
    beforeEach(function () {
        (new PermissionSync)->run();
        $this->admin = User::factory()->admin()->create();
    });

    test('a sub-admin is created with roles, verified and able to sign in', function () {
        $role = Role::findByName('Editor');

        $this->actingAs($this->admin)->post('/users', [
            'name' => 'Sam Sub',
            'email' => 'sam@example.test',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
            'roles' => [$role->id],
        ])->assertRedirect('/users');

        $sam = User::where('email', 'sam@example.test')->firstOrFail();

        expect($sam->role)->toBe(UserRole::Editor)
            ->and($sam->email_verified_at)->not->toBeNull()
            ->and(Hash::check('a-long-enough-password', $sam->password))->toBeTrue()
            ->and($sam->hasRole('Editor'))->toBeTrue();

        auth()->logout();
        $this->post('/login', ['email' => 'sam@example.test', 'password' => 'a-long-enough-password'])->assertRedirect();
        $this->get('/stories')->assertOk();
    });

    test('a new account needs a confirmed password and a unique email', function () {
        $this->actingAs($this->admin)->post('/users', [
            'name' => 'No Password',
            'email' => $this->admin->email,
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['email', 'password']);
    });

    test('the super administrator switch makes someone an administrator', function () {
        $this->actingAs($this->admin)->post('/users', [
            'name' => 'Second Admin',
            'email' => 'second@example.test',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
            'is_super_admin' => 1,
        ])->assertRedirect('/users');

        expect(User::where('email', 'second@example.test')->first()->isAdmin())->toBeTrue();
    });

    test('editing changes roles and keeps the password when it is left empty', function () {
        $user = subAdminWith(['stories.view']);
        $before = $user->password;
        $inquiries = subAdminWith(['inquiries.view'], 'Inbox');

        $this->actingAs($this->admin)->put("/users/{$user->id}", [
            'name' => 'Renamed',
            'email' => $user->email,
            'password' => '',
            'password_confirmation' => '',
            'roles' => [Role::findByName('Inbox')->id],
        ])->assertRedirect();

        $user->refresh();

        expect($user->name)->toBe('Renamed')
            ->and($user->password)->toBe($before)
            ->and($user->getRoleNames()->all())->toBe(['Inbox']);
        $this->actingAs($user)->get('/stories')->assertForbidden();
        $this->actingAs($user)->get('/inquiries')->assertOk();
        expect($inquiries->id)->not->toBe($user->id);
    });

    test('editing can set a new password', function () {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->put("/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'brand-new-password-1',
            'password_confirmation' => 'brand-new-password-1',
        ])->assertSessionHasNoErrors();

        expect(Hash::check('brand-new-password-1', $user->fresh()->password))->toBeTrue();
    });

    test('an administrator cannot remove their own super-administrator access', function () {
        $this->actingAs($this->admin)->put("/users/{$this->admin->id}", [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
        ])->assertSessionHasErrors('is_super_admin');

        expect($this->admin->fresh()->isAdmin())->toBeTrue();
    });

    test('another administrator can be demoted while one remains', function () {
        $other = User::factory()->admin()->create();

        $this->actingAs($this->admin)->put("/users/{$other->id}", [
            'name' => $other->name,
            'email' => $other->email,
        ])->assertSessionHasNoErrors();

        expect($other->fresh()->isAdmin())->toBeFalse();
    });

    test('a sub-admin can be deleted but not your own account', function () {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->delete("/users/{$user->id}")->assertRedirect('/users');
        expect(User::find($user->id))->toBeNull();

        $this->actingAs($this->admin)->delete("/users/{$this->admin->id}")->assertSessionHasErrors('delete');
        expect(User::find($this->admin->id))->not->toBeNull();
    });

    test('the edit screen includes the account\'s roles', function () {
        $user = subAdminWith(['stories.view']);

        $this->actingAs($this->admin)->get("/users/{$user->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->component('users/edit')
            ->where('user.roles', ['Custom'])
            ->has('user.role_ids', 1));
    });
});
