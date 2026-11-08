<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
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
        $category = $this->route('category');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')->where('canteen_id', \App\Support\CurrentCanteen::id())->ignore($category),
                function (string $attribute, mixed $value, Closure $fail) use ($category): void {
                    $slugTaken = Category::query()
                        ->where('slug', Str::slug($value))
                        ->when($category, fn ($query) => $query->whereKeyNot($category->getKey()))
                        ->exists();

                    if ($slugTaken) {
                        $fail('A category with a very similar name already exists.');
                    }
                },
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }
}
