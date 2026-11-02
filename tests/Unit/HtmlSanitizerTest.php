<?php

use App\Services\HtmlSanitizer;

beforeEach(function () {
    $this->sanitizer = new HtmlSanitizer(allowInsecureMedia: false);
});

test('it keeps ordinary editorial markup', function () {
    $html = '<h2>Title</h2><p>Hello <strong>world</strong> and <em>friends</em>.</p><ul><li>One</li></ul>';

    expect($this->sanitizer->sanitize($html))->toBe($html);
});

test('it removes scripts, iframes, styles and their payloads', function (string $dirty, array $forbidden) {
    $clean = $this->sanitizer->sanitize($dirty);

    foreach ($forbidden as $needle) {
        expect($clean)->not->toContain($needle);
    }
})->with([
    'script tag' => ['<p>Hi</p><script>alert(1)</script>', ['<script', 'alert(1)']],
    'inline event handler' => ['<img src="https://example.com/a.png" onerror="alert(1)">', ['onerror', 'alert(1)']],
    'javascript: link' => ['<a href="javascript:alert(1)">click</a>', ['javascript:']],
    'iframe' => ['<iframe src="https://evil.example"></iframe><p>ok</p>', ['<iframe', 'evil.example']],
    'style tag' => ['<style>body{display:none}</style><p>ok</p>', ['<style', 'display:none']],
    'svg payload' => ['<svg onload="alert(1)"><circle/></svg>', ['<svg', 'onload']],
    'data: image' => ['<img src="data:text/html;base64,PHNjcmlwdD4=">', ['data:text/html']],
    'plain http image is not allowed as media' => ['<img src="http://example.com/a.png">', ['http://example.com/a.png']],
]);

test('external links are forced to rel noopener noreferrer', function () {
    $clean = $this->sanitizer->sanitize('<a href="https://example.com" rel="opener" target="_blank">Read</a>');

    expect($clean)->toContain('href="https://example.com"')
        ->toContain('rel="noopener noreferrer"')
        ->not->toContain('opener"')
        ->not->toContain('target=');
});

test('mailto and tel links and internal relative links are allowed', function () {
    $clean = $this->sanitizer->sanitize(
        '<a href="mailto:inquiry@cancocares.ca">Email</a> <a href="tel:+14035551234">Call</a> <a href="/contact-us">Contact</a>'
    );

    // The sanitiser entity-encodes "@" and "+" inside attributes; browsers decode them.
    expect(html_entity_decode($clean))->toContain('mailto:inquiry@cancocares.ca')
        ->toContain('tel:+14035551234')
        ->toContain('href="/contact-us"');
});

test('legacy story markup survives with entities intact', function () {
    $legacy = '<p>Here at Canco, we are one big community!<br>Thank Splatsin Kukpi7 &amp; Tkwamipla7 (Chief &amp; Council).</p>';

    expect($this->sanitizer->sanitize($legacy))
        ->toContain('<br />')
        ->toContain('Kukpi7 &amp; Tkwamipla7');
});

test('library images keep src, alt and size but lose every other attribute', function () {
    $clean = $this->sanitizer->sanitize('<img src="https://cdn.example/a.webp" alt="Volunteers" width="800" height="600" class="x" style="y" onload="z">');

    expect($clean)->toContain('src="https://cdn.example/a.webp"')
        ->and($clean)->toContain('alt="Volunteers"')
        ->and($clean)->toContain('width="800"')
        ->and($clean)->not->toContain('class')
        ->and($clean)->not->toContain('onload');
});

test('a dev server without TLS may opt in to plain-http library images', function () {
    $html = '<img src="http://localhost:8000/storage/a.webp" alt="A">';

    expect((new HtmlSanitizer(allowInsecureMedia: true))->sanitize($html))->toContain('src="http://localhost:8000/storage/a.webp"')
        ->and($this->sanitizer->sanitize($html))->not->toContain('http://localhost');
});
