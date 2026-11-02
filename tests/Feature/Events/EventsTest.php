<?php

use App\Jobs\RevalidateFrontend;
use App\Models\Event;
use App\Models\Media;
use App\Models\SlugRedirect;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function eventInput(array $overrides = []): array
{
    return [
        'title' => 'Spring Fundraiser',
        'summary' => 'An evening of food and music for a good cause.',
        'description' => '<p>Join us!</p>',
        'timezone' => 'America/Vancouver',
        'starts_at_local' => '2027-04-10T18:00',
        'ends_at_local' => '2027-04-10T21:00',
        'location_name' => 'Community Hall',
        'city' => 'Kelowna',
        'province' => 'bc',
        'postal_code' => 'v1x 2a1',
        'status' => 'published',
        ...$overrides,
    ];
}

function staff(): User
{
    return User::factory()->create();
}

describe('admin access', function () {
    test('guests are sent to the login page', function () {
        $this->get(route('events.index'))->assertRedirect();
        $this->post(route('events.store'), eventInput())->assertRedirect();
    });

    test('an editor can open the list, the form and an existing event', function () {
        $event = Event::factory()->create();

        $this->actingAs(staff())->get(route('events.index'))->assertOk()->assertInertia(fn ($page) => $page->component('events/index')->has('events.data', 1));
        $this->actingAs(staff())->get(route('events.create'))->assertOk()->assertInertia(fn ($page) => $page->component('events/create')->has('timezones'));
        $this->actingAs(staff())->get(route('events.edit', $event))->assertOk()->assertInertia(fn ($page) => $page->component('events/edit')->where('event.title', $event->title));
    });

    test('only an admin can delete permanently', function () {
        $event = Event::factory()->create();
        $event->delete();

        $this->actingAs(staff())->delete(route('events.force-delete', $event))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->delete(route('events.force-delete', $event))->assertRedirect();

        expect(Event::withTrashed()->count())->toBe(0);
    });
});

describe('saving', function () {
    test('stores the time as UTC converted from the event time zone', function () {
        $this->actingAs(staff())->post(route('events.store'), eventInput())->assertSessionHasNoErrors();

        $event = Event::firstOrFail();

        // 18:00 on 10 April 2027 in Vancouver is daylight time (UTC-7).
        expect($event->starts_at->utc()->format('Y-m-d H:i'))->toBe('2027-04-11 01:00')
            ->and($event->ends_at?->utc()->format('Y-m-d H:i'))->toBe('2027-04-11 04:00')
            ->and($event->timezone)->toBe('America/Vancouver')
            ->and($event->province)->toBe('BC')
            ->and($event->postal_code)->toBe('V1X 2A1')
            ->and($event->slug)->toBe('spring-fundraiser');
    });

    test('the edit form hands the time back in the event time zone, not the viewer\'s', function () {
        $user = staff();
        $this->actingAs($user)->post(route('events.store'), eventInput(['timezone' => 'America/Toronto']));
        $event = Event::firstOrFail();

        $this->actingAs($user)->get(route('events.edit', $event))
            ->assertInertia(fn ($page) => $page->where('event.starts_at_local', '2027-04-10T18:00')->where('event.timezone', 'America/Toronto'));
    });

    test('an all-day event spans its day in the event time zone', function () {
        $this->actingAs(staff())->post(route('events.store'), eventInput([
            'is_all_day' => '1', 'starts_at_local' => '2027-06-01', 'ends_at_local' => '2027-06-02',
        ]))->assertSessionHasNoErrors();

        $event = Event::firstOrFail();

        expect($event->starts_at->setTimezone('America/Vancouver')->format('Y-m-d H:i'))->toBe('2027-06-01 00:00')
            ->and($event->ends_at?->setTimezone('America/Vancouver')->format('Y-m-d H:i'))->toBe('2027-06-02 23:59');
    });

    test('the description is sanitised', function () {
        $this->actingAs(staff())->post(route('events.store'), eventInput(['description' => '<p>Hi</p><script>alert(1)</script>']));

        expect(Event::firstOrFail()->description)->not->toContain('script')->toContain('Hi');
    });

    test('flags save and are turned off by leaving them out', function () {
        $user = staff();
        $this->actingAs($user)->post(route('events.store'), eventInput(['is_featured' => '1', 'show_in_donation_form' => '1']));
        $event = Event::firstOrFail();

        expect($event->is_featured)->toBeTrue()->and($event->show_in_donation_form)->toBeTrue();

        $this->actingAs($user)->put(route('events.update', $event), eventInput())->assertSessionHasNoErrors();

        expect($event->fresh()->is_featured)->toBeFalse()->and($event->fresh()->show_in_donation_form)->toBeFalse();
    });

    test('a library image is attached and needs alt text', function () {
        $media = Media::create(['path' => 'media/gala.webp', 'thumb_path' => null, 'original_name' => 'gala.jpg', 'mime' => 'image/webp', 'size_bytes' => 10, 'width' => 1600, 'height' => 900]);
        $user = staff();

        $this->actingAs($user)->post(route('events.store'), eventInput(['image_media_id' => $media->id]))->assertSessionHasErrors('image_alt');

        $this->actingAs($user)->post(route('events.store'), eventInput(['image_media_id' => $media->id, 'image_alt' => 'The gala']))->assertSessionHasNoErrors();

        expect(Event::firstOrFail()->image())->toMatchArray(['alt' => 'The gala', 'width' => 1600]);
    });

    test('renaming a published event keeps the old address working', function () {
        $user = staff();
        $this->actingAs($user)->post(route('events.store'), eventInput());
        $event = Event::firstOrFail();

        $this->actingAs($user)->put(route('events.update', $event), eventInput(['slug' => 'spring-gala']))->assertSessionHasNoErrors();

        expect(SlugRedirect::where('old_slug', 'spring-fundraiser')->where('redirectable_type', 'event')->exists())->toBeTrue();
        $this->getJson('/api/v1/events/spring-fundraiser')->assertStatus(301)->assertJson(['redirect_to' => 'spring-gala']);
    });

    test('dispatches a website refresh', function () {
        $this->actingAs(staff())->post(route('events.store'), eventInput());

        Queue::assertPushed(RevalidateFrontend::class, fn (RevalidateFrontend $job) => in_array('events', $job->tags, true) && in_array('event:spring-fundraiser', $job->tags, true));
    });
});

