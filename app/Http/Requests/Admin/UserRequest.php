<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/**
 * Create or edit a sub-admin. Only super administrators reach it (the `can:admin` route group).
 */
class UserRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $existing = $user instanceof User ? $user : null;

        return [
            ...$this->profileRules($existing?->id),
            // Required when creating; when editing, empty means "keep the current password".
            'password' => $existing === null
                ? $this->passwordRules()
                : ['nullable', 'string', Password::default(), 'confirmed'],
            'is_super_admin' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['integer', Rule::exists(Role::class, 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.*.exists' => 'One of the chosen roles no longer exists. Reload the page and try again.',
        ];
    }
}
