<?php

use App\Models\Event;
use App\Models\User;
use App\Support\ZoneLabel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
| BC moved to permanent UTC-7 on 1 Nov 2026. The website must print the organiser's wall-clock time, not
| a conversion that depends on the server's time-zone database (docs/plan/14 #5). These tests avoid
| depending on the tz data of the machine running them: they only use dates whose offset has not changed.
*/

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function timesInput(array $overrides = []): array
{
    return [
        'title' => 'Winter Gala',
        'summary' => 'An evening for a good cause.',
        'description' => '<p>Join us!</p>',
        'timezone' => 'America/Vancouver',
        'starts_at_local' => '2026-12-05T18:00',
        'ends_at_local' => '2026-12-05T21:00',
        'location_name' => 'Community Hall',
        'city' => 'Kelowna',
        'status' => 'published',
        ...$overrides,
    ];
}

test('the organisers time is stored as typed and served without an offset', function () {
    $this->actingAs(User::factory()->create())->post(route('events.store'), timesInput(['slug' => 'winter-gala']))
        ->assertSessionHasNoErrors();

    $event = Event::firstOrFail();

    expect($event->starts_local)->toBe('2026-12-05 18:00:00')
        ->and($event->ends_local)->toBe('2026-12-05 21:00:00');

    $this->getJson('/api/v1/events/winter-gala')->assertOk()
        ->assertJsonPath('data.starts_local', '2026-12-05T18:00:00')
        ->assertJsonPath('data.ends_local', '2026-12-05T21:00:00')
        ->assertJsonPath('data.timezone_label', 'PT');
});

test('an all-day event keeps its calendar day whatever the server time-zone data says', function () {
    $this->actingAs(User::factory()->create())->post(route('events.store'), timesInput([
        'slug' => 'community-picnic', 'is_all_day' => '1', 'starts_at_local' => '2026-12-01', 'ends_at_local' => '',
    ]))->assertSessionHasNoErrors();

    $this->getJson('/api/v1/events/community-picnic')->assertOk()
        ->assertJsonPath('data.starts_local', '2026-12-01T00:00:00')
        ->assertJsonPath('data.ends_local', null)
        ->assertJsonPath('data.is_all_day', true);
});

test('the edit form shows the stored wall-clock time even if the UTC instant is an hour out', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('events.store'), timesInput());
    $event = Event::firstOrFail();
    $event->forceFill(['starts_at' => '2026-12-06 03:00:00'])->saveQuietly(); // as an old tz database would store it

    $this->actingAs($user)->get(route('events.edit', $event))
        ->assertInertia(fn ($page) => $page->where('event.starts_at_local', '2026-12-05T18:00'));
});

test('rows without wall-clock columns fall back to converting the UTC instant', function () {
    $event = Event::factory()->create(['starts_at' => '2027-04-11 01:00:00', 'ends_at' => null]);
    $event->forceFill(['starts_local' => null, 'ends_local' => null])->saveQuietly();

    expect($event->fresh()->startsLocal())->toBe('2027-04-10 18:00:00');
});

test('anything that sets only the UTC instant still gets its wall-clock time', function () {
    $event = Event::factory()->create(['starts_at' => '2027-04-11 01:00:00', 'ends_at' => '2027-04-11 04:00:00']);

    expect($event->starts_local)->toBe('2027-04-10 18:00:00')
        ->and($event->ends_local)->toBe('2027-04-10 21:00:00');
});

test('events:resync-utc re-derives the UTC instants from the wall-clock time', function () {
    $event = Event::factory()->create(['starts_at' => '2027-04-11 01:00:00', 'ends_at' => '2027-04-11 04:00:00']);
    $event->forceFill(['starts_at' => '2027-04-11 02:00:00'])->saveQuietly(); // an hour out

    $this->artisan('events:resync-utc --dry-run')->assertSuccessful();
    expect($event->fresh()->starts_at->utc()->format('H:i'))->toBe('02:00');

    $this->artisan('events:resync-utc')->assertSuccessful();
    expect($event->fresh()->starts_at->utc()->format('Y-m-d H:i'))->toBe('2027-04-11 01:00');
});

test('Canadian zones get a generic label, others what PHP prints', function () {
    $at = CarbonImmutable::parse('2027-04-11 01:00:00', 'UTC');

    expect(ZoneLabel::for('America/Vancouver', $at))->toBe('PT')
        ->and(ZoneLabel::for('America/Toronto', $at))->toBe('ET')
        ->and(ZoneLabel::for('UTC', $at))->toBe('UTC');
});
