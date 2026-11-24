<?php
namespace App\Http\Requests\Auth;
use App\Models\SchoolMember;
use App\Models\User;
use App\Support\SchoolRoster;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
class RegisterRequest extends FormRequest {
    private ?SchoolMember $schoolMember = null;
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'name' => ['required','string','max:255'],
        'school_id' => ['required','string','max:40'],
        'last_name' => ['required','string','max:80'],
        'email' => ['required','string','email','max:255',Rule::unique(User::class)],
        'password' => ['required','confirmed',Password::defaults()],
    ]; }
    public function attributes(): array { return ['school_id' => 'school ID number', 'last_name' => 'last name']; }
    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['school_id', 'last_name'])) { return; }
            $result = SchoolRoster::claimable((string) $this->input('school_id'), (string) $this->input('last_name'));
            if (is_string($result)) { $validator->errors()->add('school_id', $result); return; }
            $this->schoolMember = $result;
        });
    }
    /** The school list entry this registration was matched to (only after validation passed). */
    public function schoolMember(): SchoolMember { return $this->schoolMember; }
}
