<?php

namespace App\Services;

use App\Models\SlugRedirect;
use App\Models\Story;
use Illuminate\Support\Str;

/**
 * Stable, unique, URL-safe slugs for stories, plus the redirect bookkeeping that keeps
 * old URLs working after a rename.
 *
 * A slug is "taken" if any story (even a deleted one) uses it, or if it is the old
 * address of a different story — handing it out again would hijack that redirect.
 */
class SlugService
{
    private const MAX_LENGTH = 200; // column is 220; leaves room for a "-123" suffix

    public function forTitle(string $title, ?Story $ignore = null): string
    {
        $base = Str::limit(Str::slug($title), self::MAX_LENGTH, '');
        $base = rtrim($base, '-');
        $base = $base === '' ? 'story' : $base;

        $slug = $base;
        $suffix = 2;

        while ($this->isTaken($slug, $ignore)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public function isTaken(string $slug, ?Story $ignore = null): bool
    {
        $ignoreId = $ignore?->exists ? $ignore->getKey() : null;

        $usedByStory = Story::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        $usedByRedirect = SlugRedirect::where('redirectable_type', (new Story)->getMorphClass())
            ->where('old_slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->where('redirectable_id', '!=', $ignoreId))
            ->exists();

        return $usedByStory || $usedByRedirect;
    }

    /**
     * Call after a story that has been (or was meant to be) public changes slug.
     */
    public function recordRename(Story $story, string $oldSlug): void
    {
        $type = $story->getMorphClass();

        SlugRedirect::updateOrCreate(
            ['redirectable_type' => $type, 'old_slug' => $oldSlug],
            ['redirectable_id' => $story->getKey()],
        );

        // The story's current address must never also be a redirect.
        SlugRedirect::where('redirectable_type', $type)->where('old_slug', $story->slug)->delete();
    }
}
