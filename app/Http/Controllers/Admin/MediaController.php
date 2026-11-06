<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadMediaRequest;
use App\Jobs\RevalidateFrontend;
use App\Models\Media;
use App\Services\ImageConversionException;
use App\Services\MediaService;
use App\Services\MediaUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    private const PER_PAGE = 24;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Media::class);

        $search = trim((string) $request->query('search', ''));
        $onlyLegacy = $request->query('format') === 'other';

        $media = $this->query($search)
            ->when($onlyLegacy, fn (Builder $q) => $q->where('mime', '!=', 'image/webp'))
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Media $item): array => $this->item($item));

        return Inertia::render('media/index', [
            'media' => $media,
            'filters' => ['search' => $search, 'format' => $onlyLegacy ? 'other' : 'all'],
            'counts' => [
                'all' => Media::count(),
                'other' => Media::where('mime', '!=', 'image/webp')->count(),
            ],
            'canConvert' => MediaService::canConvert(),
        ]);
    }

    /**
     * JSON for the picker dialog used by the story, event and home-page forms.
     */
    public function library(Request $request): JsonResponse
    {
        Gate::authorize('pick', Media::class);

        $page = $this->query(trim((string) $request->query('search', '')))->paginate(self::PER_PAGE);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Media $item): array => $this->item($item))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function store(UploadMediaRequest $request, MediaService $service): JsonResponse
    {
        Gate::authorize('upload', Media::class);

        try {
            $media = $service->store($request->file('file'), $request->user(), $request->input('alt'));
        } catch (ImageConversionException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        return response()->json(['data' => $this->item($media)], 201);
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        Gate::authorize('update', $media);

        $data = $request->validate(['alt' => ['nullable', 'string', 'max:200']]);

        $media->update(['alt' => filled($data['alt'] ?? null) ? $data['alt'] : null]);

        // The description may be shown wherever this picture is used.
        RevalidateFrontend::fire(['stories', 'events', 'settings']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Image description saved.')]);

        return back();
    }

    public function convert(Media $media, MediaService $service): RedirectResponse
    {
        Gate::authorize('update', $media);

        try {
            $service->convertExisting($media);
        } catch (ImageConversionException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Converted to WebP.')]);

        return back();
    }

    public function destroy(Media $media, MediaService $service, MediaUsage $usage): RedirectResponse
    {
        Gate::authorize('delete', $media);

        $uses = $usage->for($media);

        if ($uses !== []) {
            $first = $uses[0]['label'];
            $more = count($uses) > 1 ? ' and '.(count($uses) - 1).' more' : '';

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This image is used by “:first”:more, so it cannot be deleted. Remove it there first.', ['first' => $first, 'more' => $more]),
            ]);

            return back();
        }

        $service->delete($media);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Image deleted.')]);

        return back();
    }

    /**
     * @return Builder<Media>
     */
    private function query(string $search): Builder
    {
        return Media::query()
            ->when($search !== '', fn (Builder $q) => $q->where(
                fn (Builder $q) => $q->where('original_name', 'like', "%{$search}%")->orWhere('alt', 'like', "%{$search}%")
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function item(Media $media): array
    {
        return [
            'id' => $media->id,
            'name' => $media->original_name,
            'url' => $media->url(),
            'thumb_url' => $media->thumbUrl(),
            'path' => $media->path,
            'width' => $media->width,
            'height' => $media->height,
            'size_bytes' => $media->size_bytes,
            'alt' => $media->alt,
            'is_webp' => $media->isWebp(),
            'created_at' => $media->created_at?->toIso8601String(),
        ];
    }
}
