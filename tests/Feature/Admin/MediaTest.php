<?php

use App\Models\Media;
use App\Models\Story;
use App\Models\User;
use App\Services\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

/** A real image (GD) so the converter has pixels to read. */
function fakeImage(string $name, int $width, int $height, string $type = 'jpeg'): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 60, 40));
    $path = tempnam(sys_get_temp_dir(), 'img');

    match ($type) {
        'png' => imagepng($image, $path),
        'gif' => imagegif($image, $path),
        default => imagejpeg($image, $path),
    };

    return new UploadedFile($path, $name, "image/{$type}", null, true);
}

function webpSize(string $path): array
{
    $info = getimagesizefromstring(Storage::disk('public')->get($path));

    return [$info[0], $info[1], $info['mime']];
}

it('converts an uploaded JPEG to WebP and stores a thumbnail', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->post(route('media.store'), ['file' => fakeImage('Team Photo.jpg', 1200, 800)], ['Accept' => 'application/json']);

    $response->assertCreated()->assertJsonPath('data.is_webp', true)->assertJsonPath('data.width', 1200);

    $media = Media::firstOrFail();
    expect($media->mime)->toBe('image/webp')
        ->and($media->path)->toEndWith('.webp')->toContain('team-photo-')
        ->and(webpSize($media->path))->toBe([1200, 800, 'image/webp'])
        ->and(webpSize($media->thumb_path)[0])->toBe(MediaService::THUMB_WIDTH);
});

it('converts PNG and GIF uploads too', function (string $type) {
    $this->actingAs(User::factory()->create());

    $this->post(route('media.store'), ['file' => fakeImage("pic.{$type}", 300, 200, $type)], ['Accept' => 'application/json'])
        ->assertCreated();

    expect(webpSize(Media::firstOrFail()->path)[2])->toBe('image/webp');
})->with(['png', 'gif']);

it('never enlarges and caps the longest edge', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('media.store'), ['file' => fakeImage('big.jpg', 3600, 1800)], ['Accept' => 'application/json'])->assertCreated();
    $this->post(route('media.store'), ['file' => fakeImage('small.jpg', 200, 100)], ['Accept' => 'application/json'])->assertCreated();

    [$big, $small] = [Media::where('original_name', 'big.jpg')->firstOrFail(), Media::where('original_name', 'small.jpg')->firstOrFail()];

    expect([$big->width, $big->height])->toBe([MediaService::MAX_EDGE, 1200])
        ->and([$small->width, $small->height])->toBe([200, 100]);
});

it('rejects files that are not images', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('media.store'), ['file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    expect(Media::count())->toBe(0);
});

it('rejects a file that claims to be an image but is not', function () {
    $this->actingAs(User::factory()->create());

    $fake = UploadedFile::fake()->createWithContent('evil.jpg', '<?php echo 1;');

    $this->post(route('media.store'), ['file' => $fake], ['Accept' => 'application/json'])->assertUnprocessable();

    expect(Media::count())->toBe(0);
});

it('requires a signed-in user', function () {
    $this->post(route('media.store'), ['file' => fakeImage('a.jpg', 100, 100)], ['Accept' => 'application/json'])->assertUnauthorized();
    $this->get(route('media.index'))->assertRedirect();
});

it('lists media for the page and the picker, with search', function () {
    $this->actingAs(User::factory()->create());
    $this->post(route('media.store'), ['file' => fakeImage('banner.jpg', 800, 400)], ['Accept' => 'application/json']);
    $this->post(route('media.store'), ['file' => fakeImage('portrait.jpg', 400, 800)], ['Accept' => 'application/json']);

    $this->get(route('media.index', ['search' => 'banner']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('media/index')->has('media.data', 1)->where('media.data.0.name', 'banner.jpg'));

    $this->getJson(route('media.library'))->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
});

it('saves the default alt text', function () {
    $this->actingAs(User::factory()->create());
    $this->post(route('media.store'), ['file' => fakeImage('a.jpg', 100, 100)], ['Accept' => 'application/json']);
    $media = Media::firstOrFail();

    $this->patch(route('media.update', $media), ['alt' => 'Volunteers packing boxes'])->assertRedirect();

    expect($media->fresh()->alt)->toBe('Volunteers packing boxes');
});

it('deletes an unused image and its files', function () {
    $this->actingAs(User::factory()->create());
    $this->post(route('media.store'), ['file' => fakeImage('a.jpg', 100, 100)], ['Accept' => 'application/json']);
    $media = Media::firstOrFail();

    $this->delete(route('media.destroy', $media))->assertRedirect();

    expect(Media::count())->toBe(0);
    Storage::disk('public')->assertMissing([$media->path, $media->thumb_path]);
});

it('refuses to delete an image a story uses', function () {
    $this->actingAs(User::factory()->create());
    $this->post(route('media.store'), ['file' => fakeImage('a.jpg', 100, 100)], ['Accept' => 'application/json']);
    $media = Media::firstOrFail();
    Story::factory()->create(['title' => 'Winter Drive', 'featured_image_path' => $media->path]);

    $this->delete(route('media.destroy', $media))->assertRedirect()->assertInertiaFlash('toast.type', 'error');

    expect(Media::count())->toBe(1);
    Storage::disk('public')->assertExists($media->path);
});

it('converts an imported non-WebP file and repoints the story that used it', function () {
    $this->actingAs(User::factory()->create());

    Storage::disk('public')->put('stories/old.jpg', file_get_contents(fakeImage('old.jpg', 600, 400)->getRealPath()));
    $media = Media::create([
        'path' => 'stories/old.jpg', 'thumb_path' => null, 'original_name' => 'old.jpg', 'mime' => 'image/jpeg',
        'size_bytes' => 1000, 'width' => 600, 'height' => 400,
    ]);
    $story = Story::factory()->create(['featured_image_path' => 'stories/old.jpg']);

    $this->post(route('media.convert', $media))->assertRedirect()->assertInertiaFlash('toast.type', 'success');

    $media->refresh();
    expect($media->mime)->toBe('image/webp')
        ->and($story->fresh()->featured_image_path)->toBe($media->path);
    Storage::disk('public')->assertMissing('stories/old.jpg');
});
