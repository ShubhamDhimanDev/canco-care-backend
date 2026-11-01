<?php

namespace App\Support;

use App\Models\Media;
use App\Services\Settings;

/**
 * The editable copy and images of the home page (docs/plan/13 §4). The stored document only holds
 * what an editor changed; everything else comes from defaults() — the text of cancocares.ca — so an
 * empty database renders today's page, and clearing a field restores its default instead of
 * leaving a hole in the page.
 *
 * Images are stored as `{media_id, alt}`; a missing image means "use the picture bundled with the
 * website".
 */
final class HomeContent
{
    /** The only keys an image node may have. */
    private const IMAGE = ['media_id' => null, 'alt' => null];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'hero' => [
                'title' => 'Fueling Hope Across Canada',
                'text' => 'Together, we can uplift our local communities and create lasting change. Your support helps make a meaningful impact - join us in making a difference today!',
                'primary_label' => 'Donate',
                'secondary_label' => 'Partner with us',
                'secondary_href' => '/partner-with-us',
                'image' => self::IMAGE,
            ],
            'intro' => [
                'eyebrow' => 'Every community deserves',
                'title' => 'CANCO CARES',
                'text' => 'In Canada, numerous communities face various challenges, and many individuals and families need support to thrive. Canco Cares is here to bridge this gap. Our programs across the country aim to provide communities with the resources they need to prosper.',
                'button_label' => 'Learn more',
                'button_href' => '/who-we-are',
            ],
            'cards' => [
                [
                    'image' => self::IMAGE,
                    'eyebrow' => 'Community',
                    'title' => 'PROJECTS',
                    'text' => 'Our Community Projects span across the country, each one aimed at fostering compassion and progress in our local communities.',
                ],
                [
                    'image' => self::IMAGE,
                    'eyebrow' => 'Strategic',
                    'title' => 'PARTNERSHIPS',
                    'text' => "We've built strong relationships with strategic partners who align with our mission, helping us extend our reach and impact.",
                ],
            ],
            'stories' => [
                'eyebrow' => 'In Community',
                'title' => 'LATEST STORIES',
            ],
        ];
    }

    /**
     * Defaults overlaid with the stored document; blank text falls back to the default.
     *
     * @return array<string, mixed>
     */
    public static function merged(Settings $settings): array
    {
        $stored = $settings->get('home.content');

        return self::overlay(self::defaults(), is_array($stored) ? $stored : []);
    }

    /**
     * The document the public API serves: images resolved to `{url, alt, width, height}` (or null).
     *
     * @return array<string, mixed>
     */
    public static function forApi(Settings $settings): array
    {
        $content = self::merged($settings);
        $ids = self::mediaIds($content);
        $media = $ids === [] ? collect() : Media::query()->whereKey($ids)->get()->keyBy('id');

        $resolve = function (array $image) use ($media): ?array {
            $item = $media->get($image['media_id'] ?? 0);

            return $item instanceof Media ? $item->asImage($image['alt'] ?? null) : null;
        };

        $content['hero']['image'] = $resolve($content['hero']['image']);
        $content['cards'] = array_map(function (array $card) use ($resolve): array {
            $card['image'] = $resolve($card['image']);

            return $card;
        }, $content['cards']);

        return $content;
    }

    /**
     * Every library id the document refers to.
     *
     * @param  array<string, mixed>  $content
     * @return list<int>
     */
    public static function mediaIds(array $content): array
    {
        $ids = [];

        array_walk_recursive($content, function (mixed $value, string|int $key) use (&$ids): void {
            if ($key === 'media_id' && filled($value)) {
                $ids[(int) $value] = (int) $value;
            }
        });

        return array_values($ids);
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $over
     * @return array<string, mixed>
     */
    private static function overlay(array $base, array $over): array
    {
        foreach ($base as $key => $default) {
            if (! array_key_exists($key, $over)) {
                continue;
            }

            $value = $over[$key];

            if (is_array($default) && is_array($value)) {
                $base[$key] = self::overlay($default, $value);
            } elseif (is_string($value) ? trim($value) !== '' : $value !== null) {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
