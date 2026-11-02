<?php

namespace App\Http\Requests\Api\V1;

use App\Contracts\BotVerifier;
use App\Models\InquiryType;
use App\Support\ClientIp;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The public contact form (docs/plan/03-api-spec.md "POST /inquiries"). The server is the source of
 * truth: the website validates for convenience only.
 */
class StoreInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $clean = fn (mixed $value): mixed => is_string($value)
            ? trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value))
            : $value;

        $this->merge([
            'first_name' => $clean($this->input('first_name')),
            'last_name' => $clean($this->input('last_name')),
            'email' => is_string($this->input('email')) ? mb_strtolower($clean($this->input('email'))) : $this->input('email'),
            'phone' => $clean($this->input('phone')),
            // Newlines are real in a message; every other control character is not.
            'message' => $clean($this->input('message')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $verifier = app(BotVerifier::class);

        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', 'max:30', function (string $attribute, mixed $value, \Closure $fail): void {
                if (self::normalisePhone((string) $value) === null) {
                    $fail('Enter a 10-digit phone number.');
                }
            }],
            'inquiry_type' => [
                Rule::requiredIf(fn (): bool => InquiryType::active()->exists()),
                'nullable',
                'string',
                Rule::exists('inquiry_types', 'slug')->where('is_active', true),
            ],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'consent' => ['accepted'],
            'source_url' => ['nullable', 'url:http,https', 'max:255'],
            'website' => ['nullable', 'string'],
            'bot_token' => [$verifier->enabled() ? 'required' : 'nullable', 'string', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'inquiry_type.required' => 'Choose what your inquiry is about.',
            'inquiry_type.exists' => 'Choose one of the listed topics.',
            'consent.accepted' => 'Please agree to the privacy terms to send your message.',
        ];
    }

    /**
     * The bot check runs after the field rules so a person who mistyped sees field errors first.
     *
     * @return array<int, \Closure>
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

    /**
     * "(403) 555-1234", "+1 403-555-1234" → "+14035551234"; null when it is not a 10-digit number.
     */
    public static function normalisePhone(string $value): ?string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) === 10 ? '+1'.$digits : null;
    }
}
