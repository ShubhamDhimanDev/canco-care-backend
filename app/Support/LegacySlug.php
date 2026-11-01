<?php

namespace App\Support;

/**
 * Reproduces the slug algorithm of the legacy cancocares.ca front end so that the
 * URLs of migrated stories (e.g. /stories/canco-delivering-wishes-) keep working.
 *
 * Legacy rule: lowercase, drop everything except [a-z0-9] and spaces, then turn
 * every run of whitespace into a single hyphen. It does not trim, which is why a
 * title ending in "!!" produces a trailing hyphen.
 */
final class LegacySlug
{
    public static function fromTitle(string $title): string
    {
        $slug = strtolower($title);
        $slug = (string) preg_replace('/[^a-z0-9 ]/', '', $slug);

        return (string) preg_replace('/\s+/', '-', $slug);
    }
}
