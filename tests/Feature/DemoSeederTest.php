<?php

use App\Models\Donation;
use App\Models\Event;
use App\Models\Inquiry;
use App\Models\InquiryType;
use App\Models\Media;
use App\Models\Setting;
use App\Models\Story;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Http::fake();
});

test('it fills the admin with stories, events, inquiries, donations and pictures', function () {
    $types = InquiryType::factory()->count(2)->create();

    $this->seed(DemoSeeder::class);

    expect(Media::where('original_name', 'like', 'demo-%')->count())->toBe(8)
        ->and(Story::where('slug', 'like', 'demo-%')->count())->toBe(8)
        ->and(Event::where('slug', 'like', 'demo-%')->count())->toBe(8)
        ->and(Inquiry::where('reference', 'like', 'DEMO-%')->count())->toBe(9)
        ->and(Donation::where('idempotency_key', 'like', 'demo-%')->count())->toBe(8);

    // Every inquiry is filed under one of the existing topics, and keeps the topic's name.
    expect(Inquiry::where('reference', 'like', 'DEMO-%')->whereNull('inquiry_type_id')->count())->toBe(0)
        ->and(Inquiry::where('reference', 'like', 'DEMO-%')->pluck('type_name')->unique()->sort()->values()->all())
        ->toBe($types->pluck('name')->sort()->values()->all());

    // The pictures are real WebP files in the library, with thumbnails.
    $picture = Media::where('original_name', 'like', 'demo-%')->first();
    Storage::disk('public')->assertExists($picture->path);
    Storage::disk('public')->assertExists($picture->thumb_path);
    expect($picture->mime)->toBe('image/webp');

    // A good spread, so every screen has something to show.
    expect(Story::where('slug', 'like', 'demo-%')->published()->count())->toBeGreaterThan(3)
        ->and(Story::where('slug', 'like', 'demo-%')->where('status', 'draft')->count())->toBe(1)
        ->and(Event::where('slug', 'like', 'demo-%')->upcoming()->count())->toBeGreaterThan(0)
        ->and(Event::where('slug', 'like', 'demo-%')->past()->count())->toBeGreaterThan(0);
});

test('it can be run again without duplicating anything', function () {
    InquiryType::factory()->create();

    $this->seed(DemoSeeder::class);
    $counts = fn () => [Story::count(), Event::count(), Inquiry::count(), Donation::count(), Media::count(), User::count()];
    $first = $counts();

    $this->seed(DemoSeeder::class);

    expect($counts())->toBe($first);
});

test('it leaves the home page, the settings, the topics and the existing stories alone', function () {
    $original = Story::factory()->create(['slug' => 'original', 'published_at' => '2023-04-25 07:21:53']);
    Setting::create(['key' => 'home.hero.title', 'value' => 'Our words', 'group' => 'home']);
    $topic = InquiryType::factory()->create(['email' => 'keep@example.com']);
    $settingsBefore = Setting::count();

    $this->seed(DemoSeeder::class);

    expect(Setting::count())->toBe($settingsBefore)
        ->and(Setting::where('key', 'home.hero.title')->value('value'))->toBe('Our words')
        ->and(InquiryType::count())->toBe(1)
        ->and($topic->fresh()->email)->toBe('keep@example.com')
        ->and($original->fresh()->updated_at->equalTo($original->updated_at))->toBeTrue();

    // None of the demo stories is featured, and all are older than the original ones, so the home page's
    // "Latest Stories" (featured first, then newest) is unchanged.
    $newestDemo = Story::where('slug', 'like', 'demo-%')->published()->max('published_at');
    expect(Story::where('slug', 'like', 'demo-%')->where('is_featured', true)->count())->toBe(0)
        ->and(strtotime((string) $newestDemo))->toBeLessThan(strtotime('2023-04-25 07:21:53'));
});

test('it works with no inquiry topics at all', function () {
    $this->seed(DemoSeeder::class);

    expect(Inquiry::where('reference', 'like', 'DEMO-%')->count())->toBe(9);
});

test('its accounts get random passwords, not a well-known one', function () {
    $this->seed(DemoSeeder::class);

    $writer = User::where('email', 'demo.writer@example.test')->firstOrFail();

    expect($writer->getRoleNames()->all())->toBe(['Demo: Content writer'])
        ->and($writer->can('stories.update'))->toBeTrue()
        ->and($writer->can('donations.view'))->toBeFalse()
        ->and(Hash::check('password', $writer->password))->toBeFalse();
});

test('it refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new DemoSeeder)->run())->toThrow(RuntimeException::class, 'production');
});

test('it tells the website once, at the end', function () {
    Http::fake(['*' => Http::response([])]);
    config(['services.frontend.url' => 'https://site.example.test', 'services.frontend.revalidate_secret' => 's']);

    $this->seed(DemoSeeder::class);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['tags'] === ['stories', 'events', 'settings']);
});

test('demo:clear removes the demo data and nothing else', function () {
    $real = Story::factory()->create(['slug' => 'a-real-story']);
    $realEvent = Event::factory()->create(['slug' => 'a-real-event']);
    $realUser = User::factory()->create();
    InquiryType::factory()->create();

    $this->seed(DemoSeeder::class);

    $this->artisan('demo:clear', ['--force' => true])->assertSuccessful();

    expect(Story::where('slug', 'like', 'demo-%')->withTrashed()->count())->toBe(0)
        ->and(Event::where('slug', 'like', 'demo-%')->withTrashed()->count())->toBe(0)
        ->and(Inquiry::count())->toBe(0)
        ->and(Donation::count())->toBe(0)
        ->and(Media::count())->toBe(0)
        ->and(User::where('email', 'like', '%@example.test')->where('email', 'like', 'demo.%')->count())->toBe(0)
        ->and(Story::find($real->id))->not->toBeNull()
        ->and(Event::find($realEvent->id))->not->toBeNull()
        ->and(User::find($realUser->id))->not->toBeNull()
        ->and(InquiryType::count())->toBe(1);
});
