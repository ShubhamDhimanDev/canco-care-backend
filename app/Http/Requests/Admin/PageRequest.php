<?php

namespace App\Http\Requests\Admin;

use App\Support\PageCatalog;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the page editor. The rules come from the page's catalog entry, so adding a field to
 * `PageCatalog` is enough: it is drawn, validated and saved without further code.
 */
class PageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PageCatalog::exists($this->slug());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (PageCatalog::fields($this->slug()) as $key => $field) {
            $path = "fields.{$key}";

            if ($field['type'] === 'image') {
                $rules["{$path}.media_id"] = ['nullable', 'integer', 'exists:media,id'];
                $rules["{$path}.alt"] = ["required_with:{$path}.media_id", 'nullable', 'string', 'max:200'];
                $rules["{$path}.remove"] = ['sometimes', 'boolean'];

                continue;
            }

            $rules[$path] = ['nullable', 'string', 'max:'.(int) $field['max']];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $names = [];

        foreach (PageCatalog::fields($this->slug()) as $key => $field) {
            $names["fields.{$key}"] = mb_strtolower((string) $field['label']);
            $names["fields.{$key}.alt"] = 'description of the picture';
        }

        return $names;
    }

    private function slug(): string
    {
        return (string) $this->route('slug');
    }
}
