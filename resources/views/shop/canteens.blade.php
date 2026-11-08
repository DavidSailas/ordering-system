@php($currency = config('restaurant.currency'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Choose a canteen · {{ config('restaurant.name') }}</title>
    <x-favicon />
    <link rel="stylesheet" href="{{ asset('customer.css') }}">
    <link rel="stylesheet" href="{{ asset('ui.css') }}?v={{ @filemtime(public_path('ui.css')) }}">
</head>
<body class="picker-body">
<header class="picker-bar">
    <a class="picker-logo" href="{{ route('shop.canteens') }}">
        <x-logo :size="38" />
        <span>{{ config('restaurant.name') }}</span>
    </a>
    <a class="picker-staff" href="{{ route('login') }}"><x-icon name="lock" class="ui-i ui-i--sm" />Staff sign in</a>
</header>

<main class="picker">
    <header class="picker-head">
        <span class="picker-eyebrow">Order online</span>
        <h1>Where would you like to order from?</h1>
        <p>Each canteen has its own menu and its own cashier. Pick one to start your order.</p>
    </header>

    @if (session('success'))
        <div class="notice" role="status">{{ session('success') }}</div>
    @endif

    <div class="picker-grid">
        @foreach ($canteens as $canteen)
            @php($cartConflict = $cartCount > 0 && $currentId && $currentId !== $canteen->id)
            @php($preview = $previews[$canteen->id] ?? ['count' => 0, 'from' => null, 'images' => []])
            <form method="POST" action="{{ route('shop.canteens.choose', $canteen) }}" class="picker-card" style="--brand: {{ $canteen->accent }}"
                  @if ($cartConflict) data-confirm="Your cart has items from another canteen. Switching will empty it." data-confirm-title="Switch canteen?" data-confirm-ok="Yes, switch and clear cart" @endif>
                @csrf

                <div class="picker-card-top">
                    <x-logo :canteen="$canteen" :size="60" />
                    <span class="ui-badge {{ $canteen->is_open ? 'ui-badge--ok' : 'ui-badge--bad' }}">
                        <span class="picker-dot"></span>{{ $canteen->is_open ? 'Open now' : 'Closed' }}
                    </span>
                </div>

                <h2>{{ $canteen->name }}</h2>
                <p class="picker-tagline">{{ $canteen->tagline ?: 'Fresh meals made daily.' }}</p>

                @if ($canteen->address)
                    <p class="picker-meta"><x-icon name="map-pin" class="ui-i ui-i--sm" />{{ $canteen->address }}</p>
                @endif

                @if (count($preview['images']))
                    <div class="picker-thumbs" aria-hidden="true">
                        @foreach ($preview['images'] as $image)
                            <img src="{{ $image }}" alt="" loading="lazy" width="160" height="120">
                        @endforeach
                    </div>
                @endif

                <div class="picker-facts">
                    <span><strong>{{ $preview['count'] }}</strong> {{ $preview['count'] === 1 ? 'dish' : 'dishes' }}</span>
                    @if ($preview['from'] !== null)
                        <span>from <strong>{{ $currency }}{{ number_format($preview['from'], 0) }}</strong></span>
                    @endif
                </div>

                <button type="submit" class="ui-btn ui-btn--primary ui-btn--block picker-cta">
                    {{ $canteen->id === $currentId ? 'Continue ordering' : ($canteen->is_open ? 'Order here' : 'View menu') }}
                    <x-icon name="chevron-right" class="ui-i ui-i--sm" />
                </button>
            </form>
        @endforeach
    </div>
</main>
<script src="{{ asset('ui.js') }}?v={{ @filemtime(public_path('ui.js')) }}" defer></script>
</body>
</html>
