<?php

namespace App\Models;

use App\Enums\StoryStatus;
use App\Observers\StoryObserver;
use Database\Factories\StoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int|null $legacy_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string $body
 * @property string|null $featured_image_path
 * @property string|null $featured_image_alt
 * @property int|null $featured_image_width
 * @property int|null $featured_image_height
 * @property StoryStatus $status
 * @property Carbon|null $published_at
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $author_name
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'legacy_id',
    'title',
    'slug',
    'excerpt',
    'body',
    'featured_image_path',
    'featured_image_alt',
    'featured_image_width',
    'featured_image_height',
    'status',
    'published_at',
    'meta_title',
    'meta_description',
    'author_name',
    'created_by',
    'updated_by',
])]
#[ObservedBy(StoryObserver::class)]
class Story extends Model
{
    /** @use HasFactory<StoryFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'status' => StoryStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * What an editor sees as this story's state: trashed, draft, scheduled or published.
     */
    public function publicationState(): string
    {
        return match (true) {
            $this->trashed() => 'trashed',
            $this->status === StoryStatus::Draft || $this->published_at === null => 'draft',
            $this->published_at->isFuture() => 'scheduled',
            default => 'published',
        };
    }

    /**
     * Old slugs that should redirect to this story.
     *
     * @return MorphMany<SlugRedirect, $this>
     */
    public function redirects(): MorphMany
    {
        return $this->morphMany(SlugRedirect::class, 'redirectable');
    }

    /**
     * The featured image as the API exposes it, or null when the story has none.
     *
     * @return array{url: string, alt: string|null, width: int|null, height: int|null}|null
     */
    public function featuredImage(): ?array
    {
        if ($this->featured_image_path === null) {
            return null;
        }

        return [
            'url' => Storage::disk('public')->url($this->featured_image_path),
            'alt' => $this->featured_image_alt,
            'width' => $this->featured_image_width,
            'height' => $this->featured_image_height,
        ];
    }

    /**
     * The nearest public story published before this one ("previous"), if any.
     */
    public function older(): ?Story
    {
        return static::query()->published()
            ->where(fn (Builder $query) => $query
                ->where('published_at', '<', $this->published_at)
                ->orWhere(fn (Builder $tie) => $tie
                    ->where('published_at', $this->published_at)
                    ->where('id', '<', $this->id)))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The nearest public story published after this one ("next"), if any.
     */
    public function newer(): ?Story
    {
        return static::query()->published()
            ->where(fn (Builder $query) => $query
                ->where('published_at', '>', $this->published_at)
                ->orWhere(fn (Builder $tie) => $tie
                    ->where('published_at', $this->published_at)
                    ->where('id', '>', $this->id)))
            ->orderBy('published_at')
            ->orderBy('id')
            ->first();
    }

    /**
     * Stories visible on the public site: published and not scheduled for later.
     *
     * @param  Builder<Story>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', StoryStatus::Published->value)
            ->where('published_at', '<=', now());
    }
}
