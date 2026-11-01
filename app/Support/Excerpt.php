<?php

namespace App\Support;

/**
 * Builds a plain-text excerpt from HTML.
 *
 * Block boundaries and <br> become spaces, so "family!<br>In early" reads
 * "family! In early" (the legacy site glued these together).
 */
final class Excerpt
{
    public static function from(string $html, int $limit = 160): string
    {
        $text = (string) preg_replace('#<\s*br\s*/?\s*>|</\s*(p|div|li|h[1-6]|blockquote|tr)\s*>#i', ' ', $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit);
        $atWord = (string) preg_replace('/\s+\S*$/u', '', $cut);

        // A single very long word has no boundary to cut on; fall back to the hard cut.
        $cut = $atWord !== '' ? $atWord : $cut;

        return rtrim($cut, " \t\n\r\0\x0B,.;:-–—").'…';
    }
}
