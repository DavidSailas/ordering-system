@php
    $cartCount = \App\Support\ShopCart::for(request())->count();
    $isMenu = request()->routeIs('home', 'shop.menu');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <title>@yield('title', 'Cecilian Bites · Freshly made, happily shared')</title>
    <x-favicon />
    <link rel="stylesheet" href="{{ asset('customer.css') }}">
    <link rel="stylesheet" href="{{ asset('ui.css') }}?v={{ @filemtime(public_path('ui.css')) }}">
    <link rel="stylesheet" href="{{ asset('clean-scrollbars.css') }}">
    <link rel="stylesheet" href="{{ asset('mobile.css') }}?v={{ @filemtime(public_path('mobile.css')) }}">
    @if (! empty($canteenAccentCss))<style>:root{ {!! $canteenAccentCss !!} }</style>@endif
</head>
<body>
<div class="customer-shell">
    <aside class="customer-sidebar">
        <a class="side-brand" href="{{ route('home') }}">
            <x-logo :size="38" />
            <span class="brand-text"><strong>{{ config('restaurant.name') }}</strong><small>Order online</small></span>
        </a>

        @if (($canteenCount ?? 1) > 1 && ! empty($currentCanteen))
            <a class="canteen-switch" href="{{ route('shop.canteens') }}"><span>Ordering from</span><strong>{{ $currentCanteen->name }}</strong><small>Switch canteen</small></a>
        @endif

        <nav aria-label="Customer navigation">
            <span class="nav-label">Shop</span>
            <a href="{{ route('shop.menu') }}" @class(['is-active' => $isMenu])>
                <svg class="icon" viewBox="0 0 24 24"><path d="M4 3v8a2 2 0 0 0 2 2v8M8 3v6M6 3v6M16 21V3c-2.5 1-4 4-4 8h4"/></svg>Menu
            </a>
            <a href="{{ route('shop.cart') }}" @class(['is-active' => request()->routeIs('shop.cart', 'shop.checkout')])>
                <svg class="icon" viewBox="0 0 24 24"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M3 4h2.5l2.2 11h10.6L20 8H6.4"/></svg>Cart
                @if($cartCount)<span class="nav-count">{{ $cartCount }}</span>@endif
            </a>
            <a href="{{ route('shop.orders') }}" @class(['is-active' => request()->routeIs('shop.orders')])>
                <svg class="icon" viewBox="0 0 24 24"><path d="M7 3h10a2 2 0 0 1 2 2v16l-3-2-2 2-2-2-2 2-2-2-3 2V5a2 2 0 0 1 2-2Z"/><path d="M9 8h6M9 12h6"/></svg>My orders
            </a>
            <span class="nav-label" style="margin-top:14px">Account</span>
            <a href="{{ route('shop.profile') }}" @class(['is-active' => request()->routeIs('shop.profile')])>
                <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>My profile
            </a>
            @auth
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button type="submit"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>Sign out</button>
                </form>
            @endauth
        </nav>

        <div class="sidebar-note">
            <h3>How ordering works</h3>
            <p>Choose your dishes, tell us your building and room, and pay when it arrives.</p>
        </div>
    </aside>

    <div class="customer-content">
        <header class="topbar">
            <a class="brand" href="{{ route('home') }}"><x-logo :size="34" /><span>{{ config('restaurant.name') }}</span></a>
            @if (($canteenCount ?? 1) > 1 && ! empty($currentCanteen))
                <a class="topbar-switch" href="{{ route('shop.canteens') }}" aria-label="Switch canteen">Switch</a>
            @endif
            <div class="topbar-info">
                <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                Deliver to your room / building · Pay on receipt
            </div>
            <a class="cart-link" href="{{ route('shop.cart') }}">
                <svg class="icon" viewBox="0 0 24 24"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M3 4h2.5l2.2 11h10.6L20 8H6.4"/></svg>
                Cart <b>{{ $cartCount }}</b>
            </a>
        </header>

        <main>
            @unless(config('restaurant.accepting_orders', true))<div class="notice error" role="alert">We are not accepting orders right now. You can still browse the menu.</div>@endunless
            @if(session('success'))<div class="notice" role="status">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="notice error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            @yield('content')
        </main>

        <footer>
            <span>© {{ date('Y') }} {{ config('restaurant.name') }}</span>
            <span>Good food. A little joy in every bite. · <a href="{{ route('login') }}" style="color:inherit;opacity:.7">Staff login</a></span>
        </footer>
    </div>
</div>
<nav class="mobile-tabs" aria-label="Main navigation">
    <a href="{{ route('shop.menu') }}" @class(['is-active' => $isMenu])>
        <svg class="icon" viewBox="0 0 24 24"><path d="M4 3v8a2 2 0 0 0 2 2v8M8 3v6M6 3v6M16 21V3c-2.5 1-4 4-4 8h4"/></svg>Menu
    </a>
    <a href="{{ route('shop.cart') }}" @class(['is-active' => request()->routeIs('shop.cart', 'shop.checkout')])>
        <svg class="icon" viewBox="0 0 24 24"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M3 4h2.5l2.2 11h10.6L20 8H6.4"/></svg>Cart
        @if($cartCount)<span class="tab-badge">{{ $cartCount }}</span>@endif
    </a>
    <a href="{{ route('shop.orders') }}" @class(['is-active' => request()->routeIs('shop.orders', 'shop.pay')])>
        <svg class="icon" viewBox="0 0 24 24"><path d="M7 3h10a2 2 0 0 1 2 2v16l-3-2-2 2-2-2-2 2-2-2-3 2V5a2 2 0 0 1 2-2Z"/><path d="M9 8h6M9 12h6"/></svg>Orders
    </a>
    <a href="{{ route('shop.profile') }}" @class(['is-active' => request()->routeIs('shop.profile')])>
        <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>Profile
    </a>
</nav>
<script src="{{ asset('ui.js') }}?v={{ @filemtime(public_path('ui.js')) }}" defer></script>
@yield('scripts')
</body>
</html>
