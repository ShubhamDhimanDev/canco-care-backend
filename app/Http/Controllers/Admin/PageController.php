<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageRequest;
use App\Models\Media;
use App\Models\Setting;
use App\Services\PageWriter;
use App\Services\Settings;
use App\Support\PageCatalog;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The words and pictures of the website's other pages: Who We Are, Our Impact, Partner With Us,
 * Contact Us, Terms and Conditions and the Privacy Policy (docs/plan/14 #1, #3). The home page has its
 * own editor. Guarded by the `pages.update` permission in the routes.
 */
class PageController extends Controller
{
    public function index(): Response
    {
        $updated = Setting::query()
            ->where('group', 'pages')
            ->pluck('updated_at', 'key');
        $siteUrl = rtrim((string) config('services.frontend.url'), '/');

        return Inertia::render('pages/index', [
            'pages' => array_map(function (string $slug) use ($updated, $siteUrl): array {
                $definition = PageCatalog::definition($slug);

                return [
                    'slug' => $slug,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'path' => $definition['path'],
                    'public_url' => $siteUrl === '' ? null : $siteUrl.$definition['path'],
                    'updated_at' => $updated->get("pages.{$slug}")?->toIso8601String(),
                ];
            }, PageCatalog::slugs()),
        ]);
    }

    public function edit(string $slug, Settings $settings): Response
    {
        abort_unless(PageCatalog::exists($slug), 404);

        $definition = PageCatalog::definition($slug);
        $content = PageCatalog::merged($slug, $settings);
        $media = Media::query()
            ->whereKey(array_values(array_filter(array_map(
                fn (mixed $value): int => is_array($value) ? (int) ($value['media_id'] ?? 0) : 0,
                $content,
            ))))
            ->get()
            ->keyBy('id');

        // Text: what is stored (or the default, the same thing the site shows). Image: the chosen library picture or null.
        $values = [];
        $defaults = [];
        foreach (PageCatalog::fields($slug) as $key => $field) {
            if ($field['type'] === 'image') {
                $item = $media->get($content[$key]['media_id'] ?? 0);
                $values[$key] = $item instanceof Media ? $item->asImage($content[$key]['alt'] ?? null) : null;
            } else {
                $values[$key] = $content[$key];
                $defaults[$key] = $field['default'];
            }
        }

        $siteUrl = rtrim((string) config('services.frontend.url'), '/');

        return Inertia::render('pages/edit', [
            'slug' => $slug,
            'label' => $definition['label'],
            'sections' => $definition['sections'],
            'values' => $values,
            'defaults' => $defaults,
            'publicUrl' => $siteUrl === '' ? null : $siteUrl.$definition['path'],
        ]);
    }

    public function update(PageRequest $request, string $slug, PageWriter $writer): RedirectResponse
    {
        $writer->save($slug, (array) $request->validated('fields', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':page saved.', ['page' => PageCatalog::definition($slug)['label']])]);

        return to_route('pages.edit', $slug);
    }
}
