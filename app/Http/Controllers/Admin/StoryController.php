<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StoryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoryRequest;
use App\Models\Story;
use App\Models\Tag;
use App\Services\StoryImageService;
use App\Services\StoryWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StoryController extends Controller
{
    private const STATES = ['all', 'published', 'scheduled', 'draft', 'trashed'];

    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Story::class);

        $state = in_array($request->query('status'), self::STATES, true) ? (string) $request->query('status') : 'all';
        $search = trim((string) $request->query('search', ''));

        $stories = Story::query()
            ->when($state === 'trashed', fn (Builder $q) => $q->onlyTrashed())
            ->when($state === 'draft', fn (Builder $q) => $this->drafts($q))
            ->when($state === 'published', fn (Builder $q) => $q->published())
            ->when($state === 'scheduled', fn (Builder $q) => $this->scheduled($q))
            ->when($search !== '', fn (Builder $q) => $q->where(
                fn (Builder $q) => $q->where('title', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")
            ))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Story $story): array => $this->row($story));

        return Inertia::render('stories/index', [
            'stories' => $stories,
            'filters' => ['status' => $state, 'search' => $search],
            'counts' => [
                'all' => Story::count(),
                'published' => Story::published()->count(),
                'scheduled' => $this->scheduled(Story::query())->count(),
                'draft' => $this->drafts(Story::query())->count(),
                'trashed' => Story::onlyTrashed()->count(),
            ],
            'can' => ['forceDelete' => (bool) $request->user()?->isAdmin()],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Story::class);

        return Inertia::render('stories/create', [
            'allTags' => $this->tagNames(),
            'siteUrl' => rtrim((string) config('services.frontend.url'), '/'),
        ]);
    }

    public function store(StoryRequest $request, StoryWriter $writer): RedirectResponse
    {
        Gate::authorize('create', Story::class);

        $story = $writer->create($request->validated(), $request->file('featured_image'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Story created.')]);

        return to_route('stories.edit', $story);
    }

    public function edit(Story $story): Response
    {
        Gate::authorize('update', $story);

        $siteUrl = rtrim((string) config('services.frontend.url'), '/');
        $story->load('tags');

        return Inertia::render('stories/edit', [
            'story' => [
                'id' => $story->id,
                'title' => $story->title,
                'slug' => $story->slug,
                'excerpt' => $story->excerpt,
                'summary' => $story->summary,
                'faq' => $story->faq ?? [],
                'tags' => $story->tags->pluck('name')->all(),
                'is_featured' => $story->is_featured,
                'noindex' => $story->noindex,
                'body' => $story->body,
                'image' => $story->featuredImage(),
                'status' => $story->status->value,
                'state' => $story->publicationState(),
                'published_at' => $story->published_at?->toIso8601String(),
                'meta_title' => $story->meta_title,
                'meta_description' => $story->meta_description,
                'author_name' => $story->author_name,
                'updated_at' => $story->updated_at?->toIso8601String(),
                'public_url' => $siteUrl === '' ? null : "{$siteUrl}/stories/{$story->slug}",
            ],
            'allTags' => $this->tagNames(),
            'siteUrl' => $siteUrl,
        ]);
    }

    public function update(StoryRequest $request, Story $story, StoryWriter $writer): RedirectResponse
    {
        Gate::authorize('update', $story);

        $writer->update(
            $story,
            $request->validated(),
            $request->file('featured_image'),
            $request->boolean('remove_image'),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Story saved.')]);

        return to_route('stories.edit', $story);
    }

    public function destroy(Story $story): RedirectResponse
    {
        Gate::authorize('delete', $story);

        $story->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Story moved to the trash.')]);

        return to_route('stories.index');
    }

    public function restore(Story $story): RedirectResponse
    {
        Gate::authorize('restore', $story);

        $story->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Story restored.')]);

        return to_route('stories.index', ['status' => 'trashed']);
    }

    public function forceDelete(Story $story, StoryImageService $images): RedirectResponse
    {
        Gate::authorize('forceDelete', $story);

        $imagePath = $story->featured_image_path;

        $story->redirects()->delete();
        $story->forceDelete();
        $images->delete($imagePath);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Story deleted permanently.')]);

        return to_route('stories.index', ['status' => 'trashed']);
    }

    /**
     * Every tag name, for the tag field's suggestions.
     *
     * @return list<string>
     */
    private function tagNames(): array
    {
        return array_values(Tag::query()->orderBy('name')->get(['name'])->map(fn (Tag $tag): string => $tag->name)->all());
    }

    /**
     * @param  Builder<Story>  $query
     * @return Builder<Story>
     */
    private function drafts(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('status', StoryStatus::Draft->value)
            ->orWhereNull('published_at'));
    }

    /**
     * @param  Builder<Story>  $query
     * @return Builder<Story>
     */
    private function scheduled(Builder $query): Builder
    {
        return $query->where('status', StoryStatus::Published->value)->where('published_at', '>', now());
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Story $story): array
    {
        return [
            'id' => $story->id,
            'title' => $story->title,
            'slug' => $story->slug,
            'state' => $story->publicationState(),
            'published_at' => $story->published_at?->toIso8601String(),
            'updated_at' => $story->updated_at?->toIso8601String(),
            'image_url' => $story->featuredImage()['url'] ?? null,
            'is_featured' => $story->is_featured,
        ];
    }
}
