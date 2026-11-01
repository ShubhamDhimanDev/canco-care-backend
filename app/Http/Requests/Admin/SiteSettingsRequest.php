<?php

namespace App\Http\Requests\Admin;

use App\Services\Donations\DonationGateways;
use App\Support\ImpactFigure;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for Settings › Site (impact figure, social links, contact, donations, receipts).
 * Money fields arrive in dollars (what an administrator types) and are stored as cents.
 */
class SiteSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // The form offers six boxes for suggested amounts; the empty ones are just unused.
        $presets = $this->input('donations.presets');

        if (is_array($presets)) {
            $this->merge(['donations' => [
                ...(array) $this->input('donations'),
                'presets' => array_values(array_filter($presets, fn (mixed $value): bool => $value !== null && $value !== '')),
            ]]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'impact.mode' => ['required', Rule::in([ImpactFigure::CUSTOM, ImpactFigure::ACTUAL])],
            'impact.amount_text' => ['required_if:impact.mode,custom', 'nullable', 'string', 'max:40'],
            'impact.caption' => ['nullable', 'string', 'max:60'],
            'impact.blurb' => ['nullable', 'string', 'max:1000'],
            'impact.starting_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000', 'decimal:0,2'],

            'social.facebook' => ['nullable', 'url:http,https', 'max:255'],
            'social.instagram' => ['nullable', 'url:http,https', 'max:255'],
            'social.linkedin' => ['nullable', 'url:http,https', 'max:255'],
            'contact.inquiry_email' => ['nullable', 'email:rfc', 'max:190'],

            'donations.enabled' => ['sometimes', 'boolean', $this->gatewayRule()],
            'donations.presets' => ['required', 'array', 'min:1', 'max:6'],
            'donations.presets.*' => ['required', 'integer', 'min:1', 'max:10000', 'distinct'],
            'donations.min' => ['required', 'numeric', 'min:1', 'max:10000', 'decimal:0,2'],

            'receipts.charity_name' => ['required', 'string', 'max:120'],
            'receipts.address' => ['required', 'string', 'max:200'],
            'receipts.registration_number' => ['required', 'string', 'max:40'],
            'receipts.signatory_name' => ['required', 'string', 'max:80'],
            'receipts.signatory_label' => ['required', 'string', 'max:60'],
            'receipts.reply_to' => ['nullable', 'email:rfc', 'max:190'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'impact.amount_text.required_if' => 'Type the figure to show, for example $80,000.',
            'impact.starting_amount.decimal' => 'Use dollars and cents, for example 80000 or 80000.50.',
            'donations.presets.*.integer' => 'Suggested amounts are whole dollars.',
            'donations.presets.*.distinct' => 'Each suggested amount must be different.',
            'social.facebook.url' => 'Enter a full address starting with https://.',
            'social.instagram.url' => 'Enter a full address starting with https://.',
            'social.linkedin.url' => 'Enter a full address starting with https://.',
        ];
    }

    /**
     * Giving cannot be switched on while the server has no payment gateway it may use.
     */
    private function gatewayRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (filter_var($value, FILTER_VALIDATE_BOOLEAN) && ($problem = DonationGateways::problem()) !== null) {
                $fail("Online donations cannot be switched on yet. {$problem}");
            }
        };
    }
}
