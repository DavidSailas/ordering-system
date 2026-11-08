@php($currency = config('restaurant.currency'))
<x-layouts.admin title="Dashboard">
    <div class="ui-page-head">
        <div>
            <h1>Welcome back, {{ Str::before(auth()->user()->name, ' ') }}</h1>
            <p>Here's what's happening at {{ config('restaurant.name') }} today.</p>
        </div>
        <div class="ui-actions">
            <a href="{{ route('admin.foods.create') }}" class="ui-btn ui-btn--primary"><x-icon name="plus" class="ui-i ui-i--sm" />Add dish</a>
            <a href="{{ route('admin.settings.edit') }}" class="ui-btn"><x-icon name="settings" class="ui-i ui-i--sm" />Settings</a>
        </div>
    </div>

    @unless (config('restaurant.accepting_orders', true))
        <div class="ui-alert ui-alert--warn"><x-icon name="alert" class="ui-i" />The canteen is currently <strong>closed for new orders</strong>. Customers can browse the menu but cannot check out. <a class="ui-link" href="{{ route('admin.settings.edit') }}">Change in Settings</a></div>
    @endunless

    <div class="ui-stats">
        <a class="ui-card ui-stat" href="{{ route('admin.foods.index') }}">
            <span class="ui-stat-icon"><x-icon name="utensils" class="ui-i" /></span>
            <span class="ui-stat-label">Total products</span>
            <strong>{{ $totalDishes }}</strong>
            <small>{{ $availableDishes }} available on the menu</small>
        </a>
        <a class="ui-card ui-stat" href="{{ route('admin.orders.index') }}">
            <span class="ui-stat-icon ui-stat-icon--info"><x-icon name="clipboard-list" class="ui-i" /></span>
            <span class="ui-stat-label">Orders today</span>
            <strong>{{ $todayOrders }}</strong>
            <small>{{ $activeOrders }} active orders</small>
        </a>
        <a class="ui-card ui-stat" href="{{ route('admin.reports.index') }}">
            <span class="ui-stat-icon ui-stat-icon--ok"><x-icon name="wallet" class="ui-i" /></span>
            <span class="ui-stat-label">Paid sales today</span>
            <strong>{{ $currency }}{{ number_format($cashCollected, 2) }}</strong>
            <small>After discounts and refunds</small>
        </a>
    </div>

    <div class="ui-grid-2">
        <section class="ui-card ui-card-pad">
            <div class="ui-card-head">
                <div><h2>Recent orders</h2><p>Latest orders received by the canteen</p></div>
                <a class="ui-link" href="{{ route('admin.orders.index') }}">View all →</a>
            </div>
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead><tr><th>Order</th><th>Customer</th><th>Deliver to</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($recentOrders as $order)
                            <tr>
                                <td><a href="{{ route('admin.orders.show', $order) }}">#{{ $order->number }}</a></td>
                                <td>{{ $order->customer_name }}</td>
                                <td>{{ $order->location ?? '—' }}</td>
                                <td>{{ $currency }}{{ number_format($order->total, 2) }}</td>
                                <td><x-admin.status-badge :status="$order->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="ui-empty">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="ui-card ui-card-pad">
            <div class="ui-card-head">
                <div><h2>Menu availability</h2><p>What customers can order right now</p></div>
                <a class="ui-link" href="{{ route('admin.foods.index') }}">Manage →</a>
            </div>
            <div style="text-align:center;padding:14px 8px 6px">
                <span class="ui-stat-icon ui-stat-icon--ok" style="margin:0 auto 12px;width:52px;height:52px;border-radius:50%"><x-icon name="check" class="ui-i" /></span>
                <strong style="display:block;font-size:17px">{{ $availableDishes }} of {{ $totalDishes }} products available</strong>
                <p style="margin:6px 0 0;color:var(--muted);font-size:14px">{{ $totalDishes - $availableDishes }} menu items currently unavailable.</p>
            </div>
        </section>
    </div>

    <div class="ui-grid-2">
        <section class="ui-card ui-card-pad">
            <div class="ui-card-head">
                <div><h2>Sales · last 7 days</h2><p>Daily order sales trend</p></div>
                <a class="ui-link" href="{{ route('admin.reports.index') }}">Full report →</a>
            </div>
            <div class="ui-chart">
                @foreach ($salesByDay as $day)
                    <div class="ui-chart-col" title="{{ $day['date'] }} · {{ $day['orders'] }} orders">
                        <span>{{ $day['total'] > 0 ? $currency.number_format($day['total'], 0) : '' }}</span>
                        <div class="ui-chart-bar" style="height: {{ $day['total'] > 0 ? max(3, round($day['total'] / max(1, $maxDailySales) * 100 * .8)) : 1 }}%"></div>
                        <small>{{ $day['label'] }}</small>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="ui-card ui-card-pad">
            <div class="ui-card-head"><div><h2>Popular products</h2><p>Top dishes over the last 7 days</p></div></div>
            <div class="ui-rank">
                @forelse ($topDishes as $dish)
                    <div><span class="ui-rank-no">{{ $loop->iteration }}</span><strong>{{ $dish->name }}</strong><small>{{ (int) $dish->quantity }} sold</small></div>
                @empty
                    <p class="ui-empty" style="margin:0">No sales yet.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.admin>
