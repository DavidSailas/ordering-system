@props(['area' => 'admin', 'title' => null])

@use('Illuminate\Support\Str')

@php
    $isAdmin = $area === 'admin';
    $isRunner = $area === 'runner';
    $areaLabel = $isAdmin ? 'Admin' : ($isRunner ? 'Runner' : 'Cashier');

    // [label, route, active pattern, icon]
    $navigation = $isAdmin
        ? [
            ['Dashboard', 'admin.dashboard', 'admin.dashboard', 'layout-dashboard'],
            ['Menu', 'admin.foods.index', 'admin.foods.*', 'utensils'],
            ['Categories', 'admin.categories.index', 'admin.categories.*', 'tag'],
            ['Orders', 'admin.orders.index', 'admin.orders.*', 'clipboard-list'],
            ['Sales & Reports', 'admin.reports.index', 'admin.reports.*', 'bar-chart'],
            ['Staff', 'admin.staff.index', 'admin.staff.*', 'users'],
            ['Settings', 'admin.settings.edit', 'admin.settings.*', 'settings'],
        ]
        : ($isRunner
        ? [
            ['Deliveries', 'runner.dashboard', 'runner.dashboard', 'inbox'],
        ]
        : [
            ['Incoming orders', 'dashboard', 'dashboard', 'inbox'],
            ['Transactions & sales', 'cashier.transactions', 'cashier.transactions', 'receipt'],
            ['Payment records', 'cashier.payment-records', 'cashier.payment-records', 'wallet'],
        ]);

    $user = auth()->user();

    if ($isAdmin && $user->isSuperAdmin()) {
        array_unshift($navigation, ['Users & roles', 'admin.users.index', 'admin.users.*', 'users']);
        array_unshift($navigation, ['All canteens', 'admin.canteens.index', 'admin.canteens.*', 'store']);
    }

    // Phone layout: a few key screens become bottom tabs, everything else goes in a "More" sheet.
    $shortLabels = [
        'dashboard' => 'Orders', 'cashier.transactions' => 'Sales', 'cashier.payment-records' => 'Payments',
        'admin.dashboard' => 'Home', 'admin.orders.index' => 'Orders', 'admin.foods.index' => 'Menu',
        'admin.reports.index' => 'Reports', 'runner.dashboard' => 'Deliveries',
    ];
    $tabKeys = ['admin.dashboard', 'admin.orders.index', 'admin.foods.index', 'admin.reports.index'];
    if ($isAdmin) {
        $tabItems = array_values(array_filter($navigation, fn ($i) => in_array($i[1], $tabKeys, true)));
        usort($tabItems, fn ($a, $b) => array_search($a[1], $tabKeys) <=> array_search($b[1], $tabKeys));
        $moreItems = array_values(array_filter($navigation, fn ($i) => ! in_array($i[1], $tabKeys, true)));
    } else {
        $tabItems = $navigation;
        $moreItems = [];
    }
    $hideTabs = ! $isAdmin && count($tabItems) < 2;   // e.g. runners only have one screen: no tab bar needed
    $moreActive = collect($moreItems)->contains(fn ($i) => request()->routeIs($i[2]));

    $allCanteens = $user->isSuperAdmin() ? \App\Models\Canteen::query()->orderBy('sort_order')->orderBy('id')->get() : collect();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#e25806">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ $isAdmin ? 'Admin · ' : '' }}{{ config('restaurant.name') }}</title>
    <x-favicon />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('ui.css') }}?v={{ @filemtime(public_path('ui.css')) }}">
    <link rel="stylesheet" href="{{ asset('staff-mobile.css') }}?v={{ @filemtime(public_path('staff-mobile.css')) }}">
    @if (! empty($canteenAccentCss))<style>:root{ {!! $canteenAccentCss !!} }</style>@endif
