@props(['title' => null, 'variant' => 'staff', 'wide' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('restaurant.name') }}</title>
    <x-favicon />
    <link rel="stylesheet" href="{{ asset('auth.css') }}?v={{ @filemtime(public_path('auth.css')) }}">
    <link rel="stylesheet" href="{{ asset('ui.css') }}?v={{ @filemtime(public_path('ui.css')) }}">
    <link rel="stylesheet" href="{{ asset('clean-scrollbars.css') }}">
</head>
<body class="auth">
<div class="auth-shell">
    <section class="auth-aside" aria-hidden="true">
        <a class="auth-brand" href="{{ route('home') }}" tabindex="-1">
            <x-logo :size="46" />
            <span>{{ config('restaurant.name') }}<small>{{ $variant === 'staff' ? 'Canteen management' : 'Order online' }}</small></span>
        </a>

        <div class="auth-hero">
            @if ($variant === 'staff')
                <span class="auth-eyebrow"><x-icon name="shield" class="auth-i" />Staff portal</span>
                <h2>Everything your canteen needs, in one place.</h2>
                <p>Take orders, manage the menu and follow your sales, securely and from any device.</p>
                <ul class="auth-points">
                    <li><x-icon name="check-circle" class="auth-i" />Live incoming orders with payment status</li>
                    <li><x-icon name="check-circle" class="auth-i" />Menu, staff and reports for each canteen</li>
                    <li><x-icon name="check-circle" class="auth-i" />Separate access for every role</li>
                </ul>
                <div class="auth-roles"><span>Admin</span><span>Owner</span><span>Cashier</span><span>Runner</span></div>
            @else
                <span class="auth-eyebrow"><x-icon name="utensils" class="auth-i" />Order online</span>
                <h2>Fresh food, ready when you are.</h2>
                <p>Order from our canteens and have it brought to your room or building.</p>
                <ul class="auth-points">
                    <li><x-icon name="check-circle" class="auth-i" />Browse the menu and order in a few taps</li>
                    <li><x-icon name="check-circle" class="auth-i" />Follow your order from kitchen to counter</li>
                    <li><x-icon name="check-circle" class="auth-i" />Pay when it arrives</li>
                </ul>
            @endif
        </div>

        <small>© {{ date('Y') }} {{ config('restaurant.name') }}</small>
    </section>

    <main class="auth-main">
        <a class="auth-back" href="{{ route('home') }}"><x-icon name="arrow-left" class="auth-i" />Back to ordering</a>

        <div @class(['auth-card', 'auth-card--wide' => $wide])>
            <a class="auth-mobile-brand" href="{{ route('home') }}"><x-logo :size="40" />{{ config('restaurant.name') }}</a>
            {{ $slot }}
        </div>

        <p class="auth-legal"><x-icon name="lock" class="auth-i" />Secure connection · activity is recorded</p>
    </main>
</div>
<script>
document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
    button.addEventListener('click', function () {
        var input = document.getElementById(button.dataset.togglePassword);
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.textContent = show ? 'Hide' : 'Show';
        button.setAttribute('aria-pressed', show ? 'true' : 'false');
        input.focus();
    });
});

/* Caps Lock warning */
document.querySelectorAll('input[type="password"]').forEach(function (input) {
    ['keydown', 'keyup'].forEach(function (name) {
        input.addEventListener(name, function (e) {
            var hint = document.getElementById('caps-hint');
            if (hint && e.getModifierState) { hint.hidden = ! e.getModifierState('CapsLock'); }
        });
    });
    input.addEventListener('blur', function () {
        var hint = document.getElementById('caps-hint');
        if (hint) { hint.hidden = true; }
    });
});

/* Loading state so a double click cannot submit twice */
document.querySelectorAll('form[data-auth-form]').forEach(function (form) {
    form.addEventListener('submit', function () {
        var button = form.querySelector('.auth-submit');
        if (button) { button.classList.add('is-loading'); button.setAttribute('aria-busy', 'true'); setTimeout(function () { button.disabled = true; }, 0); }
    });
});
window.addEventListener('pageshow', function (e) {
    if (e.persisted) {
        document.querySelectorAll('.auth-submit.is-loading').forEach(function (button) {
            button.classList.remove('is-loading'); button.disabled = false; button.removeAttribute('aria-busy');
        });
    }
});
</script>
</body>
</html>
