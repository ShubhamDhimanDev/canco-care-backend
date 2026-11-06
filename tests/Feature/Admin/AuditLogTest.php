<?php

use App\Models\AuditLog;
use App\Models\Donation;
use App\Models\Event;
use App\Models\Inquiry;
use App\Models\InquiryType;
use App\Models\Story;
use App\Models\User;
use App\Services\Audit;
use App\Services\PermissionSync;
use App\Services\Settings;
use App\Services\TagService;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Http::fake();
});

function lastLog(): AuditLog
{
    return AuditLog::query()->latest('id')->firstOrFail();
}

describe('model changes', function () {
    test('a creation records the new values only', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $story = Story::factory()->create(['title' => 'Spring drive', 'slug' => 'spring-drive']);

        $log = AuditLog::where('auditable_type', 'story')->where('auditable_id', (string) $story->id)->firstOrFail();

        expect($log->event)->toBe('created')
            ->and($log->user_id)->toBe($admin->id)
            ->and($log->actor_name)->toBe($admin->name)
            ->and($log->actor_email)->toBe($admin->email)
            ->and($log->source)->toBe('admin')
            ->and($log->auditable_label)->toBe('Spring drive')
            ->and($log->old_values)->toBeNull()
            ->and($log->new_values)->toMatchArray(['title' => 'Spring drive', 'slug' => 'spring-drive'])
            ->and($log->new_values)->not->toHaveKeys(['id', 'created_at', 'updated_at']);
    });

    test('an update records only the fields that changed, old and new', function () {
        $story = Story::factory()->create(['title' => 'Before', 'excerpt' => 'Same']);
        $this->actingAs(User::factory()->admin()->create());

        $story->update(['title' => 'After']);

        $log = lastLog();

        expect($log->event)->toBe('updated')
            ->and($log->old_values)->toBe(['title' => 'Before'])
            ->and($log->new_values)->toBe(['title' => 'After'])
            ->and($log->auditable_label)->toBe('After');
    });

    test('saving without changing anything writes nothing', function () {
        $story = Story::factory()->create();
        $count = AuditLog::count();

        $story->save();
        $story->touch();

        expect(AuditLog::count())->toBe($count);
    });

    test('enums and dates are stored as plain values', function () {
        $story = Story::factory()->create();

        $story->update(['status' => 'draft', 'published_at' => null]);

        expect(lastLog()->new_values)->toMatchArray(['status' => 'draft', 'published_at' => null])
            ->and(lastLog()->old_values['status'])->toBe('published');
    });

    test('trash, restore and permanent delete are told apart', function () {
        $story = Story::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $story->delete();
        expect(lastLog()->event)->toBe('trashed')->and(lastLog()->old_values)->toBeNull();

        $story->restore();
        expect(lastLog()->event)->toBe('restored');

        $story->forceDelete();
        $log = lastLog();

        expect($log->event)->toBe('deleted')
            ->and($log->old_values)->toHaveKey('title')
            ->and($log->new_values)->toBeNull()
            // Still readable after the record is gone.
            ->and($log->auditable_label)->toBe($story->title);
    });

    test('a password change is recorded as changed but never shows either value', function () {
        $user = User::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $user->update(['password' => 'a-brand-new-password-1']);

        $log = lastLog();

        expect($log->event)->toBe('updated')
            ->and($log->old_values)->toBe(['password' => Audit::HIDDEN])
            ->and($log->new_values)->toBe(['password' => Audit::HIDDEN])
            ->and(json_encode(AuditLog::all()))->not->toContain('a-brand-new-password-1')
            ->and(json_encode(AuditLog::all()))->not->toContain($user->getOriginal('password'));
    });

    test('a remember-me token refresh is not worth a log line', function () {
        $user = User::factory()->create();
        $count = AuditLog::count();

        $user->forceFill(['remember_token' => 'abc'])->save();

        expect(AuditLog::count())->toBe($count);
    });

    test('long text is cut', function () {
        config(['audit.max_value_length' => 50]);
        $story = Story::factory()->create();

        $story->update(['body' => str_repeat('x', 500)]);

        expect(lastLog()->new_values['body'])->toContain('[cut: 450 more characters]')
            ->and(mb_strlen(lastLog()->new_values['body']))->toBeLessThan(120);
    });

    test('work done by artisan or a seeder is attributed to the console, not a person', function () {
        Story::factory()->create();

        expect(lastLog()->user_id)->toBeNull()->and(lastLog()->source)->toBe('console');
    });

    test('a failure to write the log never breaks the action', function () {
        AuditLog::creating(fn () => throw new RuntimeException('disk full'));

        $story = Story::factory()->create();

        expect(Story::find($story->id))->not->toBeNull();
    });

    test('inquiries, media, topics and events are covered', function () {
        $this->actingAs(User::factory()->admin()->create());

        Inquiry::factory()->create();
        InquiryType::factory()->create();
        Event::factory()->create();

        expect(AuditLog::pluck('auditable_type')->unique()->sort()->values()->all())
            ->toBe(['event', 'inquiry', 'inquiry_type', 'user']);
    });
});

