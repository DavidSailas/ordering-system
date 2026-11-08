@php($currency = config('restaurant.currency'))
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Receipt #{{ $order->number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e5e5; font: 13px/1.45 ui-monospace, 'Courier New', monospace; color: #000; }
        .toolbar { display: flex; gap: 8px; justify-content: center; padding: 12px; }
        .toolbar a, .toolbar button { padding: 8px 14px; border: 1px solid #555; border-radius: 6px; background: #fff; color: #000; font: inherit; text-decoration: none; cursor: pointer; }
        .receipt { width: 80mm; max-width: 100%; margin: 0 auto 24px; padding: 6mm 5mm; background: #fff; }
        .center { text-align: center; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        .row span:first-child { min-width: 0; overflow-wrap: anywhere; }
        .muted { color: #444; font-size: 12px; }
        .rule { margin: 8px 0; border: 0; border-top: 1px dashed #000; }
        .total { font-size: 15px; font-weight: 700; }
        .stamp { margin: 8px 0; padding: 4px; border: 2px solid #000; text-align: center; font-weight: 700; letter-spacing: 1px; }
        h1 { margin: 0; font-size: 17px; }
        @page { size: 80mm auto; margin: 0; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .receipt { width: 80mm; margin: 0; padding: 4mm; }
        }
    </style>
</head>
<body @if ($autoprint) onload="window.print()" @endif>
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">Print receipt</button>
        <a href="{{ route('orders.payment', $order) }}">← Back to order</a>
    </div>

    <main class="receipt">
        <div class="center">
            <h1>{{ config('restaurant.name') }}</h1>
            @if (config('restaurant.address'))<div class="muted">{{ config('restaurant.address') }}</div>@endif
            @if (config('restaurant.phone'))<div class="muted">Tel: {{ config('restaurant.phone') }}</div>@endif
        </div>

        <hr class="rule">

        @if ($order->isPaid())
            <div class="center"><strong>RECEIPT</strong></div>
        @else
            <div class="stamp">ORDER SLIP · UNPAID</div>
        @endif

        <div class="row"><span>Order #</span><span>{{ $order->number }}</span></div>
        <div class="row"><span>Date</span><span>{{ ($order->paid_at ?? $order->created_at)->format('M d, Y h:i A') }}</span></div>
        <div class="row"><span>Customer</span><span>{{ $order->customer_name }}</span></div>
        <div class="row"><span>Type</span><span>{{ $order->type->label() }}</span></div>
        @if ($order->location)
            <div class="row"><span>Deliver to</span><span>{{ $order->location }}</span></div>
        @endif
        @if ($order->cashier)
            <div class="row"><span>Cashier</span><span>{{ $order->cashier->name }}</span></div>
        @endif

        <hr class="rule">

        @foreach ($order->items as $item)
            <div class="row">
                <span>{{ $item->quantity }} × {{ $item->name }}</span>
                <span>{{ $currency }}{{ number_format($item->price * $item->quantity, 2) }}</span>
            </div>
            @if ($item->quantity > 1)
                <div class="muted">&nbsp;&nbsp;@ {{ $currency }}{{ number_format($item->price, 2) }} each</div>
            @endif
        @endforeach

        <hr class="rule">

        <div class="row"><span>Subtotal</span><span>{{ $currency }}{{ number_format($order->total, 2) }}</span></div>

        @if ($order->isPaid())
            @if ((float) $order->discount > 0)
                <div class="row"><span>Discount</span><span>−{{ $currency }}{{ number_format($order->discount, 2) }}</span></div>
            @endif
            <div class="row total"><span>TOTAL PAID</span><span>{{ $currency }}{{ number_format($order->amountCharged(), 2) }}</span></div>

            <hr class="rule">

            <div class="row"><span>Payment</span><span>{{ $order->paymentMethodOrCash()->label() }}</span></div>
            @if ($order->payment_reference)
                <div class="row"><span>Reference</span><span>{{ $order->payment_reference }}</span></div>
            @endif
            @if ($order->paymentMethodOrCash() === \App\Enums\PaymentMethod::Cash)
                <div class="row"><span>Cash received</span><span>{{ $currency }}{{ number_format($order->cash_received, 2) }}</span></div>
                <div class="row"><span>Change</span><span>{{ $currency }}{{ number_format($order->changeDue(), 2) }}</span></div>
            @endif

            @if ($order->isRefunded())
                <div class="stamp">REFUNDED {{ $order->refunded_at->format('M d, Y') }}</div>
            @endif
        @else
            <div class="row total"><span>AMOUNT DUE</span><span>{{ $currency }}{{ number_format($order->total, 2) }}</span></div>
        @endif

        <hr class="rule">

        <div class="center muted">{{ config('restaurant.receipt_footer') }}</div>
    </main>
</body>
</html>
