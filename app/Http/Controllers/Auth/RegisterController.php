<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Mail\RegistrationCodeMail;
use App\Models\User;
use App\Support\ShopCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Customer registration with an emailed 6-digit code.
 *
 *  1. POST /register          – validate the details, email a code, keep the details
 *                               (password already hashed) in the cache for a few minutes.
 *  2. POST /register/verify   – the correct code creates the account and signs the customer in.
 *
 * No account exists until the email address has been proven.
 */
class RegisterController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.register', [
            'role' => UserRole::Customer,
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $email = Str::lower($request->validated('email'));

        $pending = [
            'name' => $request->validated('name'),
            'email' => $email,
            'password' => Hash::make($request->validated('password')),
        ];

        if (! $this->sendCode($pending)) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'We could not send the verification email right now. Please check the email address and try again.']);
        }

        $request->session()->put('register.email', $email);

        return redirect()->route('register.verify');
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        $email = $request->session()->get('register.email');

        if (! $email || ! Cache::has($this->key($email))) {
            return redirect()->route('register')->withErrors(['email' => 'Your verification expired. Please register again.']);
        }

        return view('auth.verify-code', [
            'maskedEmail' => $this->mask($email),
            'ttl' => (int) config('restaurant.registration_code.ttl_minutes', 10),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']], [
            'code.required' => 'Enter the 6-digit code from your email.',
            'code.digits' => 'The code is 6 digits.',
        ]);

        $email = $request->session()->get('register.email');
        $pending = $email ? Cache::get($this->key($email)) : null;

        if (! $pending) {
            return redirect()->route('register')->withErrors(['email' => 'Your verification expired. Please register again.']);
        }

        $maxAttempts = (int) config('restaurant.registration_code.max_attempts', 5);

        if (($pending['attempts'] ?? 0) >= $maxAttempts) {
            Cache::forget($this->key($email));

            return redirect()->route('register')->withErrors(['email' => 'Too many wrong codes. Please register again.']);
        }

        if (! Hash::check($request->input('code'), $pending['code'])) {
            $pending['attempts'] = ($pending['attempts'] ?? 0) + 1;
            Cache::put($this->key($email), $pending, $this->expiry());

            $left = max(0, $maxAttempts - $pending['attempts']);

            return back()->withErrors(['code' => "That code is not correct. {$left} ".Str::plural('attempt', $left).' left.']);
        }

        if (User::query()->where('email', $email)->exists()) {
            Cache::forget($this->key($email));

            return redirect()->route('login')->withErrors(['email' => 'This email is already registered. Please log in.']);
        }

        $user = User::create([
            'name' => $pending['name'],
            'email' => $pending['email'],
            'password' => $pending['password'], // already hashed
            'role' => UserRole::Customer,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        Cache::forget($this->key($email));
        $request->session()->forget('register.email');

        Auth::login($user);
        $request->session()->regenerate();

        ShopCart::mergeGuestCartInto($request, $user);

        return redirect()->route($user->role->homeRoute());
    }

    public function resend(Request $request): RedirectResponse
    {
        $email = $request->session()->get('register.email');
        $pending = $email ? Cache::get($this->key($email)) : null;

        if (! $pending) {
            return redirect()->route('register')->withErrors(['email' => 'Your verification expired. Please register again.']);
        }

        $wait = (int) config('restaurant.registration_code.resend_seconds', 60);
        $elapsed = now()->timestamp - ($pending['sent_at'] ?? 0);

        if ($elapsed < $wait) {
            return back()->withErrors(['code' => 'Please wait '.($wait - $elapsed).' seconds before asking for a new code.']);
        }

        if (! $this->sendCode($pending)) {
            return back()->withErrors(['code' => 'We could not send the email right now. Please try again in a moment.']);
        }

        return back()->with('status', 'A new code was sent to '.$this->mask($email).'.');
    }

    /**
     * Create a fresh code, cache it (hashed) with the pending details and email it.
     *
     * @param  array{name: string, email: string, password: string}  $pending
     */
    private function sendCode(array $pending): bool
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $pending['code'] = Hash::make($code);
        $pending['attempts'] = 0;
        $pending['sent_at'] = now()->timestamp;

        Cache::put($this->key($pending['email']), $pending, $this->expiry());

        try {
            Mail::to($pending['email'])->send(new RegistrationCodeMail($pending['name'], $code));
        } catch (\Throwable $e) {
            Log::error('Registration code email failed: '.$e->getMessage());
            Cache::forget($this->key($pending['email']));

            return false;
        }

        return true;
    }

    private function key(string $email): string
    {
        return 'register:'.sha1(Str::lower($email));
    }

    private function expiry(): \DateTimeInterface
    {
        return now()->addMinutes((int) config('restaurant.registration_code.ttl_minutes', 10));
    }

    private function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return Str::substr($local, 0, 2).str_repeat('•', max(1, Str::length($local) - 2)).'@'.$domain;
    }
}
