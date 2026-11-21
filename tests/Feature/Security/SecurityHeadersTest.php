<?php

use Illuminate\Support\Facades\Vite;

/*
| The CSP is not sent when APP_ENV=local (the Vite dev server is another origin), so these run as
| "testing". HSTS depends on the environment, so that test switches it.
*/

test('every response carries the standard security headers and is noindex', function () {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()');
});

test('the API responses have them too', function () {
    $this->getJson('/api/v1/health')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('the CSP is report-only by default and carries the nonce that @vite uses', function () {
    $response = $this->get('/login');

    $csp = (string) $response->headers->get('Content-Security-Policy-Report-Only');

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($csp)->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'");

    preg_match("/script-src 'self' 'nonce-([^']+)'/", $csp, $match);

    expect($match)->toHaveCount(2)
        ->and(Vite::cspNonce())->toBe($match[1]);
});

test('enforce mode sends the blocking header and off sends none', function () {
    config(['security.csp' => 'enforce']);
    $this->get('/login')->assertHeader('Content-Security-Policy')->assertHeaderMissing('Content-Security-Policy-Report-Only');

    config(['security.csp' => 'off']);
    $this->get('/login')->assertHeaderMissing('Content-Security-Policy')->assertHeaderMissing('Content-Security-Policy-Report-Only');
});

test('HSTS is sent only over HTTPS in production, short unless set to full', function () {
    $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

    $this->app['env'] = 'production';
    $this->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');
    $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=300');

    config(['security.hsts' => 'full']);
    $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

    config(['security.hsts' => 'off']);
    $this->get('https://localhost/login')->assertHeaderMissing('Strict-Transport-Security');
});

test('the admin robots.txt blocks everything', function () {
    expect(file_get_contents(public_path('robots.txt')))->toContain('Disallow: /');
});
