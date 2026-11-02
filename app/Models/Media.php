<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One file in the media library (docs/plan/13 §2).
 *
 * @property int $id
 * @property string $path
 * @property string|null $thumb_path
 * @property string $original_name
 * @property string $mime
 * @property int $size_bytes
 * @property int $width
 * @property int $height
 * @property string|null $alt
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['path', 'thumb_path', 'original_name', 'mime', 'size_bytes', 'width', 'height', 'alt', 'uploaded_by'])]
class Media extends Model
{
    protected $table = 'media';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function thumbUrl(): string
    {
        return Storage::disk('public')->url($this->thumb_path ?? $this->path);
    }

    public function isWebp(): bool
    {
        return $this->mime === 'image/webp';
    }

    /**
     * The image as the public API exposes it. An empty `$alt` falls back to the library's own.
     *
     * @return array{url: string, alt: string|null, width: int, height: int}
     */
    public function asImage(?string $alt = null): array
    {
        return [
            'url' => $this->url(),
            'alt' => filled($alt) ? $alt : $this->alt,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
