<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Models\Concerns\Audited;
use App\Observers\EventObserver;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $summary
 * @property string|null $description
 * @property string|null $image_path
 * @property string|null $image_alt
 * @property int|null $image_width
 * @property int|null $image_height
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property string $timezone
 * @property bool $is_all_day
 * @property string|null $location_name
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $city
 * @property string|null $province
 * @property string|null $postal_code
 * @property string|null $online_url
 * @property string|null $registration_url
 * @property string|null $cost_note
 * @property EventStatus $status
 * @property Carbon|null $published_at
 * @property bool $is_featured
 * @property bool $show_in_donation_form
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'title', 'slug', 'summary', 'description',
    'image_path', 'image_alt', 'image_width', 'image_height',
    'starts_at', 'ends_at', 'timezone', 'is_all_day',
    'location_name', 'address_line1', 'address_line2', 'city', 'province', 'postal_code',
    'online_url', 'registration_url', 'cost_note',
    'status', 'published_at', 'is_featured', 'show_in_donation_form',
    'meta_title', 'meta_description', 'created_by', 'updated_by',
])]
#[ObservedBy(EventObserver::class)]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use Audited, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'is_all_day' => 'boolean',
            'is_featured' => 'boolean',
            'show_in_donation_form' => 'boolean',
        ];
    }

    /**
     * What an editor sees: trashed, draft, scheduled, cancelled, upcoming, past.
     */
    public function publicationState(): string
    {
        return match (true) {
            $this->trashed() => 'trashed',
            $this->status === EventStatus::Draft => 'draft',
            $this->status === EventStatus::Cancelled => 'cancelled',
            $this->published_at === null => 'draft',
            $this->published_at->isFuture() => 'scheduled',
            $this->hasEnded() => 'past',
            default => 'published',
        };
    }

    /**
     * An event with no end time is over when its start has passed (all-day: at the end of that day).
     */
    public function hasEnded(): bool
    {
        $end = $this->ends_at ?? ($this->is_all_day ? $this->starts_at->copy()->endOfDay() : $this->starts_at);

        return $end->isPast();
    }

    /**
     * @return MorphMany<SlugRedirect, $this>
     */
    public function redirects(): MorphMany
    {
        return $this->morphMany(SlugRedirect::class, 'redirectable');
    }

    /**
     * @return array{url: string, alt: string|null, width: int|null, height: int|null}|null
     */
    public function image(): ?array
    {
        if ($this->image_path === null) {
            return null;
        }

        return [
            'url' => Storage::disk('public')->url($this->image_path),
            'alt' => $this->image_alt,
            'width' => $this->image_width,
            'height' => $this->image_height,
        ];
    }

    public function isOnline(): bool
    {
        return filled($this->online_url);
    }

    /**
     * Events visible on the website: published or cancelled (cancelled stay, flagged), and not
     * scheduled for later.
     *
     * @param  Builder<Event>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->whereIn('status', [EventStatus::Published->value, EventStatus::Cancelled->value])
            ->where('published_at', '<=', now());
    }

    /**
     * Not over yet: its end (or its start, when it has no end) is still ahead.
     *
     * @param  Builder<Event>  $query
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->whereRaw('COALESCE(ends_at, starts_at) >= ?', [now()->utc()->toDateTimeString()]);
    }

    /**
     * @param  Builder<Event>  $query
     */
    #[Scope]
    protected function past(Builder $query): void
    {
        $query->whereRaw('COALESCE(ends_at, starts_at) < ?', [now()->utc()->toDateTimeString()]);
    }

    public function auditType(): string
    {
        return 'event';
    }

    public function auditLabel(): string
    {
        return $this->title;
    }
}