describe('other actions', function () {
    test('a settings save lists only the settings that changed', function () {
        $this->actingAs(User::factory()->admin()->create());
        $settings = app(Settings::class);

        $settings->setMany(['impact.caption' => 'NEW CAPTION', 'impact.mode' => 'custom']);

        $log = lastLog();

        expect($log->auditable_type)->toBe('setting')
            ->and($log->auditable_id)->toBe('impact')
            ->and($log->old_values)->toBe(['impact.caption' => 'AND COUNTING!'])
            ->and($log->new_values)->toBe(['impact.caption' => 'NEW CAPTION']);

        $count = AuditLog::count();
        $settings->setMany(['impact.caption' => 'NEW CAPTION']);

        expect(AuditLog::count())->toBe($count);
    });

    test('tag changes on a story are logged', function () {
        $story = Story::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $tags = app(TagService::class);

        $tags->sync($story, ['Food drive']);
        expect(lastLog()->old_values)->toBeNull()->and(lastLog()->new_values)->toBe(['tags' => ['Food drive']]);

        $tags->sync($story, ['Food drive', 'Education']);
        expect(lastLog()->old_values)->toBe(['tags' => ['Food drive']])
            ->and(lastLog()->new_values)->toBe(['tags' => ['Education', 'Food drive']]);
    });

    test('sign-in, sign-out and a failed sign-in are logged', function () {
        $user = User::factory()->create(['email' => 'who@example.test']);

        $this->post('/login', ['email' => 'who@example.test', 'password' => 'wrong-password'])->assertSessionHasErrors();
        expect(lastLog()->event)->toBe('login_failed')
            ->and(lastLog()->new_values)->toBe(['email' => 'who@example.test'])
            ->and(lastLog()->user_id)->toBeNull();

        $this->post('/login', ['email' => 'who@example.test', 'password' => 'password'])->assertRedirect();
        expect(lastLog()->event)->toBe('login')->and(lastLog()->user_id)->toBe($user->id);

        $this->post('/logout');
        expect(lastLog()->event)->toBe('logout')->and(lastLog()->user_id)->toBe($user->id);

        expect(json_encode(AuditLog::all()))->not->toContain('wrong-password');
    });

    test('creating, changing and deleting a role are logged with the permissions', function () {
        (new PermissionSync)->run();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/roles', ['name' => 'Writer', 'permissions' => ['stories.view']]);
        expect(lastLog()->event)->toBe('created')
            ->and(lastLog()->new_values)->toBe(['name' => 'Writer', 'permissions' => ['stories.view']])
            ->and(lastLog()->old_values)->toBeNull();

        $role = Role::findByName('Writer');
        $this->actingAs($admin)->put("/roles/{$role->id}", ['name' => 'Writer', 'permissions' => ['stories.view', 'stories.create']]);
        expect(lastLog()->event)->toBe('updated')
            ->and(lastLog()->old_values)->toBe(['permissions' => ['stories.view']])
            ->and(lastLog()->new_values)->toBe(['permissions' => ['stories.create', 'stories.view']]);

        $this->actingAs($admin)->delete("/roles/{$role->id}");
        expect(lastLog()->event)->toBe('deleted')->and(lastLog()->old_values['name'])->toBe('Writer');
    });

    test('giving someone a role is logged against that user', function () {
        (new PermissionSync)->run();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->withoutRoles()->create();
        $role = Role::findByName('Editor');

        $this->actingAs($admin)->put("/users/{$user->id}", [
            'name' => $user->name, 'email' => $user->email, 'roles' => [$role->id],
        ])->assertSessionHasNoErrors();

        $log = lastLog();

        expect($log->auditable_type)->toBe('user')
            ->and($log->auditable_id)->toBe((string) $user->id)
            ->and($log->old_values)->toBe(['roles' => []])
            ->and($log->new_values)->toBe(['roles' => ['Editor']])
            ->and($log->user_id)->toBe($admin->id);
    });

    test('exports and receipt resends are logged', function () {
        $admin = User::factory()->admin()->create();
        Donation::factory()->succeeded()->create();

        $this->actingAs($admin)->get('/donations/export?view=succeeded')->assertOk()->streamedContent();
        expect(lastLog()->event)->toBe('exported')->and(lastLog()->auditable_type)->toBe('donation')
            ->and(lastLog()->new_values)->toMatchArray(['rows' => 1]);

        $this->actingAs($admin)->get('/inquiries/export')->assertOk()->streamedContent();
        expect(lastLog()->auditable_type)->toBe('inquiry');

        $donation = Donation::first();
        $this->actingAs($admin)->post("/donations/{$donation->id}/resend")->assertRedirect();
        expect(lastLog()->event)->toBe('resent_receipt')->and(lastLog()->auditable_label)->toBe($donation->reference);
    });

    test('the retention command deletes only old entries', function () {
        Story::factory()->create();
        AuditLog::query()->update(['created_at' => now()->subDays(400)]);
        Story::factory()->create();

        $this->artisan('audit:prune')->assertSuccessful();

        expect(AuditLog::where('created_at', '<', now()->subDays(300))->count())->toBe(0)
            ->and(AuditLog::count())->toBeGreaterThan(0);

        config(['audit.retention_days' => 0]);
        $count = AuditLog::count();
        $this->artisan('audit:prune')->assertSuccessful();
        expect(AuditLog::count())->toBe($count);
    });
});

