<?php

use App\Support\Excerpt;

test('line breaks and block ends become spaces so words are not glued together', function () {
    $html = '<p>Here at Canco, we are one big community!<br>In early February, we visited Enderby.</p><p>Thank you.</p>';

    expect(Excerpt::from($html))
        ->toBe('Here at Canco, we are one big community! In early February, we visited Enderby. Thank you.');
});

test('tags are stripped and entities decoded', function () {
    expect(Excerpt::from('<p>Splatsin &amp; Tkwamipla7 <strong>Chief</strong></p>'))
        ->toBe('Splatsin & Tkwamipla7 Chief');
});

test('short text is returned untouched', function () {
    expect(Excerpt::from('<p>Short and sweet.</p>'))->toBe('Short and sweet.');
});

test('long text is cut on a word boundary and ends with an ellipsis', function () {
    $excerpt = Excerpt::from('<p>'.str_repeat('community ', 40).'</p>', 50);

    expect($excerpt)->toEndWith('…')
        ->and(mb_strlen($excerpt))->toBeLessThanOrEqual(51)
        ->and($excerpt)->not->toContain('communit…');
});

test('a single unbroken word falls back to a hard cut', function () {
    expect(Excerpt::from(str_repeat('a', 300), 20))->toBe(str_repeat('a', 20).'…');
});

test('trailing punctuation is not left dangling before the ellipsis', function () {
    expect(Excerpt::from('<p>Hello there, friend. Another sentence follows here.</p>', 24))
        ->toBe('Hello there, friend…');
});
