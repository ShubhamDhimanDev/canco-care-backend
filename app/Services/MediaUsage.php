<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Media;
use App\Models\Story;

/**
 * Where a library file is used. A file that is in use cannot be deleted (the website would show a
 * broken image), and converting a file to WebP has to repoint everything that used the old one.
 *
 * Stories and events reference a file by its storage path; the home page references it by library id.
 */
class MediaUsage
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @return list<array{type: string, label: string, url: string|null}>
     */
    public function for(Media $media): array
    {
        $usages = [];

        Story::withTrashed()
            ->where('featured_image_path', $media->path)
            ->get(['id', 'title'])
            ->each(function (Story $story) use (&$usages): void {
                $usages[] = [
                    'type' => 'Story',
                    'label' => $story->title,
                    'url' => route('stories.edit', $story, false),
                ];
            });

        foreach ($this->eventUsages($media) as $usage) {
            $usages[] = $usage;
        }

        if ($this->homeUses($media->id)) {
            $usages[] = ['type' => 'Home page', 'label' => 'Home page content', 'url' => route('home-page.edit', [], false)];
        }

        return $usages;
    }

    public function isUsed(Media $media): bool
    {
        return $this->for($media) !== [];
    }

    /**
     * After a file is re-encoded to a new path, move every reference across.
     */
    public function repoint(string $oldPath, Media $media): void
    {
        Story::withTrashed()->where('featured_image_path', $oldPath)->update([
            'featured_image_path' => $media->path,
            'featured_image_width' => $media->width,
            'featured_image_height' => $media->height,
        ]);

        $this->repointEvents($oldPath, $media);
    }

    /**
     * @return list<array{type: string, label: string, url: string|null}>
     */
    private function eventUsages(Media $media): array
    {
        return array_values(Event::withTrashed()
            ->where('image_path', $media->path)
            ->get(['id', 'title'])
            ->map(fn (Event $event): array => [
                'type' => 'Event',
                'label' => $event->title,
                'url' => route('events.edit', $event, false),
            ])
            ->all());
    }

    private function repointEvents(string $oldPath, Media $media): void
    {
        Event::withTrashed()->where('image_path', $oldPath)->update([
            'image_path' => $media->path,
            'image_width' => $media->width,
            'image_height' => $media->height,
        ]);
    }

    private function homeUses(int $mediaId): bool
    {
        $found = false;
        $document = $this->homeDocument();

        array_walk_recursive($document, function (mixed $value, string|int $key) use ($mediaId, &$found): void {
            if ($key === 'media_id' && (int) $value === $mediaId) {
                $found = true;
            }
        });

        return $found;
    }

    /**
     * @return array<string, mixed>
     */
    private function homeDocument(): array
    {
        $document = $this->settings->get('home.content');

        return is_array($document) ? $document : [];
    }
}
