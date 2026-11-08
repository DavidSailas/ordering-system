<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', Rule::exists('categories', 'id')->where('canteen_id', \App\Support\CurrentCanteen::id())],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('menu_items', 'name')
                    ->where('category_id', $this->input('category_id'))
                    ->ignore($this->route('food')),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=400,min_height=300'],
            'remove_image' => ['nullable', 'boolean'],
            'is_available' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'This category already has a dish with that name.',
            'image.max' => 'The photo may not be larger than 4 MB.',
            'image.dimensions' => 'The photo is too small. Use at least 400 × 300 pixels so every dish looks sharp.',
        ];
    }
}
