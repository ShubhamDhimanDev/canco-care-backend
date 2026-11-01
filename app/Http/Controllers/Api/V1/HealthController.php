<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Uptime probe. Deliberately touches no database and reveals nothing internal.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()
            ->json(['status' => 'ok', 'time' => now()->toIso8601String()])
            ->header('Cache-Control', 'no-store');
    }
}
