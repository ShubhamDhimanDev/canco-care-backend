<?php

use App\Jobs\RevalidateFrontend;
use App\Models\SlugRedirect;
use App\Models\Story;
use App\Models\User;
use App\Services\StoryImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Valid form input for a published story; override what a test cares about.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function storyInput(array $overrides = []): array
{
    return [
        'title' => 'Spring Fundraiser',
        'body' => '<p>Join us for the fundraiser.</p>',
        'status' => 'published',
        ...$overrides,
    ];
}

function editor(): User
{
    return User::factory()->create();
}

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
});

describe('access', function () {
    test('guests are sent to the login page', function (string $method, string $route) {
        $story = Story::factory()->create();

        $this->{$method}(route($route, ['story' => $story]))->assertRedirect(route('login'));
    })->with([
        ['get', 'stories.index'],
        ['get', 'stories.create'],
        ['post', 'stories.store'],
        ['get', 'stories.edit'],
        ['put', 'stories.update'],
        ['delete', 'stories.destroy'],
        ['patch', 'stories.restore'],
        ['delete', 'stories.force-delete'],
    ]);

    test('unverified users are held back too', function () {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('stories.index'))
            ->assertRedirect(route('verification.notice'));
    });

    test('editors can use the story screens', function () {
        $this->actingAs(editor())->get(route('stories.index'))->assertOk();
        $this->actingAs(editor())->get(route('stories.create'))->assertOk();
    });
});

