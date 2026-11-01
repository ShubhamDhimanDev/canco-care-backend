<?php

namespace App\Services;

use App\Support\HomeContent;

/**
 * Turns the home page form into the stored document. Only the fields an editor can change are
 * kept; an image keeps its library id unless a new one is chosen or "remove" was pressed (which
 * brings back the picture bundled with the website).
 */
class HomePageWriter
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  array<string, mixed>  $input  validated HomePageRequest input
     */
    public function save(array $input): void
    {
        $stored = $this->settings->get('home.content');
        $stored = is_array($stored) ? $stored : [];

        $document = [
            'hero' => [
                ...$this->texts($input['hero'] ?? [], ['title', 'text', 'primary_label', 'secondary_label', 'secondary_href']),
                'image' => $this->image($input['hero']['image'] ?? [], $stored['hero']['image'] ?? []),
            ],
            'intro' => $this->texts($input['intro'] ?? [], ['eyebrow', 'title', 'text', 'button_label', 'button_href']),
            'cards' => [],
            'stories' => $this->texts($input['stories'] ?? [], ['eyebrow', 'title']),
        ];

        foreach ([0, 1] as $index) {
            $card = $input['cards'][$index] ?? [];

            $document['cards'][] = [
                ...$this->texts($card, ['eyebrow', 'title', 'text']),
                'image' => $this->image($card['image'] ?? [], $stored['cards'][$index]['image'] ?? []),
            ];
        }

        $this->settings->set('home.content', $document);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $keys
     * @return array<string, string|null>
     */
    private function texts(array $values, array $keys): array
    {
        $out = [];

        foreach ($keys as $key) {
            $value = $values[$key] ?? null;
            $out[$key] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        return $out;
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
            return HomeContent::defaults()['hero']['image'];
        }

        $mediaId = filled($input['media_id'] ?? null) ? (int) $input['media_id'] : ($previous['media_id'] ?? null);

        return ['media_id' => $mediaId === null ? null : (int) $mediaId, 'alt' => $mediaId === null ? null : $alt];
    }
}
