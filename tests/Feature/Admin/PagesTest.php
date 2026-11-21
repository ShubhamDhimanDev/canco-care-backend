<?php

use App\Jobs\RevalidateFrontend;
use App\Models\Media;
use App\Models\User;
use App\Services\MediaUsage;
use App\Services\Settings;
use App\Support\PageCatalog;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Queue::fake();
});

describe('access', function () {
    test('guests are sent to the login page', function () {
        $this->get(route('pages.index'))->assertRedirect();
        $this->put(route('pages.update', 'who-we-are'), [])->assertRedirect();
    });

    test('a user without the permission is refused', function () {
        $this->actingAs(User::factory()->withoutRoles()->create())->get(route('pages.index'))->assertForbidden();
    });

    test('an editor (default role) can open the list and every page', function () {
        $editor = User::factory()->create();

        $this->actingAs($editor)->get(route('pages.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('pages/index')->has('pages', count(PageCatalog::slugs())));

        foreach (PageCatalog::slugs() as $slug) {
            $this->actingAs($editor)->get(route('pages.edit', $slug))->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('pages/edit')->where('slug', $slug)->has('sections'));
        }
    });

    test('an unknown page is a 404', function () {
        $this->actingAs(User::factory()->create())->get('/pages/nope')->assertNotFound();
        $this->actingAs(User::factory()->create())->put('/pages/nope', [])->assertForbidden();
    });

    test('the Editor role carries the permission', function () {
        expect(Role::findByName('Editor', 'web')->hasPermissionTo('pages.update'))->toBeTrue();
    });
});

describe('defaults', function () {
    test('every page has defaults for every field, so an empty database renders the original site', function () {
        foreach (PageCatalog::slugs() as $slug) {
            $content = PageCatalog::merged($slug, app(Settings::class));

            foreach (PageCatalog::fields($slug) as $key => $field) {
                expect($content)->toHaveKey($key);

                if ($field['type'] !== 'image' && $key !== 'updated_on') {
                    expect($content[$key])->not->toBe('', "{$slug}.{$key} has no default");
                }
            }
        }
    });

    test('the legal pages start from the text published on the old website', function () {
        foreach (['terms-and-conditions', 'privacy-policy'] as $slug) {
            expect(PageCatalog::merged($slug, app(Settings::class))['body'])->toContain('Personal Information Protection Policy');
        }
    });
});

describe('saving', function () {
    test('stores only what changed, trims, and refreshes the website', function () {
        $this->actingAs(User::factory()->create())
            ->put(route('pages.update', 'contact-us'), ['fields' => ['title' => '  GET IN TOUCH  ', 'eyebrow' => '', 'lead' => 'Hello there.']])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pages.edit', 'contact-us'));

        expect(app(Settings::class)->get('pages.contact-us'))->toBe(['title' => 'GET IN TOUCH', 'lead' => 'Hello there.']);

        $content = PageCatalog::merged('contact-us', app(Settings::class));
        expect($content['title'])->toBe('GET IN TOUCH')
            ->and($content['eyebrow'])->toBe('CANCO CARES');   // emptied → original

        Queue::assertPushed(RevalidateFrontend::class);
    });

    test('rich text is sanitised', function () {
        $this->actingAs(User::factory()->create())->put(route('pages.update', 'privacy-policy'), ['fields' => [
            'body' => '<h2>Privacy</h2><p onclick="x()">Hello <script>alert(1)</script><a href="javascript:alert(1)">bad</a> <a href="https://example.com">good</a></p>',
        ]])->assertSessionHasNoErrors();

        $body = (string) app(Settings::class)->get('pages.privacy-policy')['body'];

        expect($body)->toContain('<h2>Privacy</h2>')->toContain('https://example.com')
            ->not->toContain('<script')->not->toContain('onclick')->not->toContain('javascript:');
    });

    test('text longer than the field allows is refused', function () {
        $this->actingAs(User::factory()->create())
            ->put(route('pages.update', 'contact-us'), ['fields' => ['title' => str_repeat('a', 41)]])
            ->assertSessionHasErrors('fields.title');
    });

    test('a picture needs a description and an existing library item', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('pages.update', 'who-we-are'), ['fields' => ['mission_image' => ['media_id' => 999]]])
            ->assertSessionHasErrors(['fields.mission_image.media_id', 'fields.mission_image.alt']);

        $media = pageMedia();
        $this->actingAs($user)->put(route('pages.update', 'who-we-are'), ['fields' => ['mission_image' => ['media_id' => $media->id, 'alt' => 'A hand holding a heart']]])
            ->assertSessionHasNoErrors();

        expect(app(Settings::class)->get('pages.who-we-are')['mission_image'])->toBe(['media_id' => $media->id, 'alt' => 'A hand holding a heart']);

        // The picture is now in use, so the library refuses to delete it.
        expect(app(MediaUsage::class)->isUsed($media))->toBeTrue();

        // "Remove" brings back the bundled picture.
        $this->actingAs($user)->put(route('pages.update', 'who-we-are'), ['fields' => ['mission_image' => ['remove' => '1']]])->assertSessionHasNoErrors();
        expect(app(Settings::class)->get('pages.who-we-are'))->toBe([]);
    });

    test('an existing picture is kept when the form is saved without touching it', function () {
        $user = User::factory()->create();
        $media = pageMedia();
        $this->actingAs($user)->put(route('pages.update', 'our-impact'), ['fields' => ['photo' => ['media_id' => $media->id, 'alt' => 'The team']]]);

        $this->actingAs($user)->put(route('pages.update', 'our-impact'), ['fields' => ['title' => 'IMPACT', 'photo' => ['alt' => 'The team, smiling']]])->assertSessionHasNoErrors();

        expect(app(Settings::class)->get('pages.our-impact')['photo'])->toBe(['media_id' => $media->id, 'alt' => 'The team, smiling']);
    });
});