describe('validation', function () {
    test('required fields', function () {
        $this->actingAs(staff())->post(route('events.store'), ['status' => 'published'])
            ->assertSessionHasErrors(['title', 'summary', 'starts_at_local', 'timezone']);
    });

    test('an event needs a place or an online link', function () {
        $this->actingAs(staff())->post(route('events.store'), eventInput(['location_name' => null, 'city' => null, 'online_url' => null]))
            ->assertSessionHasErrors('location_name');

        $this->actingAs(staff())->post(route('events.store'), eventInput(['location_name' => null, 'city' => null, 'online_url' => 'https://zoom.us/j/1']))
            ->assertSessionHasNoErrors();
    });

    test('it cannot end before it starts', function () {
        $this->actingAs(staff())->post(route('events.store'), eventInput(['ends_at_local' => '2027-04-10T17:00']))
            ->assertSessionHasErrors('ends_at_local');
    });

    test('bad values are rejected', function (string $field, mixed $value) {
        $this->actingAs(staff())->post(route('events.store'), eventInput([$field => $value]))->assertSessionHasErrors($field);
    })->with([
        'time zone' => ['timezone', 'Mars/Olympus'],
        'postal code' => ['postal_code', '12345'],
        'province' => ['province', 'British Columbia'],
        'registration link' => ['registration_url', 'javascript:alert(1)'],
        'online link' => ['online_url', 'ftp://x.y'],
        'start' => ['starts_at_local', 'next friday'],
    ]);

    test('a slug already used by another event is refused', function () {
        Event::factory()->create(['slug' => 'taken']);

        $this->actingAs(staff())->post(route('events.store'), eventInput(['slug' => 'taken']))->assertSessionHasErrors('slug');
    });

    test('the generated slug is made unique', function () {
        Event::factory()->create(['slug' => 'spring-fundraiser']);

        $this->actingAs(staff())->post(route('events.store'), eventInput())->assertSessionHasNoErrors();

        expect(Event::where('title', 'Spring Fundraiser')->firstOrFail()->slug)->toBe('spring-fundraiser-2');
    });
});

