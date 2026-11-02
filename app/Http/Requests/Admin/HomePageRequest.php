<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the home page editor. Links must be a path on this site ("/who-we-are") or a full
 * http(s) address, so an editor can never save a "javascript:" link.
 */
class HomePageRequest extends FormRequest
{
    private const LINK = '/^(\/[^\s]*|https?:\/\/[^\s]+)$/i';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $image = fn (string $path): array => [
            "{$path}.media_id" => ['nullable', 'integer', 'exists:media,id'],
            "{$path}.alt" => ["required_with:{$path}.media_id", 'nullable', 'string', 'max:200'],
            "{$path}.remove" => ['sometimes', 'boolean'],
        ];

        return [
            'hero.title' => ['nullable', 'string', 'max:80'],
            'hero.text' => ['nullable', 'string', 'max:400'],
            'hero.primary_label' => ['nullable', 'string', 'max:30'],
            'hero.secondary_label' => ['nullable', 'string', 'max:30'],
            'hero.secondary_href' => ['nullable', 'string', 'max:300', 'regex:'.self::LINK],
            ...$image('hero.image'),

            'intro.eyebrow' => ['nullable', 'string', 'max:60'],
            'intro.title' => ['nullable', 'string', 'max:60'],
            'intro.text' => ['nullable', 'string', 'max:600'],
            'intro.button_label' => ['nullable', 'string', 'max:30'],
            'intro.button_href' => ['nullable', 'string', 'max:300', 'regex:'.self::LINK],

            'cards' => ['required', 'array', 'size:2'],
            'cards.*.eyebrow' => ['nullable', 'string', 'max:60'],
            'cards.*.title' => ['nullable', 'string', 'max:40'],
            'cards.*.text' => ['nullable', 'string', 'max:400'],
            ...$image('cards.*.image'),

            'stories.eyebrow' => ['nullable', 'string', 'max:60'],
            'stories.title' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hero.secondary_href.regex' => 'Use a page on this site (like /who-we-are) or a full address starting with https://.',
            'intro.button_href.regex' => 'Use a page on this site (like /who-we-are) or a full address starting with https://.',
        ];
    }
}
