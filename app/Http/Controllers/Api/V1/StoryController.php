<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\StoryCardResource;
use App\Http\Resources\V1\StoryResource;
use App\Models\SlugRedirect;
use App\Models\Story;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StoryController extends Controller
{
    private const DEFAULT_PER_PAGE = 9;

    private const MAX_PER_PAGE = 24;

    /**
     * Public stories, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // Non-numeric input falls back to the default; numbers are clamped to 1..MAX.
        $requested = filter_var($request->query('per_page'), FILTER_VALIDATE_INT);
        $perPage = $requested === false ? self::DEFAULT_PER_PAGE : min(max($requested, 1), self::MAX_PER_PAGE);

        $tag = $request->query('tag');
        $search = Like::text($request->query('search'));

        return StoryCardResource::collection(
            Story::published()
                ->with('tags')
                ->when($search !== null, fn ($query) => Like::anyColumn($query, ['title', 'excerpt', 'summary'], $search))
                ->when(is_string($tag) && $tag !== '', fn ($query) => $query->whereHas('tags', fn ($q) => $q->where('slug', $tag)))
                // The home page's "Latest Stories": stories marked "feature on the home page" first.
                ->when($request->boolean('featured_first'), fn ($query) => $query->orderByDesc('is_featured'))
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate($perPage)
                ->withQueryString()
        );
    }

    /**
     * One public story. A renamed story's old slug answers 301 with the new slug
     * so the frontend can issue a permanent redirect.
     */
    public function show(string $slug): StoryResource|JsonResponse
    {
        $story = Story::published()->with('tags')->where('slug', $slug)->first();

        if ($story !== null) {
            return new StoryResource($story);
        }

        $moved = SlugRedirect::where('redirectable_type', 'story')->where('old_slug', $slug)->first();
        $target = $moved === null ? null : Story::published()->find($moved->redirectable_id);

        if ($target !== null) {
            return response()->json(['redirect_to' => $target->slug], 301);
        }

        abort(404, 'Story not found.');
    }
}