describe('listing', function () {
    test('shows stories with their state, newest edit first', function () {
        Story::factory()->create(['title' => 'Old', 'updated_at' => now()->subDays(2)]);
        Story::factory()->draft()->create(['title' => 'Draft', 'updated_at' => now()->subDay()]);
        Story::factory()->scheduled()->create(['title' => 'Later', 'updated_at' => now()]);

        $response = $this->actingAs(editor())->get(route('stories.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('stories/index')
            ->where('counts', ['all' => 3, 'published' => 1, 'scheduled' => 1, 'draft' => 1, 'trashed' => 0])
            ->where('filters', ['status' => 'all', 'search' => ''])
        );

        $rows = collect($response->inertiaProps('stories.data'));

        expect($rows->pluck('title')->all())->toBe(['Later', 'Draft', 'Old'])
            ->and($rows->pluck('state')->all())->toBe(['scheduled', 'draft', 'published']);
    });

    test('filters by state', function (string $status, array $titles) {
        Story::factory()->create(['title' => 'Live']);
        Story::factory()->draft()->create(['title' => 'Draft']);
        Story::factory()->scheduled()->create(['title' => 'Later']);
        Story::factory()->create(['title' => 'Gone'])->delete();

        $response = $this->actingAs(editor())->get(route('stories.index', ['status' => $status]));

        $found = collect($response->inertiaProps('stories.data'))->pluck('title')->sort()->values()->all();

        expect($found)->toBe(collect($titles)->sort()->values()->all());
    })->with([
        'published' => ['published', ['Live']],
        'draft' => ['draft', ['Draft']],
        'scheduled' => ['scheduled', ['Later']],
        'trashed' => ['trashed', ['Gone']],
        'all hides trashed' => ['all', ['Live', 'Draft', 'Later']],
        'unknown falls back to all' => ['bogus', ['Live', 'Draft', 'Later']],
    ]);

    test('searches title and slug', function () {
        Story::factory()->create(['title' => 'Spring Fundraiser', 'slug' => 'spring-fundraiser']);
        Story::factory()->create(['title' => 'Winter Wishes', 'slug' => 'cold-season']);

        $user = editor();

        $byTitle = $this->actingAs($user)->get(route('stories.index', ['search' => 'fundraiser']));
        $byAddress = $this->actingAs($user)->get(route('stories.index', ['search' => 'cold']));

        expect(collect($byTitle->inertiaProps('stories.data'))->pluck('title')->all())->toBe(['Spring Fundraiser'])
            ->and(collect($byAddress->inertiaProps('stories.data'))->pluck('title')->all())->toBe(['Winter Wishes']);
    });

    test('paginates 25 per page', function () {
        Story::factory()->count(30)->create();

        $this->actingAs(editor())->get(route('stories.index'))
            ->assertInertia(fn (Assert $page) => $page->has('stories.data', 25)->where('stories.total', 30)->where('stories.last_page', 2));
    });

    test('only administrators are offered permanent deletion', function () {
        $this->actingAs(editor())->get(route('stories.index'))->assertInertia(fn (Assert $page) => $page->where('can.forceDelete', false));
        $this->actingAs(User::factory()->admin()->create())->get(route('stories.index'))->assertInertia(fn (Assert $page) => $page->where('can.forceDelete', true));
    });
});

describe('creating', function () {
    test('stores a story, generating the address from the title', function () {
        $user = editor();

        $this->actingAs($user)->post(route('stories.store'), storyInput())->assertRedirect();

        $story = Story::firstOrFail();

        expect($story->slug)->toBe('spring-fundraiser')
            ->and($story->title)->toBe('Spring Fundraiser')
            ->and($story->created_by)->toBe($user->id)
            ->and($story->updated_by)->toBe($user->id)
            ->and($story->excerpt)->toBeNull();

        $this->get(route('stories.edit', $story))->assertOk();
    });

    test('adds a number when the address is already taken', function () {
        Story::factory()->create(['slug' => 'spring-fundraiser']);

        $this->actingAs(editor())->post(route('stories.store'), storyInput());

        expect(Story::pluck('slug')->all())->toContain('spring-fundraiser-2');
    });

    test('does not hand out the old address of another story', function () {
        $other = Story::factory()->create(['slug' => 'renamed']);
        SlugRedirect::create(['redirectable_type' => 'story', 'redirectable_id' => $other->id, 'old_slug' => 'spring-fundraiser']);

        $this->actingAs(editor())->post(route('stories.store'), storyInput());

        expect(Story::where('title', 'Spring Fundraiser')->value('slug'))->toBe('spring-fundraiser-2');
    });

    test('publishing without a date publishes now', function () {
        $this->actingAs(editor())->post(route('stories.store'), storyInput());

        $story = Story::firstOrFail();

        expect($story->publicationState())->toBe('published')
            ->and($story->published_at?->diffInSeconds(now(), true))->toBeLessThan(5);
    });

    test('a future date schedules the story', function () {
        $this->actingAs(editor())->post(route('stories.store'), storyInput(['published_at' => now()->addWeek()->toIso8601String()]));

        $story = Story::firstOrFail();

        expect($story->publicationState())->toBe('scheduled')
            ->and(Story::published()->count())->toBe(0);
    });

    test('a draft stays out of the public API', function () {
        $this->actingAs(editor())->post(route('stories.store'), storyInput(['status' => 'draft']));

        expect(Story::firstOrFail()->publicationState())->toBe('draft');
        $this->getJson('/api/v1/stories')->assertJsonCount(0, 'data');
    });

    test('an explicit address is used as given', function () {
        $this->actingAs(editor())->post(route('stories.store'), storyInput(['slug' => 'my-own-address']));

        expect(Story::firstOrFail()->slug)->toBe('my-own-address');
    });

    test('tells the website to refresh', function () {
        $this->actingAs(editor())->post(route('stories.store'), storyInput());

        Queue::assertPushed(RevalidateFrontend::class, fn (RevalidateFrontend $job) => $job->tags === ['stories', 'story:spring-fundraiser']);
    });
});

describe('validation', function () {
    test('rejects bad input', function (array $overrides, string $field) {
        $this->actingAs(editor())
            ->from(route('stories.create'))
            ->post(route('stories.store'), storyInput($overrides))
            ->assertSessionHasErrors($field);

        expect(Story::count())->toBe(0);
    })->with([
        'no title' => [['title' => ''], 'title'],
        'title too long' => [['title' => str_repeat('a', 201)], 'title'],
        'no body' => [['body' => ''], 'body'],
        'empty editor markup' => [['body' => '<p></p>'], 'body'],
        'only tags' => [['body' => '<p> <br> </p>'], 'body'],
        'bad status' => [['status' => 'archived'], 'status'],
        'bad date' => [['published_at' => 'not a date'], 'published_at'],
        'excerpt too long' => [['excerpt' => str_repeat('a', 301)], 'excerpt'],
        'meta description too long' => [['meta_description' => str_repeat('a', 256)], 'meta_description'],
        'uppercase address' => [['slug' => 'Spring-Fundraiser'], 'slug'],
        'spaces in address' => [['slug' => 'spring fundraiser'], 'slug'],
        'double hyphen' => [['slug' => 'spring--fundraiser'], 'slug'],
        'trailing hyphen on a new address' => [['slug' => 'spring-'], 'slug'],
    ]);

    test('rejects an address already used by another story', function () {
        Story::factory()->create(['slug' => 'taken']);

        $this->actingAs(editor())->post(route('stories.store'), storyInput(['slug' => 'taken']))->assertSessionHasErrors('slug');
    });

    test('rejects the old address of another story', function () {
        $other = Story::factory()->create();
        SlugRedirect::create(['redirectable_type' => 'story', 'redirectable_id' => $other->id, 'old_slug' => 'was-here']);

        $this->actingAs(editor())->post(route('stories.store'), storyInput(['slug' => 'was-here']))->assertSessionHasErrors('slug');
    });
});

describe('sanitising', function () {
    test('strips scripts and event handlers from the body before saving', function () {
        $dirty = '<p onclick="steal()">Hello</p><script>alert(1)</script><a href="javascript:alert(1)">x</a><iframe src="https://evil.test"></iframe>';

        $this->actingAs(editor())->post(route('stories.store'), storyInput(['body' => $dirty]));

        $body = Story::firstOrFail()->body;

        expect($body)->toContain('Hello')
            ->not->toContain('<script')
            ->not->toContain('onclick')
            ->not->toContain('javascript:')
            ->not->toContain('<iframe');
    });

    test('keeps ordinary formatting', function () {
        $html = '<h2>Heading</h2><p>Some <strong>bold</strong> and <em>italic</em> text.</p><ul><li>One</li></ul>';

        $this->actingAs(editor())->post(route('stories.store'), storyInput(['body' => $html]));

        expect(Story::firstOrFail()->body)->toBe($html);
    });
});

describe('featured image', function () {
    test('is stored with its dimensions and alt text', function () {
        $this->actingAs(editor())->post(route('stories.store'), storyInput([
            'featured_image' => UploadedFile::fake()->image('cover.jpg', 1200, 800),
            'featured_image_alt' => 'Volunteers at the fundraiser',
        ]));

        $story = Story::firstOrFail();

        Storage::disk('public')->assertExists((string) $story->featured_image_path);

        expect($story->featured_image_width)->toBe(1200)
            ->and($story->featured_image_height)->toBe(800)
            ->and($story->featured_image_alt)->toBe('Volunteers at the fundraiser')
            ->and($story->featuredImage()['url'])->toContain('/storage/stories/');
    });

    test('needs alt text', function () {
        $this->actingAs(editor())->post(route('stories.store'), storyInput([
            'featured_image' => UploadedFile::fake()->image('cover.jpg', 1200, 800),
        ]))->assertSessionHasErrors('featured_image_alt');

        expect(Story::count())->toBe(0);
    });

    test('rejects images that are too small, too large or the wrong type', function (UploadedFile $file) {
        $this->actingAs(editor())->post(route('stories.store'), storyInput([
            'featured_image' => $file,
            'featured_image_alt' => 'Alt',
        ]))->assertSessionHasErrors('featured_image');

        expect(Story::count())->toBe(0);
    })->with([
        'too narrow' => [fn () => UploadedFile::fake()->image('a.jpg', 600, 400)],
        'over 5 MB' => [fn () => UploadedFile::fake()->image('a.jpg', 1200, 800)->size(6000)],
        'a pdf' => [fn () => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf')],
        'svg' => [fn () => UploadedFile::fake()->create('a.svg', 10, 'image/svg+xml')],
    ]);

    test('replacing the image deletes the old file', function () {
        $user = editor();
        $this->actingAs($user)->post(route('stories.store'), storyInput([
            'featured_image' => UploadedFile::fake()->image('one.jpg', 1200, 800),
            'featured_image_alt' => 'One',
        ]));
        $story = Story::firstOrFail();
        $oldPath = (string) $story->featured_image_path;

        $this->actingAs($user)->put(route('stories.update', $story), storyInput([
            'featured_image' => UploadedFile::fake()->image('two.png', 1600, 900),
            'featured_image_alt' => 'Two',
        ]))->assertRedirect();

        $story->refresh();

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists((string) $story->featured_image_path);

        expect($story->featured_image_path)->not->toBe($oldPath)->and($story->featured_image_alt)->toBe('Two');
    });

    test('can be removed', function () {
        $user = editor();
        $this->actingAs($user)->post(route('stories.store'), storyInput([
            'featured_image' => UploadedFile::fake()->image('one.jpg', 1200, 800),
            'featured_image_alt' => 'One',
        ]));
        $story = Story::firstOrFail();
        $path = (string) $story->featured_image_path;

        $this->actingAs($user)->put(route('stories.update', $story), storyInput(['remove_image' => true]))->assertRedirect();

        $story->refresh();

        Storage::disk('public')->assertMissing($path);

        expect($story->featured_image_path)->toBeNull()
            ->and($story->featured_image_alt)->toBeNull()
            ->and($story->featuredImage())->toBeNull();
    });

    test('editing other fields keeps the image and does not ask for it again', function () {
        $story = Story::factory()->create(['featured_image_path' => 'stories/keep.jpg', 'featured_image_alt' => 'Keep me', 'featured_image_width' => 300, 'featured_image_height' => 200]);
        Storage::disk('public')->put('stories/keep.jpg', 'x');

        $this->actingAs(editor())->put(route('stories.update', $story), storyInput(['title' => 'New title', 'featured_image_alt' => 'Keep me']))
            ->assertSessionHasNoErrors();

        $story->refresh();

        Storage::disk('public')->assertExists('stories/keep.jpg');

        expect($story->featured_image_path)->toBe('stories/keep.jpg')->and($story->title)->toBe('New title');
    });

    test('a file still used by another story is not deleted', function () {
        Storage::disk('public')->put('stories/shared.jpg', 'x');
        Story::factory()->create(['featured_image_path' => 'stories/shared.jpg']);

        app(StoryImageService::class)->delete('stories/shared.jpg');

        Storage::disk('public')->assertExists('stories/shared.jpg');

        Story::query()->delete();
        Story::onlyTrashed()->forceDelete();
        app(StoryImageService::class)->delete('stories/shared.jpg');

        Storage::disk('public')->assertMissing('stories/shared.jpg');
    });
});

describe('editing', function () {
    test('shows the story in the form', function () {
        $story = Story::factory()->create(['title' => 'Hello', 'slug' => 'hello']);

        $this->actingAs(editor())->get(route('stories.edit', $story))->assertInertia(fn (Assert $page) => $page
            ->component('stories/edit')
            ->where('story.title', 'Hello')
            ->where('story.slug', 'hello')
            ->where('story.state', 'published')
            ->where('story.image', null)
        );
    });

    test('links to the live page only when a website URL is configured', function () {
        config(['services.frontend.url' => 'https://site.example.test/']);
        $story = Story::factory()->create(['slug' => 'hello']);

        $this->actingAs(editor())->get(route('stories.edit', $story))
            ->assertInertia(fn (Assert $page) => $page->where('story.public_url', 'https://site.example.test/stories/hello'));

        config(['services.frontend.url' => null]);

        $this->actingAs(editor())->get(route('stories.edit', $story))
            ->assertInertia(fn (Assert $page) => $page->where('story.public_url', null));
    });

    test('updates the fields and records who edited', function () {
        $author = editor();
        $other = editor();
        $story = Story::factory()->create(['created_by' => $author->id]);

        $this->actingAs($other)->put(route('stories.update', $story), storyInput([
            'title' => 'Changed', 'excerpt' => 'Short.', 'meta_title' => 'SEO title', 'author_name' => 'Ada',
        ]))->assertRedirect(route('stories.edit', $story));

        $story->refresh();

        expect($story->title)->toBe('Changed')
            ->and($story->excerpt)->toBe('Short.')
            ->and($story->meta_title)->toBe('SEO title')
            ->and($story->author_name)->toBe('Ada')
            ->and($story->created_by)->toBe($author->id)
            ->and($story->updated_by)->toBe($other->id);
    });

    test('a blank address keeps the current one', function () {
        $story = Story::factory()->create(['slug' => 'keep-me']);

        $this->actingAs(editor())->put(route('stories.update', $story), storyInput(['slug' => '']));

        expect($story->refresh()->slug)->toBe('keep-me');
    });

    test('moving a story back to draft keeps its publish date', function () {
        $story = Story::factory()->create(['published_at' => now()->subYear()]);
        $date = $story->published_at;

        $this->actingAs(editor())->put(route('stories.update', $story), storyInput(['status' => 'draft', 'published_at' => $date?->toIso8601String()]));

        $story->refresh();

        expect($story->publicationState())->toBe('draft')->and($story->published_at?->equalTo($date))->toBeTrue();
    });

    test('a trashed story cannot be edited', function () {
        $story = Story::factory()->create();
        $story->delete();

        $this->actingAs(editor())->get(route('stories.edit', $story))->assertNotFound();
        $this->actingAs(editor())->put(route('stories.update', $story), storyInput())->assertNotFound();
    });

    test('a legacy address with a trailing hyphen can be kept', function () {
        $story = Story::factory()->create(['slug' => 'canco-delivering-wishes-']);

        $this->actingAs(editor())->put(route('stories.update', $story), storyInput(['slug' => 'canco-delivering-wishes-']))
            ->assertSessionHasNoErrors();

        expect($story->refresh()->slug)->toBe('canco-delivering-wishes-');
    });

    test('but a changed address must follow the rules', function () {
        $story = Story::factory()->create(['slug' => 'canco-delivering-wishes-']);

        $this->actingAs(editor())->put(route('stories.update', $story), storyInput(['slug' => 'another-trailing-']))
            ->assertSessionHasErrors('slug');
    });
});

describe('renaming the address', function () {
    test('keeps the old web address working with a redirect', function () {
        $story = Story::factory()->create(['slug' => 'old-name']);

        $this->actingAs(editor())->put(route('stories.update', $story), storyInput(['slug' => 'new-name']))->assertSessionHasNoErrors();

        expect($story->refresh()->slug)->toBe('new-name')
            ->and(SlugRedirect::where('old_slug', 'old-name')->value('redirectable_id'))->toBe($story->id);

        $this->getJson('/api/v1/stories/old-name')->assertStatus(301)->assertExactJson(['redirect_to' => 'new-name']);
        $this->getJson('/api/v1/stories/new-name')->assertOk();
    });

    test('a second rename keeps every earlier address working', function () {
        $story = Story::factory()->create(['slug' => 'one']);
        $user = editor();

        $this->actingAs($user)->put(route('stories.update', $story), storyInput(['slug' => 'two']));
        $this->actingAs($user)->put(route('stories.update', $story), storyInput(['slug' => 'three']));

        $this->getJson('/api/v1/stories/one')->assertStatus(301)->assertExactJson(['redirect_to' => 'three']);
        $this->getJson('/api/v1/stories/two')->assertStatus(301)->assertExactJson(['redirect_to' => 'three']);
    });

    test('going back to an earlier address removes it from the redirects', function () {
        $story = Story::factory()->create(['slug' => 'one']);
        $user = editor();

        $this->actingAs($user)->put(route('stories.update', $story), storyInput(['slug' => 'two']));
        $this->actingAs($user)->put(route('stories.update', $story), storyInput(['slug' => 'one']))->assertSessionHasNoErrors();

        expect(SlugRedirect::where('old_slug', 'one')->exists())->toBeFalse()
            ->and(SlugRedirect::where('old_slug', 'two')->exists())->toBeTrue();
        $this->getJson('/api/v1/stories/one')->assertOk();
    });

    test('a story that was never public needs no redirect', function () {
        $story = Story::factory()->draft()->create(['slug' => 'draft-name']);

        $this->actingAs(editor())->put(route('stories.update', $story), storyInput(['status' => 'draft', 'slug' => 'better-name']));

        expect(SlugRedirect::count())->toBe(0);
    });

    test('refreshes both the old and the new address on the website', function () {
        $story = Story::factory()->create(['slug' => 'old-name']);
        Queue::fake();

        $this->actingAs(editor())->put(route('stories.update', $story), storyInput(['slug' => 'new-name']));

        Queue::assertPushed(RevalidateFrontend::class, fn (RevalidateFrontend $job) => in_array('story:old-name', $job->tags, true) && in_array('story:new-name', $job->tags, true));
    });
});

describe('deleting', function () {
    test('moves a story to the trash and takes it off the site', function () {
        $story = Story::factory()->create(['slug' => 'bye']);

        $this->actingAs(editor())->delete(route('stories.destroy', $story))->assertRedirect(route('stories.index'));

        // find() hides soft-deleted rows; fresh() would not.
        expect(Story::find($story->id))->toBeNull()
            ->and(Story::withTrashed()->find($story->id))->not->toBeNull();
        $this->getJson('/api/v1/stories/bye')->assertNotFound();
    });

    test('restores a story from the trash', function () {
        $story = Story::factory()->create(['slug' => 'back']);
        $story->delete();

        $this->actingAs(editor())->patch(route('stories.restore', $story))->assertRedirect();

        expect(Story::find($story->id))->not->toBeNull();
        $this->getJson('/api/v1/stories/back')->assertOk();
    });

    test('editors cannot delete permanently', function () {
        $story = Story::factory()->create();
        $story->delete();

        $this->actingAs(editor())->delete(route('stories.force-delete', $story))->assertForbidden();

        expect(Story::withTrashed()->find($story->id))->not->toBeNull();
    });

    test('administrators can, which also removes the image and old addresses', function () {
        Storage::disk('public')->put('stories/gone.jpg', 'x');
        $story = Story::factory()->create(['slug' => 'doomed', 'featured_image_path' => 'stories/gone.jpg']);
        SlugRedirect::create(['redirectable_type' => 'story', 'redirectable_id' => $story->id, 'old_slug' => 'earlier']);
        $story->delete();

        $this->actingAs(User::factory()->admin()->create())->delete(route('stories.force-delete', $story))->assertRedirect();

        expect(Story::withTrashed()->find($story->id))->toBeNull()
            ->and(SlugRedirect::count())->toBe(0);
        Storage::disk('public')->assertMissing('stories/gone.jpg');
    });

    test('the address of a trashed story is not handed out again', function () {
        $story = Story::factory()->create(['slug' => 'current']);
        $story->delete();

        $this->actingAs(editor())->post(route('stories.store'), storyInput(['slug' => 'current']))->assertSessionHasErrors('slug');
    });
});

describe('feedback', function () {
    test('every action confirms with a toast', function () {
        $user = editor();

        $created = $this->actingAs($user)->post(route('stories.store'), storyInput());
        $created->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Story created.']);

        $story = Story::firstOrFail();

        $this->actingAs($user)->put(route('stories.update', $story), storyInput())
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Story saved.']);

        $this->actingAs($user)->delete(route('stories.destroy', $story))
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Story moved to the trash.']);

        $this->actingAs($user)->patch(route('stories.restore', $story))
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Story restored.']);
    });

    test('validation errors come back per field and keep the editor on the form', function () {
        $this->actingAs(editor())->from(route('stories.create'))->post(route('stories.store'), storyInput(['title' => '']))
            ->assertRedirect(route('stories.create'))
            ->assertSessionHasErrors(['title']);
    });
});

describe('legacy stories', function () {
    test('imported stories can be edited without touching their addresses', function () {
        $this->artisan('stories:import-legacy')->assertSuccessful();
        $story = Story::where('slug', 'canco-delivering-wishes-')->firstOrFail();

        $this->actingAs(editor())->put(route('stories.update', $story), [
            'title' => $story->title,
            'slug' => $story->slug,
            'body' => $story->body,
            'status' => 'published',
            'published_at' => $story->published_at?->toIso8601String(),
            'featured_image_alt' => $story->featured_image_alt,
        ])->assertSessionHasNoErrors();

        expect($story->refresh()->slug)->toBe('canco-delivering-wishes-')
            ->and(SlugRedirect::count())->toBe(0);
        $this->getJson('/api/v1/stories/canco-delivering-wishes-')->assertOk();
    });
});
