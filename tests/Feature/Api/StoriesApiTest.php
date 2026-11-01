<?php

use App\Models\SlugRedirect;
use App\Models\Story;
use Illuminate\Support\Facades\Storage;

const CARD_KEYS = ['slug', 'title', 'excerpt', 'image', 'published_at'];

const STORY_KEYS = [...CARD_KEYS, 'body', 'author_name', 'meta_title', 'meta_description', 'updated_at', 'prev', 'next'];

function publishedAt(string $iso): array
{
    return ['status' => 'published', 'published_at' => $iso];
}

describe('GET /api/v1/stories', function () {
    test('returns public stories newest first, breaking ties by id', function () {
        $old = Story::factory()->create(['slug' => 'old', ...publishedAt('2026-01-01 10:00:00')]);
        $tieA = Story::factory()->create(['slug' => 'tie-a', ...publishedAt('2026-02-01 10:00:00')]);
        $tieB = Story::factory()->create(['slug' => 'tie-b', ...publishedAt('2026-02-01 10:00:00')]);
        $new = Story::factory()->create(['slug' => 'new', ...publishedAt('2026-03-01 10:00:00')]);

        $this->getJson('/api/v1/stories')
            ->assertOk()
            ->assertJsonPath('data.*.slug', ['new', 'tie-b', 'tie-a', 'old']);
    });

    test('each card has exactly the documented keys', function () {
        Story::factory()->create();

        $card = $this->getJson('/api/v1/stories')->json('data.0');

        expect(array_keys($card))->toBe(CARD_KEYS);
    });

    test('never exposes drafts, scheduled or deleted stories', function () {
        Story::factory()->create(['slug' => 'live']);
        Story::factory()->draft()->create(['slug' => 'draft']);
        Story::factory()->scheduled()->create(['slug' => 'later']);
        Story::factory()->create(['slug' => 'gone'])->delete();

        $this->getJson('/api/v1/stories')->assertJsonPath('data.*.slug', ['live']);
    });

    test('paginates with a default of 9 and a ceiling of 24', function () {
        Story::factory()->count(30)->create();

        $default = $this->getJson('/api/v1/stories');
        $default->assertJsonCount(9, 'data')
            ->assertJsonPath('meta.per_page', 9)
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 4)
            ->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next']]);

        $this->getJson('/api/v1/stories?per_page=100')->assertJsonCount(24, 'data');
        $this->getJson('/api/v1/stories?per_page=0')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/stories?per_page=abc')->assertJsonCount(9, 'data');
        $this->getJson('/api/v1/stories?per_page=9&page=4')->assertJsonCount(3, 'data');
    });

    test('is an empty list, not an error, when there are no stories', function () {
        $this->getJson('/api/v1/stories')->assertOk()->assertExactJson([
            'data' => [],
            'links' => ['first' => url('/api/v1/stories?page=1'), 'last' => url('/api/v1/stories?page=1'), 'prev' => null, 'next' => null],
            'meta' => json_decode($this->getJson('/api/v1/stories')->getContent(), true)['meta'],
        ]);
    });

    test('image is an object with url, alt and dimensions, or null', function () {
        config(['app.url' => 'https://admin.example.test']);
        config(['filesystems.disks.public.url' => 'https://admin.example.test/storage']);

        Story::factory()->create(['slug' => 'with-image', 'featured_image_path' => 'stories/a.jpg', 'featured_image_alt' => 'A photo', 'featured_image_width' => 300, 'featured_image_height' => 200, ...publishedAt('2026-02-01 00:00:00')]);
        Story::factory()->create(['slug' => 'no-image', ...publishedAt('2026-01-01 00:00:00')]);

        $data = $this->getJson('/api/v1/stories')->json('data');

        expect($data[0]['image'])->toBe(['url' => 'https://admin.example.test/storage/stories/a.jpg', 'alt' => 'A photo', 'width' => 300, 'height' => 200])
            ->and($data[1]['image'])->toBeNull();
    });

    test('published_at is ISO-8601 in UTC', function () {
        Story::factory()->create(publishedAt('2023-04-25 07:21:55'));

        expect($this->getJson('/api/v1/stories')->json('data.0.published_at'))->toBe('2023-04-25T07:21:55+00:00');
    });

    test('falls back to a generated excerpt when none is stored', function () {
        Story::factory()->create(['excerpt' => null, 'body' => '<p>Hello<br>world.</p>']);

        expect($this->getJson('/api/v1/stories')->json('data.0.excerpt'))->toBe('Hello world.');
    });
});

