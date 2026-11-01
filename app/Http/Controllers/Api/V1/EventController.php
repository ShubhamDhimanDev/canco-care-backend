<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\EventCardResource;
use App\Http\Resources\V1\EventResource;
use App\Models\Event;
use App\Models\SlugRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    private const DEFAULT_PER_PAGE = 9;

    private const MAX_PER_PAGE = 24;

    /**
     * Public events. `scope=upcoming` (default) soonest first, `past` and `all` newest first.
     * `featured_first=1` pins featured events to the top of the upcoming list.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $requested = filter_var($request->query('per_page'), FILTER_VALIDATE_INT);
        $perPage = $requested === false ? self::DEFAULT_PER_PAGE : min(max($requested, 1), self::MAX_PER_PAGE);

        $scope = in_array($request->query('scope'), ['upcoming', 'past', 'all'], true) ? $request->query('scope') : 'upcoming';

        $query = Event::visible()
            ->when($scope === 'upcoming', fn ($q) => $q->upcoming())
            ->when($scope === 'past', fn ($q) => $q->past());

        if ($scope === 'upcoming') {
            $query->when($request->boolean('featured_first'), fn ($q) => $q->orderByDesc('is_featured'))
                ->orderBy('starts_at');
        } else {
            $query->orderByDesc('starts_at');
        }

        return EventCardResource::collection($query->orderBy('id')->paginate($perPage)->withQueryString());
    }

    /**
     * One public event. A renamed event's old slug answers 301 with the new slug.
     */
    public function show(string $slug): EventResource|JsonResponse
    {
        $event = Event::visible()->where('slug', $slug)->first();

        if ($event !== null) {
            return new EventResource($event);
        }

        $moved = SlugRedirect::where('redirectable_type', 'event')->where('old_slug', $slug)->first();
        $target = $moved === null ? null : Event::visible()->find($moved->redirectable_id);

        if ($target !== null) {
            return response()->json(['redirect_to' => $target->slug], 301);
        }

        abort(404, 'Event not found.');
    }
}
