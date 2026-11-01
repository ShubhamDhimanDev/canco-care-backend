<?php

namespace App\Services;

use App\Enums\StoryStatus;
use App\Models\Story;
use App\Support\Excerpt;
use App\Support\LegacySlug;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Imports the stories that the legacy site served from the Canco Petroleum blog API.
 *
 * Safe to re-run: stories that already exist (matched on legacy_id) are left alone
 * unless $update is true, so editor changes are never overwritten by accident, and
 * stories an editor deleted are never resurrected.
 */
class LegacyStoryImporter
{
    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    /**
     * @return list<array{legacy_id: int, title: string, slug: string, result: string, notes: list<string>}>
     */
    public function import(
        string $sourcePath,
        string $imagesPath,
        ?string $altPath = null,
        bool $update = false,
        bool $dryRun = false,
    ): array {
        $items = $this->readJson($sourcePath);
        $alts = $altPath === null ? [] : $this->readJson($altPath);

        $items = array_values($items);

        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['id'], $item['title'], $item['content'], $item['publishDateTime'])) {
                throw new InvalidArgumentException("Each story in [{$sourcePath}] needs id, title, content and publishDateTime.");
            }
        }

        $dates = $this->planPublishDates($items);
        $results = [];

        foreach ($items as $index => $item) {
            $results[] = $this->importOne($item, $dates[$index], $imagesPath, $alts, $update, $dryRun);
        }

        return $results;
    }

    /**
     * The legacy API lists stories in the order the old site displayed them, but several share
     * (nearly) the same publish time, so sorting by date alone would reshuffle the page. Keep the
     * display order under "newest first" by making each date strictly earlier than the one before
     * it, moving a date by whole seconds only where the data disagrees with the listing.
     *
     * @param  array<int, mixed>  $items
     * @return list<array{at: CarbonInterface, note: string|null}>
     */
    private function planPublishDates(array $items): array
    {
        $plan = [];
        $previous = null;

        foreach ($items as $item) {
            /** @var array<string, mixed> $item */
            $at = $this->toAppTimezone((string) $item['publishDateTime'])->startOfSecond();
            $note = null;

            if ($previous !== null && $at >= $previous) {
                $at = $previous->subSecond();
                $note = 'published_at moved to keep the live listing order';
            }

            $plan[] = ['at' => $at, 'note' => $note];
            $previous = $at;
        }

        return $plan;
    }

    /**
     * @param  array<int|string, mixed>  $item
     * @param  array{at: CarbonInterface, note: string|null}  $publishPlan
     * @param  array<int|string, mixed>  $alts
     * @return array{legacy_id: int, title: string, slug: string, result: string, notes: list<string>}
     */
    private function importOne(array $item, array $publishPlan, string $imagesPath, array $alts, bool $update, bool $dryRun): array
    {
        $legacyId = (int) $item['id'];
        $title = trim((string) $item['title']);
        $slug = LegacySlug::fromTitle($title);

        $story = Story::withTrashed()->where('legacy_id', $legacyId)->first();

        if ($story?->trashed()) {
            return $this->row($legacyId, $title, $slug, 'skipped (deleted by an editor)');
        }

        if ($story !== null && ! $update) {
            return $this->row($legacyId, $title, $slug, 'unchanged (already imported)');
        }

        $clash = Story::withTrashed()->where('slug', $slug)->first();
        if ($clash !== null && $clash->legacy_id !== $legacyId) {
            throw new RuntimeException("Slug [{$slug}] is already used by story #{$clash->id}; cannot import legacy story {$legacyId}.");
        }

        $story ??= new Story;
        $result = $story->exists ? 'updated' : 'created';

        $body = $this->sanitizer->sanitize((string) $item['content']);
        $story->fill([
            'legacy_id' => $legacyId,
            'title' => $title,
            'slug' => $slug,
            'body' => $body,
            'excerpt' => Excerpt::from($body),
            'status' => StoryStatus::Published,
            'published_at' => $publishPlan['at'],
        ]);

        if (isset($item['created_date'], $item['modified_date'])) {
            $story->forceFill([
                'created_at' => $this->toAppTimezone((string) $item['created_date']),
                'updated_at' => $this->toAppTimezone((string) $item['modified_date']),
            ]);
        }

        $notes = [
            ...($publishPlan['note'] === null ? [] : [$publishPlan['note']]),
            ...$this->attachImage($story, $legacyId, $imagesPath, $alts, $dryRun),
        ];

        if (! $dryRun) {
            $story->save();
        }

        return $this->row($legacyId, $title, $slug, $result, $notes);
    }

    /**
     * @param  list<string>  $notes
     * @return array{legacy_id: int, title: string, slug: string, result: string, notes: list<string>}
     */
    private function row(int $legacyId, string $title, string $slug, string $result, array $notes = []): array
    {
        return [
            'legacy_id' => $legacyId,
            'title' => $title,
            'slug' => $slug,
            'result' => $result,
            'notes' => $notes,
        ];
    }

    /**
     * Copies the legacy image onto the public disk and records its dimensions and alt text.
     *
     * @param  array<int|string, mixed>  $alts  keyed by legacy id (JSON keys such as "49" become int keys in PHP)
     * @return list<string> notes for the operator
     */
    private function attachImage(Story $story, int $legacyId, string $imagesPath, array $alts, bool $dryRun): array
    {
        $files = glob(rtrim($imagesPath, '/\\').DIRECTORY_SEPARATOR.$legacyId.'-*');

        if ($files === false || $files === []) {
            return ['image file not found'];
        }

        $file = $files[0];
        $size = getimagesize($file);
        $path = 'stories/'.basename($file);
        $alt = $alts[$legacyId] ?? null;

        if (! $dryRun) {
            Storage::disk('public')->put($path, (string) file_get_contents($file));
        }

        $story->fill([
            'featured_image_path' => $path,
            'featured_image_alt' => is_string($alt) ? $alt : null,
            'featured_image_width' => $size === false ? null : $size[0],
            'featured_image_height' => $size === false ? null : $size[1],
        ]);

        return is_string($alt) ? [] : ['alt text missing - an editor must add it'];
    }

    private function toAppTimezone(string $value): CarbonInterface
    {
        return Date::parse($value)->setTimezone((string) config('app.timezone'));
    }

    /**
     * @return array<int|string, mixed>
     */
    private function readJson(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("File not found: {$path}");
        }

        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new InvalidArgumentException("Expected a JSON object or array in: {$path}");
        }

        return $data;
    }
}
