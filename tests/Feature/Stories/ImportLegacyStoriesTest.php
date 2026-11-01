<?php

use App\Enums\StoryStatus;
use App\Models\Story;
use App\Services\LegacyStoryImporter;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

const LEGACY_SLUGS = [
    'community-matters',
    'another-wish-delivered',
    'canco-delivering-wishes-',
    'wishing-you-an-amazing-2023',
    'canco-donates-9500-to-starbright',
    'supporting-starbrights-autism-program',
    'canco-donated-11000-to-kgh-foundation',
];

test('it imports the seven legacy stories with their original URLs', function () {
    $this->artisan('stories:import-legacy')->assertSuccessful();

    expect(Story::count())->toBe(7)
        ->and(Story::pluck('slug')->all())->toEqualCanonicalizing(LEGACY_SLUGS)
        ->and(Story::pluck('legacy_id')->all())->toEqualCanonicalizing([41, 42, 43, 46, 47, 48, 49]);
});

test('imported stories are public and carry their original publish date in UTC', function () {
    $this->artisan('stories:import-legacy')->assertSuccessful();

    expect(Story::published()->count())->toBe(7);

    $story = Story::where('legacy_id', 49)->firstOrFail();

    expect($story->status)->toBe(StoryStatus::Published)
        // legacy value 2023-04-25T00:21:55.970682-07:00
        ->and($story->published_at?->format('Y-m-d H:i:s'))->toBe('2023-04-25 07:21:55')
        ->and($story->created_at?->format('Y-m-d'))->toBe('2023-05-06');
});

test('the public order matches what the live site shows, with only one date nudged', function () {
    $this->artisan('stories:import-legacy')->assertSuccessful();

    // Home "Latest Stories" on cancocares.ca shows the first three of these.
    expect(Story::published()->orderByDesc('published_at')->orderByDesc('id')->pluck('slug')->all())->toBe([
        'community-matters',
        'another-wish-delivered',
        'canco-delivering-wishes-',
        'wishing-you-an-amazing-2023',
        'canco-donates-9500-to-starbright',
        'supporting-starbrights-autism-program',
        'canco-donated-11000-to-kgh-foundation',
    ]);

    // Legacy 48 ("Wishing You an Amazing 2023!") was stamped 07:21:55, later than the stories listed
    // before it; it is moved to 07:21:52. Every other story keeps its original second.
    expect(Story::where('legacy_id', 48)->value('published_at'))->toEqual(Date::parse('2023-04-25 07:21:52'));
    expect(Story::where('legacy_id', 47)->first()->published_at?->format('H:i:s'))->toBe('07:21:54');
});

test('the importer says when it moved a date', function () {
    $results = app(LegacyStoryImporter::class)->import(
        database_path('seeders/data/legacy-stories/stories.json'),
        database_path('seeders/data/legacy-stories/images'),
        database_path('seeders/data/legacy-stories/alt-text.json'),
        dryRun: true,
    );

    $moved = collect($results)->filter(fn ($r) => in_array('published_at moved to keep the live listing order', $r['notes'], true));

    expect($moved->pluck('legacy_id')->all())->toBe([48]);
});

test('every story gets its image on the public disk, with dimensions and alt text', function () {
    $this->artisan('stories:import-legacy')->assertSuccessful();

    Story::all()->each(function (Story $story) {
        Storage::disk('public')->assertExists((string) $story->featured_image_path);

        expect($story->featured_image_width)->toBeGreaterThan(0)
            ->and($story->featured_image_height)->toBeGreaterThan(0)
            ->and($story->featured_image_alt)->not->toBeEmpty()
            ->and(mb_strlen((string) $story->featured_image_alt))->toBeLessThanOrEqual(200);
    });
});

test('bodies are sanitised and excerpts read naturally', function () {
    $this->artisan('stories:import-legacy')->assertSuccessful();

    $story = Story::where('slug', 'community-matters')->firstOrFail();

    expect($story->body)->toContain('Splatsin Kukpi7 &amp; Tkwamipla7')
        ->and($story->body)->not->toContain('<script')
        ->and($story->excerpt)->toStartWith('Here at Canco, we are one big community')
        ->and($story->excerpt)->toContain('Canco family! In early February')
        ->and(mb_strlen((string) $story->excerpt))->toBeLessThanOrEqual(161);
});

test('re-running creates no duplicates and keeps editor changes', function () {
    $this->artisan('stories:import-legacy')->assertSuccessful();
    Story::where('legacy_id', 49)->update(['title' => 'Edited by an editor']);

    $this->artisan('stories:import-legacy')->assertSuccessful();

    expect(Story::count())->toBe(7)
        ->and(Story::where('legacy_id', 49)->value('title'))->toBe('Edited by an editor');
});

test('--update restores the legacy content', function () {
    $this->artisan('stories:import-legacy')->assertSuccessful();
    Story::where('legacy_id', 49)->update(['title' => 'Edited by an editor']);

    $this->artisan('stories:import-legacy', ['--update' => true])->assertSuccessful();

    expect(Story::count())->toBe(7)
        ->and(Story::where('legacy_id', 49)->value('title'))->toBe('Community Matters');
});

test('stories an editor deleted are not resurrected', function () {
    $this->artisan('stories:import-legacy')->assertSuccessful();
    Story::where('legacy_id', 49)->firstOrFail()->delete();

    $this->artisan('stories:import-legacy', ['--update' => true])->assertSuccessful();

    expect(Story::count())->toBe(6)
        ->and(Story::withTrashed()->count())->toBe(7);
});

test('--dry-run writes nothing', function () {
    $this->artisan('stories:import-legacy', ['--dry-run' => true])->assertSuccessful();

    expect(Story::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('it fails with a clear message when the source file is missing', function () {
    $this->artisan('stories:import-legacy', ['--source' => base_path('does-not-exist.json')])
        ->expectsOutputToContain('File not found')
        ->assertFailed();

    expect(Story::count())->toBe(0);
});

test('it rejects a snapshot containing a malformed story', function () {
    $file = tempnam(sys_get_temp_dir(), 'stories');
    file_put_contents($file, json_encode([['id' => '1', 'title' => 'No content or date']]));

    $this->artisan('stories:import-legacy', ['--source' => $file])
        ->expectsOutputToContain('needs id, title, content and publishDateTime')
        ->assertFailed();

    unlink($file);

    expect(Story::count())->toBe(0);
});

test('it refuses to import over a different story that already uses the slug', function () {
    Story::factory()->create(['slug' => 'community-matters', 'legacy_id' => null]);

    $this->artisan('stories:import-legacy')
        ->expectsOutputToContain('already used by story')
        ->assertFailed();
});

test('only published stories whose date has passed are visible to the public scope', function () {
    Story::factory()->create(['slug' => 'live']);
    Story::factory()->draft()->create(['slug' => 'draft']);
    Story::factory()->scheduled()->create(['slug' => 'later']);

    expect(Story::published()->pluck('slug')->all())->toBe(['live']);
});
