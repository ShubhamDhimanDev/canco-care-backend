<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Media;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The single place where the event form becomes a stored event: sanitising, slug and redirects,
 * the wall-clock → UTC conversion, publish date and image. Used by the admin controller so create
 * and update cannot drift apart.
 */
class EventWriter
{
    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
        private readonly SlugService $slugs,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated EventRequest input
     */
    public function create(array $data, User $user): Event
    {
        $event = new Event;
        $event->created_by = $user->id;

        return $this->persist($event, $data, $user);
    }

    /**
     * @param  array<string, mixed>  $data  validated EventRequest input
     */
    public function update(Event $event, array $data, User $user): Event
    {
        return $this->persist($event, $data, $user);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(Event $event, array $data, User $user): Event
    {
        $oldSlug = $event->exists ? $event->slug : null;

        $slug = $data['slug'] ?? null;
        if (blank($slug)) {
            $slug = $event->exists ? $event->slug : $this->slugs->forTitle((string) $data['title'], null, Event::class);
        }

        $status = EventStatus::from((string) $data['status']);
        $publishedAt = $data['published_at'] ?? null;
        if ($status !== EventStatus::Draft && blank($publishedAt)) {
            $publishedAt = $event->published_at ?? now();
        }

        $timezone = (string) $data['timezone'];
        $allDay = (bool) ($data['is_all_day'] ?? false);

        $event->fill([
            'title' => $data['title'],
            'slug' => $slug,
            'summary' => $data['summary'],
            'description' => $this->description($data['description'] ?? null),
            'timezone' => $timezone,
            'is_all_day' => $allDay,
            'starts_at' => $this->toUtc((string) $data['starts_at_local'], $timezone, $allDay, false),
            'ends_at' => blank($data['ends_at_local'] ?? null) ? null : $this->toUtc((string) $data['ends_at_local'], $timezone, $allDay, true),
            'location_name' => $data['location_name'] ?? null,
            'address_line1' => $data['address_line1'] ?? null,
            'address_line2' => $data['address_line2'] ?? null,
            'city' => $data['city'] ?? null,
            'province' => $data['province'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'online_url' => $data['online_url'] ?? null,
            'registration_url' => $data['registration_url'] ?? null,
            'cost_note' => $data['cost_note'] ?? null,
            'status' => $status,
            'published_at' => blank($publishedAt) ? null : $publishedAt,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'show_in_donation_form' => (bool) ($data['show_in_donation_form'] ?? false),
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'updated_by' => $user->id,
        ]);

        $this->applyImage($event, $data);

        DB::transaction(function () use ($event, $oldSlug): void {
            $event->save();

            if ($oldSlug !== null && $oldSlug !== $event->slug && $event->published_at !== null) {
                $this->slugs->recordRename($event, $oldSlug);
            }
        });

        return $event;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyImage(Event $event, array $data): void
    {
        if (filled($data['image_media_id'] ?? null)) {
            $media = Media::findOrFail((int) $data['image_media_id']);

            $event->fill([
                'image_path' => $media->path,
                'image_width' => $media->width,
                'image_height' => $media->height,
            ]);
        } elseif ((bool) ($data['remove_image'] ?? false)) {
            $event->fill(['image_path' => null, 'image_alt' => null, 'image_width' => null, 'image_height' => null]);
        }

        if ($event->image_path !== null) {
            $event->image_alt = $data['image_alt'] ?? $event->image_alt;
        }
    }

    private function description(mixed $html): ?string
    {
        if (! is_string($html) || trim($html) === '') {
            return null;
        }

        $clean = $this->sanitizer->sanitize($html);

        return trim(html_entity_decode(strip_tags($clean))) === '' ? null : $clean;
    }

    /**
     * The organiser's wall-clock time in the event's zone, as a UTC instant. An all-day event starts
     * at the beginning of its day and (when it has an end) ends at the end of that day.
     */
    private function toUtc(string $local, string $timezone, bool $allDay, bool $isEnd): CarbonImmutable
    {
        $time = CarbonImmutable::parse($local, $timezone);

        if ($allDay) {
            $time = $isEnd ? $time->endOfDay() : $time->startOfDay();
        }

        return $time->utc();
    }
}
