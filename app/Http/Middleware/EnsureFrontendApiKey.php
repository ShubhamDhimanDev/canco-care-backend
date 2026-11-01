<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the public API's write endpoints. Only the Next.js server holds the key
 * (FRONTEND_API_KEY); browsers never call these endpoints directly (ADR-002).
 *
 * Fails closed: if no key is configured, every request is rejected.
 */
class EnsureFrontendApiKey
{
    public static function isValid(Request $request): bool
    {
        $expected = (string) config('services.frontend.api_key');

        return $expected !== '' && hash_equals($expected, (string) $request->header('X-Api-Key', ''));
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! self::isValid($request)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
