<?php

namespace App\Http\Resources\V1;

use App\Models\Event;
use Illuminate\Http\Request;

/**
 * A full event for the detail page (docs/plan/03-api-spec.md "Event").
 *
 * @property-read Event $resource
 */
class EventResource extends EventCardResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $event = $this->resource;
        $hasAddress = filled($event->address_line1) || filled($event->city) || filled($event->postal_code);

        return [
            ...parent::toArray($request),
            'description' => $event->description,
            'address' => $hasAddress ? [
                'line1' => $event->address_line1,
                'line2' => $event->address_line2,
                'city' => $event->city,
                'province' => $event->province,
                'postal_code' => $event->postal_code,
            ] : null,
            'online_url' => $event->online_url,
            'registration_url' => $event->registration_url,
            'meta_title' => $event->meta_title,
            'meta_description' => $event->meta_description,
            'updated_at' => $event->updated_at?->toIso8601String(),
        ];
    }
}
