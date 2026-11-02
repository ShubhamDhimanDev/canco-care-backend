<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HomePageRequest;
use App\Models\Media;
use App\Services\HomePageWriter;
use App\Services\Settings;
use App\Support\HomeContent;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Edits the home page's copy and pictures. Both roles may use it (it is content, like stories).
 */
class HomePageController extends Controller
{
    public function edit(Settings $settings): Response
    {
        $content = HomeContent::merged($settings);
        $media = Media::query()->whereKey(HomeContent::mediaIds($content))->get()->keyBy('id');

        // What the picker shows: the chosen library image, or nothing (the bundled picture is used).
        $image = function (array $node) use ($media): ?array {
            $item = $media->get($node['media_id'] ?? 0);

            return $item instanceof Media ? $item->asImage($node['alt'] ?? null) : null;
        };

        $content['hero']['image'] = $image($content['hero']['image']);
        $content['cards'] = array_map(function (array $card) use ($image): array {
            $card['image'] = $image($card['image']);

            return $card;
        }, $content['cards']);

        return Inertia::render('home-page/edit', [
            'content' => $content,
            'defaults' => HomeContent::defaults(),
            'siteUrl' => rtrim((string) config('services.frontend.url'), '/'),
        ]);
    }

    public function update(HomePageRequest $request, HomePageWriter $writer): RedirectResponse
    {
        $writer->save($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Home page saved.')]);

        return to_route('home-page.edit');
    }
}
