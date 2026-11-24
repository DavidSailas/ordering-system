@extends('shop.layout')
@section('title', 'Scan to order · Cecilian Bites')
@section('content')
<style>
    .pass-wrap { max-width: 520px; margin: 24px auto 0; }
    .pass-card { padding: 28px 24px; text-align: center; }
    .pass-icon { display: grid; place-items: center; width: 72px; height: 72px; margin: 0 auto 16px; border-radius: 22px; background: var(--brand-tint); color: var(--brand-dark); }
    .pass-icon svg { width: 38px; height: 38px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
    .pass-card h1 { font-size: 24px; margin-bottom: 8px; }
    .pass-card p { color: var(--muted); font-size: 14.5px; }
    .pass-steps { display: grid; gap: 10px; margin: 20px 0 4px; padding: 0; list-style: none; text-align: left; }
    .pass-steps li { display: flex; gap: 12px; align-items: flex-start; padding: 12px 14px; border-radius: 12px; background: var(--bg); font-size: 14px; color: var(--ink-2); }
    .pass-steps b { display: grid; place-items: center; flex: none; width: 24px; height: 24px; border-radius: 50%; background: var(--brand); color: #fff; font-size: 12px; }
    .pass-or { display: flex; align-items: center; gap: 12px; margin: 22px 0 16px; color: var(--faint); font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    .pass-or::before, .pass-or::after { content: ""; flex: 1; height: 1px; background: var(--line); }
    .pass-form { display: grid; gap: 10px; text-align: left; }
    .pass-form label { font-size: 13.5px; }
    .pass-form input { padding: 14px; font-size: 22px; font-weight: 700; letter-spacing: .25em; text-align: center; text-transform: uppercase; }
    .pass-form .button { padding: 13px 18px; font-size: 15px; }
    .pass-error { margin: 0; padding: 10px 14px; border-radius: 10px; background: var(--bad-bg); color: #7f1d1d !important; font-size: 13.5px; font-weight: 600; text-align: left; }
    .pass-back { display: inline-block; margin-top: 18px; color: var(--brand-dark); font-size: 14px; font-weight: 600; }
</style>

<div class="pass-wrap">
    <section class="panel pass-card">
        <div class="pass-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/><rect x="3" y="14" width="7" height="7" rx="1.2"/><path d="M14 14h3v3m4 0v4h-4m-3-1v1m0-4h.01M21 14h.01"/></svg>
        </div>
        <h1>Scan the QR code to order</h1>
        <p>Ordering is for our school community. Look for the Cecilian Bites QR code posted at the canteen or in your classroom and scan it with your phone camera. You only need to do this once.</p>

        <ol class="pass-steps">
            <li><b>1</b><span>Open your phone camera and point it at the QR code.</span></li>
            <li><b>2</b><span>Tap the link that appears, and you are ready to order.</span></li>
        </ol>

        <div class="pass-or">or</div>

        <form method="POST" action="{{ route('pass.store') }}" class="pass-form">
            @csrf
            <label for="code">Can't scan? Type the code written on the poster</label>
            <input id="code" name="code" value="{{ old('code') }}" maxlength="20" autocomplete="off" autocapitalize="characters" spellcheck="false" inputmode="text" placeholder="ABC123" required>
            @error('code')<p class="pass-error" role="alert">{{ $message }}</p>@enderror
            <button type="submit" class="button">Continue</button>
        </form>

        <a class="pass-back" href="{{ route('shop.menu') }}">&larr; Just looking? Browse the menu</a>
    </section>
</div>
@endsection
