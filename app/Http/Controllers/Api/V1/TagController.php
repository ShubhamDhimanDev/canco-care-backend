<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;

class TagController extends Controller
{
    /**
     * Tags that have at least one public story, most used first (docs/plan/03-api-spec.md "GET /tags").
     */
    public function index(): JsonResponse
    {
        $tags = Tag::query()
            ->withCount(['stories as stories_count' => fn ($query) => $query->published()])
            ->get()
            ->filter(fn (Tag $tag): bool => (int) $tag->getAttribute('stories_count') > 0)
            ->sortBy([['stories_count', 'desc'], ['name', 'asc']])
            ->map(fn (Tag $tag): array => [
                'slug' => $tag->slug,
                'name' => $tag->name,
                'count' => (int) $tag->getAttribute('stories_count'),
            ])
            ->values();

        return response()->json(['data' => $tags]);
    }
}
