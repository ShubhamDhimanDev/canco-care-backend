<?php

use App\Jobs\RevalidateFrontend;
use App\Models\Media;
use App\Models\Story;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function dispatchedTags(): array
{
    return Queue::pushed(RevalidateFrontend::class)->map(fn (RevalidateFrontend $job) => $job->tags)->all();
}

describe('observer', function () {
    beforeEach(fn () => Queue::fake());

    test('creating a story refreshes the list and that story', function () {
        Story::factory()->create(['slug' => 'hello']);

        expect(dispatchedTags())->toBe([['stories', 'story:hello']]);
    });

    test('editing a story refreshes it again', function () {
        $story = Story::factory()->create(['slug' => 'hello']);
        Queue::fake(); // forget the create

        $story->update(['title' => 'New title']);

        expect(dispatchedTags())->toBe([['stories', 'story:hello']]);
    });

    test('renaming a slug also refreshes the old URL', function () {
        $story = Story::factory()->create(['slug' => 'old-name']);
        Queue::fake();

        $story->update(['slug' => 'new-name']);

        expect(dispatchedTags())->toBe([['stories', 'story:new-name', 'story:old-name']]);
    });

    test('deleting and restoring refresh too', function () {
        $story = Story::factory()->create(['slug' => 'hello']);
        Queue::fake();

        $story->delete();
        $story->restore();

        $tags = dispatchedTags();

        expect($tags)->toContain(['stories', 'story:hello'])
            ->and(count($tags))->toBeGreaterThanOrEqual(2);
    });
});

describe('job', function () {
    beforeEach(function () {
        config([
            'services.frontend.url' => 'https://site.example.test/',
            'services.frontend.revalidate_secret' => 'shh-secret',
        ]);
    });

    test('posts the tags to the website with the bearer secret', function () {
        Http::fake(['site.example.test/*' => Http::response(['revalidated' => ['stories']])]);

        (new RevalidateFrontend(['stories', 'story:a', 'stories']))->handle();

        Http::assertSent(fn ($request) => $request->url() === 'https://site.example.test/api/revalidate'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer shh-secret')
            && $request['tags'] === ['stories', 'story:a']);
    });

    test('fails loudly on a non-2xx answer so the queue retries', function () {
        Http::fake(['*' => Http::response('nope', 500)]);

        expect(fn () => (new RevalidateFrontend(['stories']))->handle())->toThrow(RequestException::class);
    });

    test('retries three times with growing delays', function () {
        $job = new RevalidateFrontend(['stories']);

        expect($job->tries)->toBe(3)->and($job->backoff())->toBe([10, 60, 300]);
    });

    test('does nothing when the website is not configured', function (array $config) {
        config($config);
        Http::fake();

        (new RevalidateFrontend(['stories']))->handle();

        Http::assertNothingSent();
    })->with([
        'no url' => [['services.frontend.url' => null]],
        'no secret' => [['services.frontend.revalidate_secret' => '']],
    ]);

    test('the first attempt is made at once, without a queue worker, and a failure falls back to the queue', function () {
        Queue::fake();
        Http::fake(['site.example.test/*' => Http::response('down', 503)]);

        (new RevalidateFrontend(['stories'], immediate: true))->handle();

        // It did not throw (the editor's request must not fail) and it queued a retry for the worker.
        Queue::assertPushed(RevalidateFrontend::class, fn (RevalidateFrontend $job) => $job->tags === ['stories'] && ! $job->immediate);
    });

    test('an unreachable website also falls back to the queue', function () {
        Queue::fake();
        Http::fake(fn () => throw new ConnectionException('refused'));

        (new RevalidateFrontend(['events'], immediate: true))->handle();

        Queue::assertPushed(RevalidateFrontend::class, fn (RevalidateFrontend $job) => $job->tags === ['events'] && ! $job->immediate);
    });

    test('fire() contacts the website before the request ends, with no queue involved', function () {
        Http::fake(['site.example.test/*' => Http::response([])]);

        RevalidateFrontend::fire(['stories', 'events']);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['tags'] === ['stories', 'events']);
    });

    test('changing a picture description refreshes everything that may show it', function () {
        Http::fake(['site.example.test/*' => Http::response([])]);
        $admin = User::factory()->admin()->create();
        $media = Media::create(['path' => 'media/a.webp', 'original_name' => 'a.webp', 'mime' => 'image/webp', 'size_bytes' => 10, 'width' => 10, 'height' => 10]);

        $this->actingAs($admin)->patch("/media/{$media->id}", ['alt' => 'A new description'])->assertRedirect();

        Http::assertSent(fn ($request) => $request['tags'] === ['stories', 'events', 'settings']);
    });

    test('saving a story reaches the website end to end', function () {
        Http::fake(['site.example.test/*' => Http::response([])]);

        Story::factory()->create(['slug' => 'live-now']); // sync queue in tests

        Http::assertSent(fn ($request) => $request['tags'] === ['stories', 'story:live-now']);
    });
});

describe('scheduled publishing', function () {
    beforeEach(fn () => Queue::fake());

    test('refreshes stories that just went live, and only those', function () {
        Story::factory()->create(['slug' => 'just-live', 'published_at' => now()->subSeconds(30)]);
        Story::factory()->create(['slug' => 'long-ago', 'published_at' => now()->subDay()]);
        Story::factory()->scheduled()->create(['slug' => 'still-future']);
        Story::factory()->draft()->create(['slug' => 'draft']);
        Queue::fake(); // ignore observer dispatches from the setup

        $this->artisan('content:revalidate-due')->assertSuccessful();

        expect(dispatchedTags())->toBe([['stories', 'story:just-live']]);
    });

    test('stays quiet when nothing just went live', function () {
        Story::factory()->create(['published_at' => now()->subDay()]);
        Queue::fake();

        $this->artisan('content:revalidate-due')->assertSuccessful();

        Queue::assertNothingPushed();
    });

    test('is scheduled to run every minute', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'content:revalidate-due'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('* * * * *')
            ->and($event->withoutOverlapping)->toBeTrue();
    });
});
