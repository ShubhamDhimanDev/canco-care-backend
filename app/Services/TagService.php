<?php

namespace App\Services;

use App\Models\Story;
use App\Models\Tag;
use Illuminate\Support\Str;

/**
 * Turns the tag names an editor typed into rows, and attaches them to a story. Two names that make
 * the same address ("Make-A-Wish" and "make a wish") are one tag; the first spelling wins.
 */
class TagService
{
    public const MAX_PER_STORY = 10;

    /**
     * @param  array<int, mixed>  $names
     */
    public function sync(Story $story, array $names): void
    {
        $story->tags()->sync($this->idsFor($names));
    }

    /**
     * @param  array<int, mixed>  $names
     * @return list<int>
     */
    public function idsFor(array $names): array
    {
        $ids = [];

        foreach ($names as $raw) {
            $name = trim((string) preg_replace('/\s+/', ' ', is_scalar($raw) ? (string) $raw : ''));
            $slug = Str::limit(Str::slug($name), 60, '');

            if ($name === '' || $slug === '') {
                continue;
            }

            $tag = Tag::firstOrCreate(['slug' => $slug], ['name' => Str::limit($name, 40, '')]);
            $ids[$tag->id] = $tag->id;
        }

        return array_slice(array_values($ids), 0, self::MAX_PER_STORY);
    }
}
