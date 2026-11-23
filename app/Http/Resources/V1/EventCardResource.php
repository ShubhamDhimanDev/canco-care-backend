<?php

namespace App\Http\Resources\V1;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Event as shown on listing cards (docs/plan/03-api-spec.md "EventCard").
 *
 * @property-read Event $resource
 */
class EventCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $event = $this->resource;

        return [
            'slug' => $event->slug,
            'title' => $event->title,
            'summary' => $event->summary,
            'image' => $event->image(),
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            // The organiser's wall-clock time (no offset): print it as is. The UTC instants above are for sorting and calendars.
            'starts_local' => str_replace(' ', 'T', $event->startsLocal()),
            'ends_local' => $event->endsLocal() === null ? null : str_replace(' ', 'T', $event->endsLocal()),
            'timezone' => $event->timezone,
            'timezone_label' => $event->timezoneLabel(),
            'is_all_day' => $event->is_all_day,
            'location_name' => $event->location_name,
            'city' => $event->city,
            'province' => $event->province,
            'is_online' => $event->isOnline(),
            'cost_note' => $event->cost_note,
            'status' => $event->status->value,
            'is_featured' => $event->is_featured,
        ];
    }
}
