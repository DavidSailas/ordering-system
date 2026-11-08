<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\CurrentCanteen;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
    }

    /**
     * The roles the person making this request may hand out: an owner can add cashiers and
     * runners; an administrator can also appoint a canteen's owner.
     *
     * @return array<int, UserRole>
     */
    public function assignableRoles(): array
    {
        return $this->user()->isAdmin() ? UserRole::assignableByAdmin() : UserRole::assignableByOwner();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user)],
            'role' => ['required', Rule::in(array_map(fn (UserRole $role): string => $role->value, $this->assignableRoles()))],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'You are not allowed to give this role.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $canteenId = CurrentCanteen::id();

                if ($this->input('role') === UserRole::Owner->value && $canteenId && User::canteenHasActiveOwner($canteenId, $this->route('user'))) {
                    $validator->errors()->add('role', 'This canteen already has an owner. Change or deactivate the current owner first.');
                }
            },
        ];
    }
}
