<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Settings;
use App\Support\HomeContent;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    /**
     * The home page's copy and images, defaults already merged (docs/plan/03-api-spec.md "GET /home").
     * An image the editor never chose is `null`: the website then uses its bundled picture.
     */
    public function show(Settings $settings): JsonResponse
    {
        return response()->json(['data' => HomeContent::forApi($settings)]);
    }
}
