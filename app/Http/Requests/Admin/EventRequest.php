<?php

namespace App\Http\Requests\Admin;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Services\HtmlSanitizer;
use App\Services\SlugService;
use Closure;
use DateTimeZone;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating and editing an event. Authorisation is done by the controller through
 * EventPolicy. Start and end arrive as the wall-clock time in the event's own time zone
 * ("2026-11-14T18:00" + "America/Vancouver"), which EventWriter converts to UTC.
 */
class EventRequest extends FormRequest
{
    private const POSTAL_CODE = '/^[ABCEGHJ-NPRSTVXY]\d[ABCEGHJ-NPRSTV-Z][ -]?\d[ABCEGHJ-NPRSTV-Z]\d$/i';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Canadian postal codes and provinces are stored upper-case.
        $this->merge([
            'province' => is_string($this->input('province')) ? strtoupper(trim($this->input('province'))) : $this->input('province'),
            'postal_code' => is_string($this->input('postal_code')) ? strtoupper(trim($this->input('postal_code'))) : $this->input('postal_code'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $event = $this->route('event');
        $event = $event instanceof Event ? $event : null;
        $hasImage = filled($this->input('image_media_id'))
            || ($event?->image_path !== null && ! $this->boolean('remove_image'));
        $dateFormat = $this->boolean('is_all_day') ? 'Y-m-d' : 'Y-m-d\TH:i';

        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:220', $this->slugRule($event)],
            'summary' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:200000'],
            'image_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'image_alt' => [Rule::requiredIf($hasImage), 'nullable', 'string', 'max:200'],
            'remove_image' => ['sometimes', 'boolean'],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'is_all_day' => ['sometimes', 'boolean'],
            'starts_at_local' => ['required', 'date_format:'.$dateFormat],
            'ends_at_local' => ['nullable', 'date_format:'.$dateFormat, $this->endRule()],
            'location_name' => ['nullable', 'string', 'max:160'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'size:2', 'alpha'],
            'postal_code' => ['nullable', 'string', 'regex:'.self::POSTAL_CODE],
            'online_url' => ['nullable', 'url:http,https', 'max:255'],
            'registration_url' => ['nullable', 'url:http,https', 'max:255'],
            'cost_note' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::enum(EventStatus::class)],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['sometimes', 'boolean'],
            'show_in_donation_form' => ['sometimes', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Give the event a title.',
            'summary.required' => 'Write a one or two sentence summary for the event card.',
            'starts_at_local.required' => 'Choose when the event starts.',
            'starts_at_local.date_format' => 'Enter the start as a date and time.',
            'ends_at_local.date_format' => 'Enter the end as a date and time.',
            'image_alt.required' => 'Describe the image for people who cannot see it (alt text).',
            'province.size' => 'Use the two-letter province code, for example BC.',
            'postal_code.regex' => 'Enter a Canadian postal code such as V1X 2A1.',
            'online_url.url' => 'Enter a full web address starting with https://.',
            'registration_url.url' => 'Enter a full web address starting with https://.',
        ];
    }

    private function endRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $start = $this->input('starts_at_local');

            if (is_string($value) && is_string($start) && $value < $start) {
                $fail('The event cannot end before it starts.');
            }
        };
    }

    /**
     * An event needs somewhere to be: a venue, a city, or an online link. (A closure rule would not
     * run for an empty field, so this is checked after the field rules.)
     *
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (blank($this->input('location_name')) && blank($this->input('city')) && blank($this->input('online_url'))) {
                    $validator->errors()->add('location_name', 'Say where it happens: a venue name, a city, or an online link.');
                }
            },
        ];
    }

    private function slugRule(?Event $event): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($event): void {
            if (! is_string($value) || $value === $event?->slug) {
                return;
            }

            if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
                $fail('Use lowercase letters, numbers and single hyphens only (for example "spring-fundraiser").');

                return;
            }

            if (app(SlugService::class)->isTaken($value, $event, Event::class)) {
                $fail('That web address is already used by another event.');
            }
        };
    }

    /**
     * The sanitised description, used by the writer and to catch an empty editor.
     */
    public function cleanDescription(): ?string
    {
        $clean = app(HtmlSanitizer::class)->sanitize((string) $this->input('description', ''));

        return trim(html_entity_decode(strip_tags($clean))) === '' ? null : $clean;
    }
}
