<?php

namespace App\Http\Requests\Api\V1;

use App\Contracts\BotVerifier;
use App\Models\Event;
use App\Services\Settings;
use App\Support\ClientIp;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The donation form (docs/plan/03-api-spec.md "POST /donations"). The amount is validated against
 * the administrator's minimum here and then stored; it is never read from a later request.
 */
class StoreDonationRequest extends FormRequest
{
    /** The most one online gift may be, in cents ($10,000). */
    public const MAX_CENTS = 1_000_000;

    private const POSTAL_CODE = '/^[ABCEGHJ-NPRSTVXY]\d[ABCEGHJ-NPRSTV-Z][ -]?\d[ABCEGHJ-NPRSTV-Z]\d$/i';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $clean = fn (mixed $value): mixed => is_string($value)
            ? trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $value))
            : $value;

        $postal = $clean($this->input('postal_code'));

        $this->merge([
            'first_name' => $clean($this->input('first_name')),
            'last_name' => $clean($this->input('last_name')),
            'email' => is_string($this->input('email')) ? mb_strtolower($clean($this->input('email'))) : $this->input('email'),
            'phone' => $clean($this->input('phone')),
            'address_line1' => $clean($this->input('address_line1')),
            'address_line2' => $clean($this->input('address_line2')),
            'city' => $clean($this->input('city')),
            'province' => is_string($this->input('province')) ? strtoupper($clean($this->input('province'))) : $this->input('province'),
            // Stored the way it is printed: "V1X 2A1".
            'postal_code' => is_string($postal) && preg_match(self::POSTAL_CODE, $postal)
                ? strtoupper(substr(preg_replace('/[ -]/', '', $postal) ?? '', 0, 3).' '.substr(preg_replace('/[ -]/', '', $postal) ?? '', 3, 3))
                : $postal,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $verifier = app(BotVerifier::class);
        $minimum = max(100, app(Settings::class)->int('donations.min_cents'));

        return [
            'amount_cents' => ['required', 'integer', 'min:'.$minimum, 'max:'.self::MAX_CENTS],
            'currency' => ['required', 'in:CAD'],
            'event' => ['nullable', 'string', 'max:220', $this->eventRule()],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'size:2', 'alpha'],
            'postal_code' => ['required', 'string', 'regex:'.self::POSTAL_CODE],
            'consent' => ['accepted'],
            'idempotency_key' => ['required', 'uuid'],
            'website' => ['prohibited'],
            'bot_token' => [$verifier->enabled() ? 'required' : 'nullable', 'string', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $minimum = number_format(max(100, app(Settings::class)->int('donations.min_cents')) / 100, 2);

        return [
            'amount_cents.min' => "The smallest gift is \${$minimum}.",
            'amount_cents.max' => 'For gifts over $10,000 please contact us.',
            'address_line1.required' => 'Enter your street address (it goes on your tax receipt).',
            'city.required' => 'Enter your city.',
            'province.required' => 'Choose your province.',
            'province.size' => 'Use the two-letter province code, for example BC.',
            'postal_code.required' => 'Enter your postal code.',
            'postal_code.regex' => 'Enter a Canadian postal code such as V1X 2A1.',
            'consent.accepted' => 'Please agree to the privacy terms to donate.',
            'website.prohibited' => 'This request could not be accepted.',
        ];
    }

    /**
     * The event must be one the donation form offers (published, visible, switched on).
     */
    private function eventRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $offered = is_string($value) && Event::visible()
                ->where('status', 'published')
                ->where('show_in_donation_form', true)
                ->where('slug', $value)
                ->exists();

            if (! $offered) {
                $fail('Choose one of the listed events.');
            }
        };
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $verifier = app(BotVerifier::class);

                if ($validator->errors()->isEmpty() && ! $verifier->passes($this->input('bot_token'), ClientIp::for($this))) {
                    $validator->errors()->add('bot_token', 'We could not confirm you are a person. Please try again.');
                }
            },
        ];
    }
}
