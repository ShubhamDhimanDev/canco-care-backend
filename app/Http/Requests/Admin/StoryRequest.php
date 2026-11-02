<?php

namespace App\Http\Requests\Admin;

use App\Enums\StoryStatus;
use App\Models\Story;
use App\Services\HtmlSanitizer;
use App\Services\SlugService;
use App\Services\TagService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Validation for creating and editing a story. Authorisation is done by the controller
 * through StoryPolicy.
 */
class StoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $story = $this->route('story');
        $story = $story instanceof Story ? $story : null;

        $hasImage = $this->hasFile('featured_image')
            || filled($this->input('featured_media_id'))
            || ($story?->featured_image_path !== null && ! $this->boolean('remove_image'));

        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:220', $this->slugRule($story)],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:200000', $this->bodyRule()],
            'featured_image' => [
                'nullable',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(5 * 1024)
                    ->dimensions(Rule::dimensions()->minWidth(800)->maxWidth(6000)->maxHeight(6000)),
            ],
            'featured_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'featured_image_alt' => [Rule::requiredIf($hasImage), 'nullable', 'string', 'max:200'],
            'remove_image' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::enum(StoryStatus::class)],
            'published_at' => ['nullable', 'date'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:255'],
            'noindex' => ['sometimes', 'boolean'],
            'author_name' => ['nullable', 'string', 'max:120'],
            'is_featured' => ['sometimes', 'boolean'],
            'summary' => ['nullable', 'string', 'max:300'],
            'faq' => ['nullable', 'array', 'max:10'],
            'faq.*.question' => ['nullable', 'required_with:faq.*.answer', 'string', 'max:200'],
            'faq.*.answer' => ['nullable', 'required_with:faq.*.question', 'string', 'max:1000'],
            'tags' => ['nullable', 'array', 'max:'.TagService::MAX_PER_STORY],
            'tags.*' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Give the story a title.',
            'body.required' => 'Write the story text.',
            'featured_image_alt.required' => 'Describe the image for people who cannot see it (alt text).',
            'faq.*.question.required_with' => 'Add the question for this answer (or clear the answer).',
            'faq.*.answer.required_with' => 'Add the answer for this question (or clear the question).',
            'tags.max' => 'Use at most '.TagService::MAX_PER_STORY.' tags.',
            'featured_image.dimensions' => 'The image must be at least 800 px wide and no larger than 6000 × 6000 px.',
        ];
    }

    /**
     * New slugs: lowercase words joined by single hyphens, and not already in use (as a story
     * or as the old address of another story). An unchanged slug is always accepted, because
     * migrated stories keep legacy addresses such as "canco-delivering-wishes-".
     */
    private function slugRule(?Story $story): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($story): void {
            if (! is_string($value) || $value === $story?->slug) {
                return;
            }

            if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
                $fail('Use lowercase letters, numbers and single hyphens only (for example "spring-fundraiser").');

                return;
            }

            if (app(SlugService::class)->isTaken($value, $story)) {
                $fail('That web address is already used by another story.');
            }
        };
    }

    /**
     * An empty editor produces markup such as "<p></p>", which is not a real body.
     */
    private function bodyRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $clean = app(HtmlSanitizer::class)->sanitize((string) $value);

            if (trim(html_entity_decode(strip_tags($clean))) === '' && ! str_contains($clean, '<img')) {
                $fail('Write the story text.');
            }
        };
    }
}
