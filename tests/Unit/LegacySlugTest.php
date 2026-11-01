<?php

use App\Support\LegacySlug;

test('it reproduces the legacy slug of every story that was live on cancocares.ca', function (string $title, string $slug) {
    expect(LegacySlug::fromTitle($title))->toBe($slug);
})->with([
    'plain title' => ['Community Matters', 'community-matters'],
    'punctuation is dropped' => ['Another wish.. Delivered!', 'another-wish-delivered'],
    'trailing punctuation leaves a trailing hyphen' => ['Canco Delivering Wishes !!', 'canco-delivering-wishes-'],
    'digits are kept' => ['Wishing You an Amazing 2023!', 'wishing-you-an-amazing-2023'],
    'currency symbols are dropped' => ['Canco donates $9500 to Starbright!', 'canco-donates-9500-to-starbright'],
    'apostrophes are dropped' => ["Supporting Starbright's Autism Program!", 'supporting-starbrights-autism-program'],
    'commas are dropped' => ['Canco donated $11,000 to KGH Foundation!', 'canco-donated-11000-to-kgh-foundation'],
]);

test('runs of whitespace collapse into one hyphen', function () {
    expect(LegacySlug::fromTitle("Hello \t  World"))->toBe('hello-world');
});
