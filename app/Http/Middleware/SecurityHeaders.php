<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Standard security headers on every response of the admin and the API (docs/plan/14 #7, #8):
 * HSTS, Content-Security-Policy, X-Frame-Options, X-Content-Type-Options, Referrer-Policy — plus
 * Permissions-Policy and `X-Robots-Tag: noindex`. The admin and the API are never meant to appear in
 * search results, in production either, so noindex is unconditional.
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $csp = (string) config('security.csp');
        $sendCsp = $csp !== 'off' && ! app()->environment('local');

        // Must happen before the view renders so that @vite stamps its tags with the nonce.
        $nonce = $sendCsp ? Vite::useCspNonce() : null;

        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'X-Robots-Tag' => 'noindex, nofollow',
        ];

        if ($sendCsp && $nonce !== null) {
            $name = $csp === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
            $headers[$name] = $this->policy($nonce);
        }

        $hsts = $this->hsts($request);
        if ($hsts !== null) {
            $headers['Strict-Transport-Security'] = $hsts;
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * Scripts: our own bundles and the nonce'd inline ones only. Styles keep 'unsafe-inline' because
     * Radix/Tailwind set inline style attributes; fonts are self-hosted by the Vite fonts plugin.
     */
    private function policy(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }

    private function hsts(Request $request): ?string
    {
        if (! app()->environment('production') || ! $request->isSecure()) {
            return null;
        }

        return match ((string) config('security.hsts')) {
            'full' => 'max-age=31536000; includeSubDomains',
            'off' => null,
            default => 'max-age=300',
        };
    }
}
