<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

/**
 * One image per request (the library page uploads files one at a time so it can show progress and
 * report each failure separately). Authorisation is done by the controller through MediaPolicy.
 */
class UploadMediaRequest extends FormRequest
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
        return [
            'file' => ['required', File::types(['jpg', 'jpeg', 'png', 'gif', 'webp'])->max(15 * 1024)],
            'alt' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose an image to upload.',
            'file.max' => 'That image is larger than 15 MB. Resize it and try again.',
            'file.mimes' => 'Upload a JPG, PNG, GIF or WebP image.',
        ];
    }
}
