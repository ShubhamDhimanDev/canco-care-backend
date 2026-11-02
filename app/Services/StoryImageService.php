<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Story;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Where a story's featured image comes from: an image picked in the media library, or a file
 * uploaded with the form (which goes through the library too, so it is converted to WebP and can be
 * reused). The library owns its files; only legacy files outside it are removed here.
 */
class StoryImageService
{
    public function __construct(private readonly MediaService $media) {}

    /**
     * @return array{path: string, width: int|null, height: int|null}
     *
     * @throws ImageConversionException
     */
    public function store(UploadedFile $file, ?User $user = null): array
    {
        return $this->fromMedia($this->media->store($file, $user));
    }

    /**
     * @return array{path: string, width: int|null, height: int|null}
     */
    public function fromMedia(Media $media): array
    {
        return ['path' => $media->path, 'width' => $media->width, 'height' => $media->height];
    }

    /**
     * Deletes a legacy file (one that is not in the media library) unless another story still uses it.
     */
    public function delete(?string $path): void
    {
        if ($path === null || $path === '' || Media::where('path', $path)->exists()) {
            return;
        }

        $stillUsed = Story::withTrashed()->where('featured_image_path', $path)->exists();

        if (! $stillUsed) {
            Storage::disk('public')->delete($path);
        }
    }
}
