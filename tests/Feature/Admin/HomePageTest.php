<?php

use App\Jobs\RevalidateFrontend;
use App\Models\Media;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

function homeInput(array $overrides = []): array
{
    return array_replace_recursive([
        'hero' => ['title' => 'Hope Across Canada', 'text' => 'Join us.', 'primary_label' => 'Give', 'secondary_label' => 'Partner', 'secondary_href' => '/partner-with-us'],
        'intro' => ['eyebrow' => 'Every community', 'title' => 'CANCO CARES', 'text' => 'Intro text', 'button_label' => 'More', 'button_href' => '/who-we-are'],
        'cards' => [
            ['eyebrow' => 'Community', 'title' => 'PROJECTS', 'text' => 'One'],
            ['eyebrow' => 'Strategic', 'title' => 'PARTNERSHIPS', 'text' => 'Two'],
        ],
        'stories' => ['eyebrow' => 'In Community', 'title' => 'LATEST STORIES'],
    ], $overrides);
}

function libraryImage(string $path = 'media/hero.webp'): Media
{
    return Media::create([
        'path' => $path, 'thumb_path' => 'media/hero-thumb.webp', 'original_name' => 'hero.jpg',
        'mime' => 'image/webp', 'size_bytes' => 1000, 'width' => 1200, 'height' => 800, 'alt' => 'Default alt',
    ]);
}

test('the public API serves the built-in content when nothing was edited', function () {
    $data = $this->getJson('/api/v1/home')->assertOk()->json('data');

    expect($data['hero']['title'])->toBe('Fueling Hope Across Canada')
        ->and($data['hero']['image'])->toBeNull()
        ->and($data['cards'])->toHaveCount(2)
        ->and($data['cards'][0]['title'])->toBe('PROJECTS')
        ->and($data['stories']['title'])->toBe('LATEST STORIES');
});

test('the editor shows the content and the defaults', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home-page.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('home-page/edit')
            ->where('content.hero.title', 'Fueling Hope Across Canada')
            ->where('defaults.hero.title', 'Fueling Hope Across Canada')
            ->has('content.cards', 2));
});

test('guests cannot open or save the editor', function () {
    $this->get(route('home-page.edit'))->assertRedirect();
    $this->put(route('home-page.update'), homeInput())->assertRedirect();
});

test('saved text is served by the API and the website cache is refreshed', function () {
    $this->actingAs(User::factory()->create())->put(route('home-page.update'), homeInput())
        ->assertRedirect(route('home-page.edit'))
        ->assertSessionHasNoErrors();

    $this->getJson('/api/v1/home')
        ->assertJsonPath('data.hero.title', 'Hope Across Canada')
        ->assertJsonPath('data.hero.primary_label', 'Give')
        ->assertJsonPath('data.cards.1.title', 'PARTNERSHIPS');

    Queue::assertPushed(RevalidateFrontend::class, fn (RevalidateFrontend $job) => $job->tags === ['settings']);
});

test('a cleared field falls back to the original text', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('home-page.update'), homeInput(['hero' => ['title' => '']]))
        ->assertSessionHasNoErrors();

    $this->getJson('/api/v1/home')->assertJsonPath('data.hero.title', 'Fueling Hope Across Canada');
});

test('a library image is resolved with its alt text, and remove brings back the bundled one', function () {
    $user = User::factory()->create();
    $media = libraryImage();

    $this->actingAs($user)->put(route('home-page.update'), homeInput([
        'hero' => ['image' => ['media_id' => $media->id, 'alt' => 'A dad hugging his daughter']],
    ]))->assertSessionHasNoErrors();

    $this->getJson('/api/v1/home')
        ->assertJsonPath('data.hero.image.width', 1200)
        ->assertJsonPath('data.hero.image.alt', 'A dad hugging his daughter')
        ->assertJsonPath('data.cards.0.image', null);

    // Saving again without choosing a new picture keeps the one already set.
    $this->actingAs($user)->put(route('home-page.update'), homeInput(['hero' => ['image' => ['alt' => 'New description']]]))
        ->assertSessionHasNoErrors();
    $this->getJson('/api/v1/home')
        ->assertJsonPath('data.hero.image.alt', 'New description')
        ->assertJsonPath('data.hero.image.width', 1200);

    $this->actingAs($user)->put(route('home-page.update'), homeInput(['hero' => ['image' => ['remove' => '1']]]));
    $this->getJson('/api/v1/home')->assertJsonPath('data.hero.image', null);
});

test('an image in use on the home page cannot be deleted from the library', function () {
    $user = User::factory()->create();
    $media = libraryImage();

    $this->actingAs($user)->put(route('home-page.update'), homeInput([
        'hero' => ['image' => ['media_id' => $media->id, 'alt' => 'Alt']],
    ]));

    $this->actingAs($user)->delete(route('media.destroy', $media))->assertInertiaFlash('toast.type', 'error');

    expect(Media::count())->toBe(1);
});

test('links must be site paths or http(s) addresses', function (string $href) {
    $this->actingAs(User::factory()->create())
        ->put(route('home-page.update'), homeInput(['hero' => ['secondary_href' => $href]]))
        ->assertSessionHasErrors('hero.secondary_href');
})->with(['javascript:alert(1)', 'mailto:a@b.c', 'who-we-are', 'data:text/html,hi']);

test('a chosen image needs a description', function () {
    $media = libraryImage();

    $this->actingAs(User::factory()->create())
        ->put(route('home-page.update'), homeInput(['hero' => ['image' => ['media_id' => $media->id]]]))
        ->assertSessionHasErrors('hero.image.alt');
});

test('settings are cached but a save is visible immediately', function () {
    $settings = app(Settings::class);

    expect($settings->string('impact.caption'))->toBe('AND COUNTING!');

    $settings->set('impact.caption', 'AND GROWING');

    expect(app(Settings::class)->string('impact.caption'))->toBe('AND GROWING');
});

test('unknown settings are refused', function () {
    app(Settings::class)->set('impakt.caption', 'typo');
})->throws(InvalidArgumentException::class);
