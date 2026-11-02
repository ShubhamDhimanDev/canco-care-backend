<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EventRequest;
use App\Models\Event;
use App\Services\EventWriter;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    private const STATES = ['all', 'upcoming', 'past', 'draft', 'trashed'];

    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Event::class);

        $state = in_array($request->query('status'), self::STATES, true) ? (string) $request->query('status') : 'all';
        $search = trim((string) $request->query('search', ''));

        $events = Event::query()
            ->when($state === 'trashed', fn (Builder $q) => $q->onlyTrashed())
            ->when($state === 'draft', fn (Builder $q) => $this->drafts($q))
            ->when($state === 'upcoming', fn (Builder $q) => $this->notDraft($q)->upcoming())
            ->when($state === 'past', fn (Builder $q) => $this->notDraft($q)->past())
            ->when($search !== '', fn (Builder $q) => $q->where(
                fn (Builder $q) => $q->where('title', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%")
            ))
            // Upcoming reads soonest first; everything else newest first.
            ->when($state === 'upcoming', fn (Builder $q) => $q->orderBy('starts_at'), fn (Builder $q) => $q->orderByDesc('starts_at'))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Event $event): array => $this->row($event));

        return Inertia::render('events/index', [
            'events' => $events,
            'filters' => ['status' => $state, 'search' => $search],
            'counts' => [
                'all' => Event::count(),
                'upcoming' => $this->notDraft(Event::query())->upcoming()->count(),
                'past' => $this->notDraft(Event::query())->past()->count(),
                'draft' => $this->drafts(Event::query())->count(),
                'trashed' => Event::onlyTrashed()->count(),
            ],
            'can' => ['forceDelete' => (bool) $request->user()?->isAdmin()],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Event::class);

        return Inertia::render('events/create', [
            'timezones' => $this->timezones(null),
            'siteUrl' => rtrim((string) config('services.frontend.url'), '/'),
        ]);
    }

    public function store(EventRequest $request, EventWriter $writer): RedirectResponse
    {
        Gate::authorize('create', Event::class);

        $event = $writer->create($request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event created.')]);

        return to_route('events.edit', $event);
    }

    public function edit(Event $event): Response
    {
        Gate::authorize('update', $event);

        $siteUrl = rtrim((string) config('services.frontend.url'), '/');
        $local = fn (?\DateTimeInterface $time, string $format): ?string => $time === null
            ? null
            : CarbonImmutable::instance($time)->setTimezone($event->timezone)->format($format);
        $format = $event->is_all_day ? 'Y-m-d' : 'Y-m-d\TH:i';

        return Inertia::render('events/edit', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'summary' => $event->summary,
                'description' => $event->description ?? '',
                'image' => $event->image(),
                'timezone' => $event->timezone,
                'is_all_day' => $event->is_all_day,
                'starts_at_local' => $local($event->starts_at, $format),
                'ends_at_local' => $local($event->ends_at, $format),
                'location_name' => $event->location_name,
                'address_line1' => $event->address_line1,
                'address_line2' => $event->address_line2,
                'city' => $event->city,
                'province' => $event->province,
                'postal_code' => $event->postal_code,
                'online_url' => $event->online_url,
                'registration_url' => $event->registration_url,
                'cost_note' => $event->cost_note,
                'status' => $event->status->value,
                'state' => $event->publicationState(),
                'published_at' => $event->published_at?->toIso8601String(),
                'is_featured' => $event->is_featured,
                'show_in_donation_form' => $event->show_in_donation_form,
                'meta_title' => $event->meta_title,
                'meta_description' => $event->meta_description,
                'updated_at' => $event->updated_at?->toIso8601String(),
                'public_url' => $siteUrl === '' ? null : "{$siteUrl}/upcoming-events/{$event->slug}",
            ],
            'timezones' => $this->timezones($event->timezone),
            'siteUrl' => $siteUrl,
        ]);
    }

    public function update(EventRequest $request, Event $event, EventWriter $writer): RedirectResponse
    {
        Gate::authorize('update', $event);

        $writer->update($event, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event saved.')]);

        return to_route('events.edit', $event);
    }

    public function destroy(Event $event): RedirectResponse
    {
        Gate::authorize('delete', $event);

        $event->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event moved to the trash.')]);

        return to_route('events.index');
    }

    public function restore(Event $event): RedirectResponse
    {
        Gate::authorize('restore', $event);

        $event->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event restored.')]);

        return to_route('events.index', ['status' => 'trashed']);
    }

    public function forceDelete(Event $event): RedirectResponse
    {
        Gate::authorize('forceDelete', $event);

        $event->redirects()->delete();
        $event->forceDelete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event deleted permanently.')]);

        return to_route('events.index', ['status' => 'trashed']);
    }

    /**
     * Time zones an organiser can pick: every Canadian zone, plus the event's own if it is elsewhere.
     *
     * @return list<string>
     */
    private function timezones(?string $current): array
    {
        $zones = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, 'CA');

        if ($current !== null && ! in_array($current, $zones, true)) {
            $zones[] = $current;
        }

        return $zones;
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    private function drafts(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('status', EventStatus::Draft->value)
            ->orWhereNull('published_at'));
    }

    /**
     * Anything an editor has taken beyond a draft (published, scheduled or cancelled).
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    private function notDraft(Builder $query): Builder
    {
        return $query->where('status', '!=', EventStatus::Draft->value)->whereNotNull('published_at');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Event $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'state' => $event->publicationState(),
            'starts_at' => $event->starts_at->toIso8601String(),
            'timezone' => $event->timezone,
            'is_all_day' => $event->is_all_day,
            'place' => $event->location_name ?: ($event->city ?: ($event->isOnline() ? 'Online' : null)),
            'is_featured' => $event->is_featured,
            'show_in_donation_form' => $event->show_in_donation_form,
            'image_url' => $event->image()['url'] ?? null,
        ];
    }
}
