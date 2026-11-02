<?php

namespace App\Services;

use App\Models\Event;
use App\Models\SlugRedirect;
use App\Models\Story;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Stable, unique, URL-safe slugs for stories and events, plus the redirect bookkeeping that keeps
 * old URLs working after a rename.
 *
 * A slug is "taken" if any record of the same kind (even a deleted one) uses it, or if it is the old
 * address of a different record — handing it out again would hijack that redirect.
 */
class SlugService
{
    private const MAX_LENGTH = 200; // column is 220; leaves room for a "-123" suffix

    /**
     * @param  class-string<Story|Event>  $model
     */
    public function forTitle(string $title, ?Model $ignore = null, string $model = Story::class): string
    {
        $fallback = $model === Event::class ? 'event' : 'story';

        $base = Str::limit(Str::slug($title), self::MAX_LENGTH, '');
        $base = rtrim($base, '-');
        $base = $base === '' ? $fallback : $base;

        $slug = $base;
        $suffix = 2;

        while ($this->isTaken($slug, $ignore, $model)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @param  class-string<Story|Event>  $model
     */
    public function isTaken(string $slug, ?Model $ignore = null, string $model = Story::class): bool
    {
        $ignoreId = $ignore?->exists ? $ignore->getKey() : null;

        $usedByRecord = $model::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        $usedByRedirect = SlugRedirect::where('redirectable_type', (new $model)->getMorphClass())
            ->where('old_slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->where('redirectable_id', '!=', $ignoreId))
            ->exists();

        return $usedByRecord || $usedByRedirect;
    }

    /**
     * Call after a record that has been (or was meant to be) public changes slug.
     */
    public function recordRename(Story|Event $record, string $oldSlug): void
    {
        $type = $record->getMorphClass();

        SlugRedirect::updateOrCreate(
            ['redirectable_type' => $type, 'old_slug' => $oldSlug],
            ['redirectable_id' => $record->getKey()],
        );

        // The record's current address must never also be a redirect.
        SlugRedirect::where('redirectable_type', $type)->where('old_slug', $record->slug)->delete();
    }
}
