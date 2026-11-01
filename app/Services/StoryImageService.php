<?php

namespace App\Services;

use App\Models\Story;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores story images on the public disk. The website resizes on the fly with
 * next/image, so the original is kept as uploaded (validated for type, size and
 * dimensions by StoryRequest).
 */
class StoryImageService
{
    private const DIRECTORY = 'stories';

    /**
     * @return array{path: string, width: int|null, height: int|null}
     */
    public function store(UploadedFile $file): array
    {
        $size = getimagesize($file->getRealPath());
        $path = Storage::disk('public')->putFile(self::DIRECTORY, $file);

        return [
            'path' => (string) $path,
            'width' => $size === false ? null : $size[0],
            'height' => $size === false ? null : $size[1],
        ];
    }

    /**
     * Deletes the file unless another story still uses it.
     */
    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $stillUsed = Story::withTrashed()->where('featured_image_path', $path)->exists();

        if (! $stillUsed) {
            Storage::disk('public')->delete($path);
        }
    }
}
