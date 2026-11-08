<x-layouts.guest title="Staff sign in">
    <span class="auth-pill"><x-icon name="shield" class="auth-i" />Staff portal</span>
    <h1>Sign in to your workspace</h1>
    <p class="auth-sub">For administrators, canteen owners, cashiers and runners.</p>

    @if (session('status'))
        <div class="auth-alert auth-alert--ok" role="status"><x-icon name="check-circle" class="auth-i" /><span>{{ session('status') }}</span></div>
    @endif

    @if ($errors->any())
        <div class="auth-alert" role="alert"><x-icon name="alert" class="auth-i" /><span>{{ $errors->first() }}</span></div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="auth-form" data-auth-form>
        @csrf
        <x-auth-field name="email" label="Email address" type="email" icon="mail" :show-error="false" placeholder="name@example.com" autocomplete="username" required autofocus />
        <x-auth-field name="password" label="Password" type="password" icon="lock" :show-error="false" placeholder="Enter your password" autocomplete="current-password" required />

        <p class="auth-caps" id="caps-hint" hidden><x-icon name="alert" class="auth-i" />Caps Lock is on</p>

        <div class="auth-row">
            <label class="auth-check"><input type="checkbox" name="remember" value="1"> Keep me signed in on this device</label>
        </div>

        <button type="submit" class="auth-submit"><span class="auth-submit-label">Sign in</span><span class="auth-spinner" aria-hidden="true"></span></button>
    </form>

    <div class="auth-help">
        <x-icon name="user" class="auth-i" />
        <p><strong>Forgot your password or need an account?</strong><br>Ask your administrator or canteen owner. They can reset it for you.</p>
    </div>
</x-layouts.guest>
