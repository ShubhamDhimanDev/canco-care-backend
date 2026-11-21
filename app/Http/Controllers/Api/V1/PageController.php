<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Settings;
use App\Support\PageCatalog;
use Illuminate\Http\JsonResponse;

class PageController extends Controller
{
    /**
     * One editable page's copy and pictures, defaults already merged (docs/plan/03-api-spec.md "GET /pages/{slug}").
     * An image nobody chose is `null`: the website then uses the picture bundled with it. `{amount}` in any
     * text is already replaced by the impact counter's figure.
     */
    public function show(string $slug, Settings $settings): JsonResponse
    {
        abort_unless(PageCatalog::exists($slug), 404);

        return response()->json(['data' => ['slug' => $slug, 'content' => PageCatalog::forApi($slug, $settings)]]);
    }
}
