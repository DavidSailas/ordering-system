@use('App\Enums\OrderStatus')
@use('Illuminate\Support\Str')

@php
    $paid = $order->isPaid();
    $choice = $order->payment_choice;
    $isNew = ! $order->status->isFinal() && $order->created_at->gt(now()->subMinutes(3));
    $items = $order->items;

    $statusBadge = match ($order->status) {
        OrderStatus::InProgress => ['ui-badge--warn', 'Preparing'],
        OrderStatus::ReadyToServe => ['ui-badge--info', 'Ready'],
        OrderStatus::Completed => ['ui-badge--ok', 'Completed'],
        OrderStatus::Canceled => ['ui-badge--bad', 'Canceled'],
    };

    if ($order->isRefunded()) {
        $payment = ['ui-badge--bad', 'Refunded'];
    } elseif ($paid) {
        $payment = ['ui-badge--ok', 'Paid · '.strtoupper($order->payment_method ?? 'cash')];
    } elseif ($order->customer_payment_ref) {
        $payment = ['ui-badge--warn', 'Verify '.strtoupper((string) $choice).' payment'];
    } elseif (in_array($choice, ['gcash', 'maya'], true)) {
        $payment = ['ui-badge--info', 'Awaiting '.($choice === 'gcash' ? 'GCash' : 'Maya')];
    } else {
        $payment = ['', 'Pay cash'];
    }
@endphp

<article class="order {{ $isNew ? 'order--new' : '' }}" data-order="{{ $order->id }}">
    <div class="order-top">
        <div>
            <span class="order-no">#{{ $order->number }}</span>
            <span class="ui-badge {{ $statusBadge[0] }}">{{ $statusBadge[1] }}</span>
            @if ($isNew)<span class="ui-badge ui-badge--brand">New</span>@endif
        </div>
        <span class="order-time" title="{{ $order->created_at->format('M j, g:i A') }}">{{ $order->created_at->diffForHumans(short: true) }}</span>
    </div>

    <div class="order-who">
        {{ $order->customer_name }}
        @if ($order->phone)<small>{{ $order->phone }}</small>@endif
    </div>

    @if ($order->location)
        <div class="order-loc"><x-icon name="map-pin" class="ui-i" /> {{ $order->location }}</div>
    @else
        <div class="order-loc order-loc--none"><x-icon name="store" class="ui-i" /> {{ $order->type->label() }}</div>
    @endif

    <ul class="order-items">
        @foreach ($items->take(5) as $item)
            <li><b>{{ $item->quantity }}×</b><span>{{ $item->name }}</span></li>
        @endforeach
        @if ($items->count() > 5)
            <li><b></b><span>+ {{ $items->count() - 5 }} more {{ Str::plural('item', $items->count() - 5) }}</span></li>
        @endif
    </ul>

    @if (filled(trim((string) $order->notes)))
        <div class="order-note">{{ Str::limit(trim($order->notes), 140) }}</div>
    @endif

    <div class="order-foot">
        <div>
            <div class="order-total">{{ config('restaurant.currency') }}{{ number_format($order->total, 2) }}</div>
            <span class="ui-badge {{ $payment[0] }}">{{ $payment[1] }}</span>
        </div>

        <div class="order-actions">
            @if ($order->status === OrderStatus::InProgress)
                <form method="POST" action="{{ route('orders.status', $order) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ OrderStatus::ReadyToServe->value }}">
                    <button type="submit" class="ui-btn ui-btn--primary ui-btn--sm"><x-icon name="bell" class="ui-i ui-i--sm" />Mark ready</button>
                </form>
            @elseif ($order->status === OrderStatus::ReadyToServe && $paid)
                <form method="POST" action="{{ route('orders.status', $order) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ OrderStatus::Completed->value }}">
                    <button type="submit" class="ui-btn ui-btn--primary ui-btn--sm"><x-icon name="check" class="ui-i ui-i--sm" />Complete</button>
                </form>
            @endif

            @if (! $paid && $order->status !== OrderStatus::Canceled)
                <a href="{{ route('orders.payment', $order) }}" class="ui-btn {{ $order->status === OrderStatus::ReadyToServe ? 'ui-btn--primary' : 'ui-btn--soft' }} ui-btn--sm"><x-icon name="wallet" class="ui-i ui-i--sm" />Collect payment</a>
            @else
                <a href="{{ route('orders.payment', $order) }}" class="ui-btn ui-btn--sm"><x-icon name="receipt" class="ui-i ui-i--sm" />Receipt</a>
            @endif

            @if (! $order->status->isFinal() && auth()->user()->isManager())
                <form method="POST" action="{{ route('orders.status', $order) }}" data-confirm="Cancel order #{{ $order->number }}?" data-confirm-title="Cancel order?" data-confirm-ok="Yes, cancel order">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ OrderStatus::Canceled->value }}">
                    <button type="submit" class="ui-btn ui-btn--danger ui-btn--sm" aria-label="Cancel order #{{ $order->number }}"><x-icon name="x" class="ui-i ui-i--sm" /></button>
                </form>
            @endif
        </div>
    </div>
</article>