describe('public API', function () {
    test('serves the merged page with defaults', function () {
        $this->getJson('/api/v1/pages/who-we-are')->assertOk()
            ->assertJsonPath('data.slug', 'who-we-are')
            ->assertJsonPath('data.content.mission_title', 'MISSION')
            ->assertJsonPath('data.content.mission_image', null)
            ->assertJsonPath('data.content.value_6_name', 'Legacy');
    });

    test('serves an edited page, with the picture resolved', function () {
        $media = pageMedia('Library alt');
        app(Settings::class)->set('pages.our-impact', ['title' => 'WHAT WE DID', 'photo' => ['media_id' => $media->id, 'alt' => 'Custom alt']]);

        $this->getJson('/api/v1/pages/our-impact')->assertOk()
            ->assertJsonPath('data.content.title', 'WHAT WE DID')
            ->assertJsonPath('data.content.photo.alt', 'Custom alt')
            ->assertJsonPath('data.content.photo.url', $media->url());
    });

    test('{amount} in a page is replaced by the impact counter figure', function () {
        app(Settings::class)->setMany(['impact.mode' => 'custom', 'impact.amount_text' => '$90,000']);

        $this->getJson('/api/v1/pages/our-impact')->assertOk()
            ->assertJsonPath('data.content.meta_description', fn (string $text) => str_starts_with($text, 'Over $90,000 donated'));
    });

    test('a privacy policy page exists, and an unknown page is a 404', function () {
        $this->getJson('/api/v1/pages/privacy-policy')->assertOk()->assertJsonPath('data.content.title', 'PRIVACY POLICY');
        $this->getJson('/api/v1/pages/nope')->assertNotFound();
    });
});

function pageMedia(?string $alt = null): Media
{
    return Media::create([
        'path' => 'media/page-'.uniqid().'.webp', 'thumb_path' => null, 'original_name' => 'x.jpg', 'mime' => 'image/webp',
        'size_bytes' => 10, 'width' => 1600, 'height' => 900, 'alt' => $alt,
    ]);
}
