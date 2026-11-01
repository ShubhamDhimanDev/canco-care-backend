<?php

namespace App\Http\Resources\V1;

use App\Models\Story;
use Illuminate\Http\Request;

/**
 * A full story for the detail page (docs/plan/03-api-spec.md "Story").
 * "prev" is the next-older public story, "next" the next-newer one.
 *
 * @property-read Story $resource
 */
class StoryResource extends StoryCardResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $story = $this->resource;
        $link = fn (?Story $other): ?array => $other === null ? null : ['slug' => $other->slug, 'title' => $other->title];

        return [
            ...parent::toArray($request),
            'body' => $story->body,
            'author_name' => $story->author_name,
            'meta_title' => $story->meta_title,
            'meta_description' => $story->meta_description,
            'updated_at' => $story->updated_at?->toIso8601String(),
            'prev' => $link($story->older()),
            'next' => $link($story->newer()),
        ];
    }
}
