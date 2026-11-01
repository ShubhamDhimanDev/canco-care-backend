<?php

namespace App\Services;

use App\Models\Media;
use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The media library's write side. Every image is converted to WebP with GD, scaled down (never up)
 * to a sane size, stripped of metadata and given a thumbnail for the admin grid. Nothing is stored
 * unconverted: if the server cannot write WebP the upload is refused with a clear message.
 */
class MediaService
{
    /** Longest edge kept for the full-size file. The site never shows an image wider than this. */
    public const MAX_EDGE = 2400;

    public const THUMB_WIDTH = 480;

    public const QUALITY = 82;

    /** GD holds ~4 bytes per pixel, so this bounds memory use (about 100 MB for the decoded original). */
    public const MAX_PIXELS = 24_000_000;

    /** @var array<string, string> */
    private const READERS = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
        'image/gif' => 'imagecreatefromgif',
    ];

    public static function canConvert(): bool
    {
        return extension_loaded('gd') && function_exists('imagewebp');
    }

    /**
     * Converts an upload and records it in the library.
     *
     * @throws ImageConversionException
     */
    public function store(UploadedFile $file, ?User $user = null, ?string $alt = null): Media
    {
        $stored = $this->convertFile((string) $file->getRealPath(), $file->getClientOriginalName());

        return Media::create([
            ...$stored,
            'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
            'alt' => filled($alt) ? $alt : null,
            'uploaded_by' => $user?->id,
        ]);
    }

    /**
     * Re-encodes a library file that is not WebP (for example one registered from the old stories
     * module) and points every story/event that used it at the new file.
     *
     * @throws ImageConversionException
     */
    public function convertExisting(Media $media): Media
    {
        if ($media->isWebp()) {
            return $media;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($media->path)) {
            throw new ImageConversionException('The original file is missing from storage.');
        }

        $oldPath = $media->path;
        $oldThumb = $media->thumb_path;
        $stored = $this->convertFile($disk->path($oldPath), $media->original_name);

        $media->update($stored);
        app(MediaUsage::class)->repoint($oldPath, $media);

        $disk->delete(array_values(array_filter([$oldPath, $oldThumb !== $oldPath ? $oldThumb : null])));

        return $media;
    }

    /**
     * Removes the files and the row. Callers check MediaUsage first.
     */
    public function delete(Media $media): void
    {
        Storage::disk('public')->delete(array_values(array_filter([$media->path, $media->thumb_path])));

        $media->delete();
    }

    /**
     * @return array{path: string, thumb_path: string, mime: string, size_bytes: int, width: int, height: int}
     *
     * @throws ImageConversionException
     */
    private function convertFile(string $source, string $originalName): array
    {
        if (! self::canConvert()) {
            throw new ImageConversionException('This server cannot convert images to WebP (the PHP GD extension with WebP support is missing).');
        }

        $info = @getimagesize($source);

        if ($info === false || ! isset(self::READERS[$info['mime']])) {
            throw new ImageConversionException('That file is not a JPG, PNG, GIF or WebP image.');
        }

        [$width, $height] = $info;

        if ($width * $height > self::MAX_PIXELS) {
            throw new ImageConversionException('That image is too large (over 24 megapixels). Resize it and try again.');
        }

        $this->allowMemoryFor($width, $height);

        $reader = self::READERS[$info['mime']];
        $image = @$reader($source);

        if (! $image instanceof GdImage) {
            throw new ImageConversionException('That image could not be read. Try saving it again from an image editor.');
        }

        $image = $this->applyOrientation($image, $source, $info['mime']);
        $full = $this->scaleDown($image, self::MAX_EDGE);
        $thumb = $this->scaleDown($full, self::THUMB_WIDTH, widthOnly: true);

        $base = 'media/'.now()->format('Y/m').'/'.Str::slug(pathinfo($originalName, PATHINFO_FILENAME) ?: 'image').'-'.Str::lower(Str::random(8));
        $path = $base.'.webp';
        $thumbPath = $base.'-thumb.webp';

        $disk = Storage::disk('public');
        $fullBytes = $this->encode($full);
        $disk->put($path, $fullBytes);
        $disk->put($thumbPath, $this->encode($thumb));

        return [
            'path' => $path,
            'thumb_path' => $thumbPath,
            'mime' => 'image/webp',
            'size_bytes' => strlen($fullBytes),
            'width' => imagesx($full),
            'height' => imagesy($full),
        ];
    }

    /**
     * Resizes so the longest edge (or, with $widthOnly, the width) is at most $limit; never enlarges.
     */
    private function scaleDown(GdImage $image, int $limit, bool $widthOnly = false): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $edge = $widthOnly ? $width : max($width, $height);

        if ($edge <= $limit) {
            return $image;
        }

        $ratio = $limit / $edge;
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $copy = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($copy, false);
        imagesavealpha($copy, true);
        imagefill($copy, 0, 0, (int) imagecolorallocatealpha($copy, 0, 0, 0, 127));
        imagecopyresampled($copy, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $copy;
    }

    /**
     * Phones store the "up" direction in EXIF rather than rotating the pixels; honour it, because the
     * WebP we write carries no metadata.
     */
    private function applyOrientation(GdImage $image, string $source, string $mime): GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($source);
        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        return imagerotate($image, $angle, 0) ?: $image;
    }

    private function encode(GdImage $image): string
    {
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $ok = imagewebp($image, null, self::QUALITY);
        $bytes = (string) ob_get_clean();

        if (! $ok || $bytes === '') {
            throw new ImageConversionException('The image could not be converted to WebP.');
        }

        return $bytes;
    }

    /**
     * Decoding a large photo needs about 4 bytes per pixel, several times over while resizing.
     */
    private function allowMemoryFor(int $width, int $height): void
    {
        $limit = ini_get('memory_limit');

        if ($limit === '-1') {
            return;
        }

        $needed = $width * $height * 4 * 3 + 64 * 1024 * 1024;

        if ($this->bytes($limit) < $needed) {
            @ini_set('memory_limit', (string) $needed);
        }
    }

    private function bytes(string $value): int
    {
        $number = (int) $value;

        return match (strtolower(substr(trim($value), -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
