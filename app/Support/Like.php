<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * "Contains" searches for what a visitor typed. The % and _ they type are plain characters, not
 * wildcards, and the escape character is declared explicitly (`ESCAPE '!'`) so it behaves the same on
 * MySQL and SQLite (SQLite has no default escape character).
 */
final class Like
{
    /** The longest search text that is used; the rest is ignored. */
    public const MAX_LENGTH = 80;

    /**
     * What the visitor typed, trimmed and cut, or null when there is nothing to search for.
     */
    public static function text(mixed $value): ?string
    {
        $text = is_string($value) ? trim(mb_substr($value, 0, self::MAX_LENGTH)) : '';

        return $text === '' ? null : $text;
    }

    /**
     * Keep only rows where any of the (code-defined) columns contains the text.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<literal-string>  $columns
     * @return Builder<TModel>
     */
    public static function anyColumn(Builder $query, array $columns, string $text): Builder
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text).'%';

        return $query->where(function (Builder $inner) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $inner->orWhereRaw("{$column} like ? escape '!'", [$pattern]);
            }
        });
    }
}
