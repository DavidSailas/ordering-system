<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'receipt_footer' => ['nullable', 'string', 'max:160'],
            'is_open' => ['nullable', 'boolean'],
            'buildings' => ['nullable', 'string', 'max:2000'],
            'gcash_number' => ['nullable', 'string', 'max:40'],
            'gcash_name' => ['nullable', 'string', 'max:80'],
            'maya_number' => ['nullable', 'string', 'max:40'],
            'maya_name' => ['nullable', 'string', 'max:80'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.max' => 'The logo may not be larger than 2 MB.',
            'accent.regex' => 'Pick the brand colour with the colour picker.',
        ];
    }
}