describe('trash', function () {
    test('can be trashed and restored', function () {
        $user = staff();
        $event = Event::factory()->create();

        $this->actingAs($user)->delete(route('events.destroy', $event))->assertRedirect();
        expect(Event::count())->toBe(0);

        $this->actingAs($user)->patch(route('events.restore', $event))->assertRedirect();
        expect(Event::count())->toBe(1);
    });
});

describe('public API visibility', function () {
    test('lists published upcoming events soonest first and never drafts, scheduled or deleted ones', function () {
        Event::factory()->create(['slug' => 'later', 'starts_at' => now()->addDays(30), 'ends_at' => null]);
        Event::factory()->create(['slug' => 'soon', 'starts_at' => now()->addDays(2), 'ends_at' => null]);
        Event::factory()->draft()->create(['slug' => 'draft']);
        Event::factory()->scheduled()->create(['slug' => 'scheduled']);
        Event::factory()->create(['slug' => 'trashed'])->delete();
        Event::factory()->past()->create(['slug' => 'past']);

        $this->getJson('/api/v1/events')->assertOk()->assertJsonPath('data.*.slug', ['soon', 'later']);
    });

    test('scope=past lists finished events newest first', function () {
        Event::factory()->past()->create(['slug' => 'old', 'starts_at' => now()->subDays(40), 'ends_at' => null]);
        Event::factory()->past()->create(['slug' => 'recent', 'starts_at' => now()->subDays(5), 'ends_at' => null]);
        Event::factory()->create(['slug' => 'future']);

        $this->getJson('/api/v1/events?scope=past')->assertJsonPath('data.*.slug', ['recent', 'old']);
        $this->getJson('/api/v1/events?scope=all')->assertJsonCount(3, 'data');
    });

    test('an event in progress still counts as upcoming', function () {
        Event::factory()->create(['slug' => 'now', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);

        $this->getJson('/api/v1/events')->assertJsonPath('data.*.slug', ['now']);
    });

    test('featured events lead the upcoming list only when asked', function () {
        Event::factory()->create(['slug' => 'plain', 'starts_at' => now()->addDays(1), 'ends_at' => null]);
        Event::factory()->create(['slug' => 'star', 'is_featured' => true, 'starts_at' => now()->addDays(9), 'ends_at' => null]);

        $this->getJson('/api/v1/events')->assertJsonPath('data.*.slug', ['plain', 'star']);
        $this->getJson('/api/v1/events?featured_first=1')->assertJsonPath('data.*.slug', ['star', 'plain']);
    });

    test('a cancelled event stays visible, flagged', function () {
        Event::factory()->cancelled()->create(['slug' => 'called-off']);

        $this->getJson('/api/v1/events/called-off')->assertOk()->assertJsonPath('data.status', 'cancelled');
    });

    test('the detail has the full documented shape and 404s for hidden events', function () {
        Event::factory()->create(['slug' => 'gala', 'online_url' => 'https://zoom.us/j/1', 'address_line1' => '1 Main St', 'postal_code' => 'V1X 2A1']);
        Event::factory()->draft()->create(['slug' => 'secret']);

        $data = $this->getJson('/api/v1/events/gala')->assertOk()->json('data');

        expect(array_keys($data))->toBe([
            'slug', 'title', 'summary', 'image', 'starts_at', 'ends_at', 'timezone', 'is_all_day', 'location_name', 'city', 'province',
            'is_online', 'cost_note', 'status', 'is_featured',
            'description', 'address', 'online_url', 'registration_url', 'meta_title', 'meta_description', 'updated_at',
        ])->and($data['is_online'])->toBeTrue()->and($data['address']['line1'])->toBe('1 Main St');

        $this->getJson('/api/v1/events/secret')->assertNotFound();
        $this->getJson('/api/v1/events/nope')->assertNotFound();
    });

    test('an old address of a hidden event does not redirect', function () {
        $event = Event::factory()->draft()->create(['slug' => 'new-name']);
        SlugRedirect::create(['redirectable_type' => 'event', 'redirectable_id' => $event->id, 'old_slug' => 'old-name']);

        $this->getJson('/api/v1/events/old-name')->assertNotFound();
    });
});
