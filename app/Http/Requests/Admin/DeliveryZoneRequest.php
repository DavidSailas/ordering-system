<?php

namespace App\Http\Requests\Admin;

use App\Models\DeliveryZone;
use App\Support\CurrentCanteen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** One building: its name, floors and prices. Sensible limits stop typing mistakes (₱10000 instead of ₱10). */
class DeliveryZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // the route is already limited to admin and owner accounts
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('delivery_zones', 'name')
                    ->where('canteen_id', CurrentCanteen::id())
                    ->ignore($this->route('zone')),
            ],
            'floors' => ['required', 'integer', 'min:1', 'max:60'],
            'base_fee' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:500'],
            'per_floor_fee' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'is_home' => ['nullable', 'boolean'],
            'home_floor' => ['nullable', 'required_if:is_home,1', 'integer', 'min:1', 'lte:floors'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $worst = (float) $this->input('base_fee') + max(0, (int) $this->input('floors') - 1) * (float) $this->input('per_floor_fee');

            if ($worst > DeliveryZone::MAX_FEE) {
                $validator->errors()->add('per_floor_fee', 'These prices would make the highest floor cost more than ₱'.number_format(DeliveryZone::MAX_FEE).'. Please check the numbers.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'You already have a building with that name.',
            'home_floor.required_if' => 'Tell us which floor the canteen is on.',
            'home_floor.lte' => 'The canteen floor cannot be higher than the number of floors.',
        ];
    }
}
