<?php

namespace App\Services;

use App\Support\PageCatalog;

/**
 * Turns a page form into the stored document. Only fields an editor filled in are kept (an empty one
 * brings back the original text); rich text is sanitised; an image keeps its library id unless a new
 * one is chosen or "remove" was pressed (which brings back the picture bundled with the website).
 */
class PageWriter
{
    public function __construct(
        private readonly Settings $settings,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated `fields` of a PageRequest
     */
    public function save(string $slug, array $input): void
    {
        $stored = $this->settings->get("pages.{$slug}");
        $stored = is_array($stored) ? $stored : [];
        $document = [];

        foreach (PageCatalog::fields($slug) as $key => $field) {
            $value = $input[$key] ?? null;

            if ($field['type'] === 'image') {
                $image = $this->image(is_array($value) ? $value : [], is_array($stored[$key] ?? null) ? $stored[$key] : []);

                if ($image['media_id'] !== null) {
                    $document[$key] = $image;
                }

                continue;
            }

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $text = $field['type'] === 'rich' ? $this->rich($value) : trim($value);

            if ($text !== '') {
                $document[$key] = $text;
            }
        }

        $this->settings->set("pages.{$slug}", $document);
    }

    private function rich(string $html): string
    {
        $clean = $this->sanitizer->sanitize($html);

        return trim(html_entity_decode(strip_tags($clean))) === '' && ! str_contains($clean, '<img') ? '' : $clean;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $previous
     * @return array{media_id: int|null, alt: string|null}
     */
    private function image(array $input, array $previous): array
    {
        $alt = filled($input['alt'] ?? null) ? trim((string) $input['alt']) : null;

        if (filter_var($input['remove'] ?? false, FILTER_VALIDATE_BOOLEAN) && blank($input['media_id'] ?? null)) {
            return ['media_id' => null, 'alt' => null];
        }

        $mediaId = filled($input['media_id'] ?? null) ? (int) $input['media_id'] : ($previous['media_id'] ?? null);

        return [
            'media_id' => $mediaId === null ? null : (int) $mediaId,
            'alt' => $mediaId === null ? null : ($alt ?? ($previous['alt'] ?? null)),
        ];
    }
}