describe('GET /api/v1/stories/{slug}', function () {
    test('returns the full story with exactly the documented keys', function () {
        Story::factory()->create(['slug' => 'hello', 'body' => '<p>Body</p>', 'author_name' => 'Ada', 'meta_title' => 'T', 'meta_description' => 'D']);

        $response = $this->getJson('/api/v1/stories/hello')->assertOk();

        expect(array_keys($response->json('data')))->toBe(STORY_KEYS)
            ->and($response->json('data.body'))->toBe('<p>Body</p>')
            ->and($response->json('data.author_name'))->toBe('Ada')
            ->and($response->json('data.meta_title'))->toBe('T');
    });

    test('links to the previous (older) and next (newer) public story', function () {
        Story::factory()->create(['slug' => 'a', 'title' => 'A', ...publishedAt('2026-01-01 00:00:00')]);
        Story::factory()->create(['slug' => 'b', 'title' => 'B', ...publishedAt('2026-02-01 00:00:00')]);
        Story::factory()->create(['slug' => 'c', 'title' => 'C', ...publishedAt('2026-03-01 00:00:00')]);
        Story::factory()->draft()->create(['slug' => 'hidden-draft']);

        $middle = $this->getJson('/api/v1/stories/b')->json('data');
        expect($middle['prev'])->toBe(['slug' => 'a', 'title' => 'A'])
            ->and($middle['next'])->toBe(['slug' => 'c', 'title' => 'C']);

        $oldest = $this->getJson('/api/v1/stories/a')->json('data');
        expect($oldest['prev'])->toBeNull()
            ->and($oldest['next']['slug'])->toBe('b');

        $newest = $this->getJson('/api/v1/stories/c')->json('data');
        expect($newest['next'])->toBeNull()
            ->and($newest['prev']['slug'])->toBe('b');
    });

    test('prev/next skip unpublished stories', function () {
        Story::factory()->create(['slug' => 'a', ...publishedAt('2026-01-01 00:00:00')]);
        Story::factory()->draft()->create(['slug' => 'draft']);
        Story::factory()->scheduled()->create(['slug' => 'later']);

        $a = $this->getJson('/api/v1/stories/a')->json('data');

        expect($a['prev'])->toBeNull()->and($a['next'])->toBeNull();
    });

    test('404s with a JSON message for unknown, draft, scheduled and deleted stories', function (callable $make) {
        $make();

        $this->getJson('/api/v1/stories/target')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    })->with([
        'unknown' => [fn () => null],
        'draft' => [fn () => Story::factory()->draft()->create(['slug' => 'target'])],
        'scheduled' => [fn () => Story::factory()->scheduled()->create(['slug' => 'target'])],
        'deleted' => [fn () => Story::factory()->create(['slug' => 'target'])->delete()],
    ]);

    test('serves slugs with a trailing hyphen, as the legacy URLs have', function () {
        Story::factory()->create(['slug' => 'canco-delivering-wishes-']);

        $this->getJson('/api/v1/stories/canco-delivering-wishes-')->assertOk()->assertJsonPath('data.slug', 'canco-delivering-wishes-');
    });

    test('rejects slugs with unexpected characters', function () {
        $this->getJson('/api/v1/stories/Not_A.Slug')->assertNotFound();
    });

    test('answers 301 with the new slug when an old slug is requested', function () {
        $story = Story::factory()->create(['slug' => 'new-name']);
        SlugRedirect::create(['redirectable_type' => 'story', 'redirectable_id' => $story->id, 'old_slug' => 'old-name']);

        $this->getJson('/api/v1/stories/old-name')
            ->assertStatus(301)
            ->assertExactJson(['redirect_to' => 'new-name']);
    });

    test('does not redirect to a story that is not public', function () {
        $story = Story::factory()->draft()->create(['slug' => 'new-name']);
        SlugRedirect::create(['redirectable_type' => 'story', 'redirectable_id' => $story->id, 'old_slug' => 'old-name']);

        $this->getJson('/api/v1/stories/old-name')->assertNotFound();
    });

    test('a live slug wins over a stale redirect with the same name', function () {
        $other = Story::factory()->create(['slug' => 'elsewhere']);
        SlugRedirect::create(['redirectable_type' => 'story', 'redirectable_id' => $other->id, 'old_slug' => 'taken']);
        Story::factory()->create(['slug' => 'taken']);

        $this->getJson('/api/v1/stories/taken')->assertOk()->assertJsonPath('data.slug', 'taken');
    });
});

describe('HTTP behaviour', function () {
    test('reads need no API key', function () {
        Story::factory()->create();

        $this->getJson('/api/v1/stories')->assertOk();
    });

    test('responses are cacheable with stale-while-revalidate and an ETag', function () {
        Story::factory()->create(['slug' => 'cached']);

        foreach (['/api/v1/stories', '/api/v1/stories/cached'] as $url) {
            $response = $this->getJson($url)->assertOk();

            expect($response->headers->get('Cache-Control'))
                ->toContain('public')
                ->toContain('max-age=60')
                ->toContain('stale-while-revalidate=300')
                ->and($response->headers->get('ETag'))->not->toBeNull();
        }
    });

    test('a matching ETag gets 304 Not Modified', function () {
        Story::factory()->create();

        $etag = $this->getJson('/api/v1/stories')->headers->get('ETag');

        $this->getJson('/api/v1/stories', ['If-None-Match' => $etag])->assertStatus(304);
    });

    test('unknown API routes return a JSON 404, never HTML', function () {
        $this->get('/api/v1/nothing-here', ['Accept' => 'text/html'])
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    });

    test('works for the stories imported from the legacy site', function () {
        Storage::fake('public');
        $this->artisan('stories:import-legacy')->assertSuccessful();

        $slugs = $this->getJson('/api/v1/stories?per_page=24')->assertOk()->json('data.*.slug');

        expect($slugs)->toHaveCount(7);

        foreach ($slugs as $slug) {
            $this->getJson("/api/v1/stories/{$slug}")->assertOk()->assertJsonPath('data.image.alt', fn ($alt) => filled($alt));
        }
    });
});
