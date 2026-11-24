<x-layouts.guest title="Register" variant="customer" wide>
    <h1>Create your account</h1>
    <p class="auth-sub">Only our students and employees can order. Enter your school ID, then we email you a 6-digit code to confirm your address.</p>

    <form method="POST" action="{{ route('register') }}" class="auth-form" data-auth-form>
        @csrf
        <div class="auth-grid-2">
            <x-auth-field name="school_id" label="Student / employee ID" placeholder="2024-0001" autocomplete="off" required autofocus />
            <x-auth-field name="last_name" label="Last name" placeholder="Dela Cruz" autocomplete="family-name" required />
        </div>
        <x-auth-field name="name" label="Full name" placeholder="Juan Dela Cruz" autocomplete="name" required />
        <x-auth-field name="email" label="Email address" type="email" placeholder="you@example.com" autocomplete="username" required />
        <div class="auth-grid-2">
            <x-auth-field name="password" label="Password" type="password" placeholder="Create a password" autocomplete="new-password" required />
            <x-auth-field name="password_confirmation" label="Confirm password" type="password" placeholder="Re-enter it" autocomplete="new-password" required />
        </div>

        <button class="auth-submit">Send verification code</button>
    </form>

    <p class="auth-alt">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
</x-layouts.guest>
