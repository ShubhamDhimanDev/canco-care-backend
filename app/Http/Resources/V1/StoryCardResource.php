<?php

namespace App\Http\Resources\V1;

use App\Models\Story;
use App\Support\Excerpt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Story as shown on listing cards (docs/plan/03-api-spec.md "StoryCard").
 *
 * @property-read Story $resource
 */
class StoryCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $story = $this->resource;

        return [
            'slug' => $story->slug,
            'title' => $story->title,
            'excerpt' => $story->excerpt ?? Excerpt::from($story->body),
            'image' => $story->featuredImage(),
            'tags' => $story->tags->map(fn ($tag): array => ['slug' => $tag->slug, 'name' => $tag->name])->values()->all(),
            'noindex' => $story->noindex,
            'published_at' => $story->published_at?->toIso8601String(),
        ];
    }
}
