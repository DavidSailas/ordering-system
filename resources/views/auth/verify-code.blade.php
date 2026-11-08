<x-layouts.guest title="Verify your email" variant="customer">
    <h1>Check your email</h1>
    <p class="auth-sub">We sent a 6-digit code to <strong>{{ $maskedEmail }}</strong>. It expires in {{ $ttl }} minutes.</p>

    @if (session('status'))
        <div class="auth-alert auth-alert--ok" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="auth-alert" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('register.verify.store') }}" class="auth-form" data-auth-form>
        @csrf
        <div class="auth-field">
            <label for="code">Verification code</label>
            <div class="auth-input @error('code') has-error @enderror">
                <input id="code" name="code" class="auth-code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="••••••" required autofocus>
            </div>
        </div>
        <button class="auth-submit">Verify and create account</button>
    </form>

    <form method="POST" action="{{ route('register.resend') }}" class="auth-alt" style="margin-top:18px">
        @csrf
        Didn't get it? Check your spam folder or <button type="submit" class="auth-linkbtn">send a new code</button>
    </form>
    <p class="auth-alt"><a href="{{ route('register') }}">Use a different email</a></p>
</x-layouts.guest>
