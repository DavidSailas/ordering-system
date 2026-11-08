<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\Canteen;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Administrator-only form for creating and editing any account and choosing its role.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'canteen_id' => [
                Rule::requiredIf(fn (): bool => UserRole::tryFrom((string) $this->input('role'))?->belongsToCanteen() ?? false),
                'nullable',
                Rule::exists(Canteen::class, 'id'),
            ],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'canteen_id.required' => 'Choose the canteen this person works for.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $role = UserRole::tryFrom((string) $this->input('role'));
                $canteenId = (int) $this->input('canteen_id');

                if ($role === UserRole::Owner && $canteenId && User::canteenHasActiveOwner($canteenId, $this->route('user'))) {
                    $validator->errors()->add('role', 'This canteen already has an owner. Change or deactivate the current owner first.');
                }
            },
        ];
    }

    /** Admins and customers belong to no canteen; owners, cashiers and runners always do. */
    public function canteenId(): ?int
    {
        $role = $this->enum('role', UserRole::class);

        return $role?->belongsToCanteen() ? (int) $this->input('canteen_id') : null;
    }
}