</head>
<body class="ui-body{{ $hideTabs ? ' has-no-tabs' : '' }}">
<div class="ui-shell">
    <aside class="ui-sidebar">
        <a class="ui-brand" href="{{ route($isAdmin ? 'admin.dashboard' : ($isRunner ? 'runner.dashboard' : 'dashboard')) }}">
            <x-logo :size="38" />
            <span>
                <strong>{{ config('restaurant.name') }}</strong>
                <small>{{ $user->isSuperAdmin() ? 'Administrator' : $user->role->label() }}</small>
            </span>
        </a>

        <div class="ui-nav-label">{{ $isAdmin ? 'Workspace' : $areaLabel }}</div>
        <nav class="ui-nav" aria-label="{{ $areaLabel }} navigation">
            @foreach ($navigation as [$label, $target, $pattern, $icon])
                <a href="{{ route($target) }}" class="{{ request()->routeIs($pattern) ? 'is-active' : '' }}">
                    <x-icon :name="$icon" class="ui-i" />{{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="ui-sidebar-foot">
            @if ($isAdmin)
                <a href="{{ route('dashboard') }}"><x-icon name="external" class="ui-i" />Cashier view</a>
                <a href="{{ route('runner.dashboard') }}"><x-icon name="external" class="ui-i" />Runner view</a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="is-danger"><x-icon name="log-out" class="ui-i" />Sign out</button>
            </form>
        </div>
    </aside>

    <div class="ui-main">
        <header class="ui-topbar">
            <a class="ui-topbar-brand" href="{{ route($isAdmin ? 'admin.dashboard' : ($isRunner ? 'runner.dashboard' : 'dashboard')) }}" aria-label="{{ config('restaurant.name') }}">
                <x-logo :size="34" />
            </a>
            <div class="ui-topbar-title">
                <strong>{{ $title ?? $areaLabel }}</strong>
                <small>{{ now()->format('l, F j, Y') }}</small>
            </div>
            @if ($user->isSuperAdmin())
                <form method="POST" class="ui-canteen-switch" id="canteen-switch" action="">
                    @csrf
                    <label for="canteen-select" style="font-size:12.5px;color:var(--muted);font-weight:600">Managing</label>
                    <select id="canteen-select" aria-label="Switch canteen" onchange="var f=document.getElementById('canteen-switch');f.action=this.options[this.selectedIndex].dataset.url;f.submit();">
                        @foreach ($allCanteens as $option)
                            <option value="{{ $option->id }}" data-url="{{ route('admin.canteens.switch', $option) }}" @selected($currentCanteen && $currentCanteen->id === $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </form>
            @elseif ($currentCanteen)
                <span class="ui-canteen-chip"><x-icon name="store" class="ui-i ui-i--sm" />{{ $currentCanteen->name }}</span>
            @endif

            <details class="ui-menu">
                <summary aria-label="Account menu">
                    <span class="ui-avatar">{{ Str::upper(Str::substr($user->name, 0, 1)) }}</span>
                    <span class="ui-account-name">{{ $user->name }}</span>
                    <x-icon name="chevron-down" class="ui-i ui-i--sm" />
                </summary>
                <div class="ui-menu-panel">
                    <div class="ui-menu-head">
                        <strong>{{ $user->name }}</strong>
                        <small>{{ $user->email }}</small>
                        <span class="ui-badge ui-badge--brand">{{ $user->role->label() }}</span>
                    </div>
                    @if ($isAdmin)
                        <a class="ui-menu-item" href="{{ route('admin.settings.edit') }}"><x-icon name="settings" class="ui-i" />Canteen settings</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="ui-menu-item is-danger"><x-icon name="log-out" class="ui-i" />Sign out</button>
                    </form>
                </div>
            </details>
        </header>

        <main class="ui-content">
            @if (session('status'))
                <div class="ui-alert ui-alert--ok" role="status"><x-icon name="check-circle" class="ui-i" />{{ session('status') }}</div>
            @endif
            @if (session('success'))
                <div class="ui-alert ui-alert--ok" role="status"><x-icon name="check-circle" class="ui-i" />{{ session('success') }}</div>
            @endif
            @foreach (['status', 'category', 'staff', 'user'] as $key)
                @error($key)
                    <div class="ui-alert ui-alert--bad" role="alert"><x-icon name="alert" class="ui-i" />{{ $message }}</div>
                @enderror
            @endforeach

            {{ $slot }}
        </main>
    </div>
</div>

<nav class="ui-tabbar" @if ($hideTabs) hidden @endif aria-label="{{ $areaLabel }} tabs">
    @foreach ($tabItems as [$label, $target, $pattern, $icon])
        <a href="{{ route($target) }}" class="{{ request()->routeIs($pattern) ? 'is-active' : '' }}" @if (request()->routeIs($pattern)) aria-current="page" @endif>
            <x-icon :name="$icon" class="ui-i" /><span>{{ $shortLabels[$target] ?? $label }}</span>
        </a>
    @endforeach
    @if ($isAdmin)
        <details class="ui-more {{ $moreActive ? 'is-active' : '' }}">
            <summary aria-label="More">
                <svg class="ui-i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="5" cy="12" r="1.2"/><circle cx="12" cy="12" r="1.2"/><circle cx="19" cy="12" r="1.2"/></svg>
                <span>More</span>
            </summary>
            <div class="ui-more-sheet">
                <h3>More</h3>
                @foreach ($moreItems as [$label, $target, $pattern, $icon])
                    <a href="{{ route($target) }}" class="{{ request()->routeIs($pattern) ? 'is-active' : '' }}"><x-icon :name="$icon" class="ui-i" />{{ $label }}</a>
                @endforeach
                <hr>
                <a href="{{ route('dashboard') }}"><x-icon name="external" class="ui-i" />Cashier view</a>
                <a href="{{ route('runner.dashboard') }}"><x-icon name="external" class="ui-i" />Runner view</a>
                <hr>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="is-danger"><x-icon name="log-out" class="ui-i" />Sign out</button>
                </form>
            </div>
        </details>
    @endif
</nav>

<script>
    // Phones: give every table cell its column title so rows can be shown as cards (see staff-mobile.css).
    (function () {
        function stack(table) {
            var heads = [].map.call(table.querySelectorAll('thead th'), function (th) { return th.textContent.trim(); });
            if (!heads.length) { return; }
            table.classList.add('is-stack');
            [].forEach.call(table.querySelectorAll('tbody tr, tfoot tr'), function (tr) {
                var col = 0;
                [].forEach.call(tr.children, function (td) {
                    var span = td.colSpan || 1;
                    var empty = !td.children.length && !td.textContent.trim();
                    if (span > 1) { td.classList.add('is-wide'); }
                    else if (empty && col > 0) { td.classList.add('is-empty'); }
                    else if (heads[col] && !/^actions?$/i.test(heads[col])) { td.setAttribute('data-label', heads[col]); }
                    else { td.classList.add('is-actions'); }
                    col += span;
                });
            });
        }
        [].forEach.call(document.querySelectorAll('.ui-content table'), stack);

        var more = document.querySelector('.ui-more');
        if (more) {
            document.addEventListener('click', function (e) { if (more.open && (e.target === more || !more.contains(e.target))) { more.open = false; } });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { more.open = false; } });
        }
    })();
</script>
<script src="{{ asset('ui.js') }}?v={{ @filemtime(public_path('ui.js')) }}" defer></script>
</body>
</html>