describe('the screen', function () {
    test('is for super administrators only', function () {
        Story::factory()->create();
        $log = lastLog();

        $this->get('/audit-logs')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/audit-logs')->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson("/audit-logs/{$log->id}")->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/audit-logs')->assertOk();
    });

    test('has no way to change or delete an entry', function () {
        Story::factory()->create();
        $log = lastLog();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->delete("/audit-logs/{$log->id}")->assertStatus(405);
        $this->actingAs($admin)->put("/audit-logs/{$log->id}")->assertStatus(405);
        $this->actingAs($admin)->patch("/audit-logs/{$log->id}")->assertStatus(405);
    });

    test('is paginated, newest first', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Story::factory()->count(30)->create();

        $this->get('/audit-logs')->assertInertia(fn (Assert $page) => $page
            ->component('audit-logs/index')
            ->has('logs.data', 25)
            ->where('logs.last_page', 2)
            ->where('logs.data.0.id', AuditLog::max('id')));

        $this->get('/audit-logs?page=2')->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', AuditLog::count() - 25));
    });

    test('filters by person, action, kind, text and dates', function () {
        $alice = User::factory()->admin()->create(['name' => 'Alice']);
        $bob = User::factory()->admin()->create(['name' => 'Bob']);

        $this->actingAs($alice);
        $story = Story::factory()->create(['title' => 'Alpha story']);
        $this->actingAs($bob);
        $story->update(['title' => 'Beta story']);
        Event::factory()->create(['title' => 'Gala']);

        $this->actingAs($alice);
        $count = fn (string $query) => collect($this->get("/audit-logs?{$query}")->inertiaProps('logs.data'))->count();

        expect($count("user={$bob->id}&type=story"))->toBe(1)
            ->and($count("user={$alice->id}&type=story"))->toBe(1)
            ->and($count('event=updated&type=story'))->toBe(1)
            ->and($count('search=Gala'))->toBe(1)
            ->and($count('type=event'))->toBe(1)
            ->and($count('event=created&type=story'))->toBe(1)
            ->and($count('user=none&type=story'))->toBe(0)
            ->and($count('from=2999-01-01'))->toBe(0)
            ->and($count('to=2000-01-01'))->toBe(0)
            ->and($count('from='.now()->toDateString().'&type=story'))->toBe(2);
    });

    test('the detail of a creation has new values and no old ones', function () {
        $this->actingAs(User::factory()->admin()->create());
        $story = Story::factory()->create(['title' => 'Fresh']);
        $log = AuditLog::where('auditable_type', 'story')->where('auditable_id', (string) $story->id)->first();

        $data = $this->getJson("/audit-logs/{$log->id}")->assertOk()->json('data');

        expect($data['has_old'])->toBeFalse()
            ->and($data['has_new'])->toBeTrue()
            ->and($data['event'])->toBe('created')
            ->and(collect($data['fields'])->firstWhere('field', 'title'))->toMatchArray(['new' => 'Fresh', 'old' => null, 'in_old' => false, 'in_new' => true]);
    });

    test('the detail of a change has both sides', function () {
        $story = Story::factory()->create(['title' => 'Before']);
        $this->actingAs(User::factory()->admin()->create());
        $story->update(['title' => 'After']);

        $data = $this->getJson('/audit-logs/'.lastLog()->id)->json('data');

        expect($data['has_old'])->toBeTrue()->and($data['has_new'])->toBeTrue()
            ->and($data['fields'])->toHaveCount(1)
            ->and($data['fields'][0])->toMatchArray(['field' => 'title', 'old' => 'Before', 'new' => 'After'])
            ->and($data['actor']['email'])->not->toBeNull()
            ->and($data)->toHaveKeys(['ip_address', 'user_agent', 'method', 'url']);
    });

    test('the detail of a deletion has old values and no new ones', function () {
        $story = Story::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $story->forceDelete();

        $data = $this->getJson('/audit-logs/'.lastLog()->id)->json('data');

        expect($data['has_old'])->toBeTrue()->and($data['has_new'])->toBeFalse();
    });

    test('an entry with no values at all (a sign-in) has neither side', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->post('/logout');
        $logId = lastLog()->id;

        $data = $this->actingAs(User::factory()->admin()->create())->getJson("/audit-logs/{$logId}")->json('data');

        expect($data['has_old'])->toBeFalse()->and($data['has_new'])->toBeFalse()->and($data['fields'])->toBe([]);
    });
});
