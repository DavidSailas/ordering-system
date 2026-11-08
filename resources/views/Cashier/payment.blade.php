@php($currency = config('restaurant.currency'))

<x-layouts.app :title="'Order #'.$order->number">
    <div class="ui-page-head">
        <div>
            <h1>Order #{{ $order->number }}</h1>
            <p>{{ $order->customer_name }} · {{ $order->type->label() }} · {{ $order->created_at->format('M d, Y h:i A') }}</p>
        </div>
        <div class="ui-actions">
            <a href="{{ route('dashboard') }}" class="ui-btn"><x-icon name="chevron-left" class="ui-i ui-i--sm" />Incoming orders</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="ui-alert ui-alert--bad" role="alert">
            <x-icon name="alert" class="ui-i" />
            <div>@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        </div>
    @endif

    <div class="pay-grid">
        {{-- Order summary --}}
        <section class="ui-card ui-card-pad">
            @if ($order->location || $order->phone)
                <div class="order-loc" style="margin-bottom:14px">
                    <x-icon name="map-pin" class="ui-i" />
                    <span>{{ $order->location ?? 'No location given' }}@if ($order->phone) · {{ $order->phone }}@endif</span>
                </div>
            @endif

            @foreach ($order->items as $item)
                <div class="pay-line"><span>{{ $item->quantity }} × {{ $item->name }}</span><strong>{{ $currency }}{{ number_format($item->price * $item->quantity, 2) }}</strong></div>
            @endforeach

            <div class="pay-line" style="margin-top:6px"><span>Subtotal</span><strong>{{ $currency }}{{ number_format($order->total, 2) }}</strong></div>

            @if ($order->paid_at)
                <div class="pay-line"><span>Discount</span><strong>−{{ $currency }}{{ number_format($order->discount, 2) }}</strong></div>
                <div class="pay-line"><strong>Amount paid</strong><strong class="pay-total">{{ $currency }}{{ number_format($order->amount_paid ?? $order->total, 2) }}</strong></div>
                <p style="margin:12px 0 0;font-size:14px;color:var(--ink-2)">
                    Payment: <strong>{{ strtoupper($order->payment_method ?? 'cash') }}</strong>@if ($order->payment_reference) · Ref: {{ $order->payment_reference }}@endif
                    @if (($order->payment_method ?? 'cash') === 'cash')
                        <br>Cash received: {{ $currency }}{{ number_format($order->cash_received, 2) }} · Change: {{ $currency }}{{ number_format(max(0, $order->cash_received - ($order->amount_paid ?? $order->total)), 2) }}
                    @endif
                    <br>Paid: {{ $order->paid_at->format('M d, Y h:i A') }}
                </p>
                @if ($order->refunded_at)
                    <div class="ui-alert ui-alert--bad" style="margin:14px 0 0"><div><strong>REFUNDED {{ $order->refunded_at->format('M d, Y h:i A') }}</strong><br>{{ $order->refund_reason }}</div></div>
                @endif
            @else
                <div class="pay-line"><strong>Amount due</strong><strong class="pay-total">{{ $currency }}{{ number_format($order->total, 2) }}</strong></div>
            @endif
        </section>

        {{-- Action panel --}}
        <section class="ui-card ui-card-pad">
            @if ($order->paid_at)
                <div class="ui-card-head"><div><h2>Payment received</h2><p>This order is paid.</p></div></div>
                <div style="display:grid;gap:10px">
                    <button type="button" class="ui-btn ui-btn--primary" onclick="window.print()"><x-icon name="receipt" class="ui-i ui-i--sm" />Print receipt</button>
                    <a class="ui-btn" href="{{ route('orders.receipt', [$order, 'print' => 1]) }}" target="_blank" rel="noopener">Open printable receipt</a>
                </div>

                @if (auth()->user()->isManager() && ! $order->refunded_at)
                    <form method="POST" action="{{ route('admin.orders.refund', $order) }}" class="ui-form" style="margin-top:20px;padding-top:18px;border-top:1px solid var(--line)" data-confirm="Record a full refund? You must return the money separately." data-confirm-title="Refund order?" data-confirm-ok="Record refund">
                        @csrf
                        <div class="ui-field">
                            <label for="reason">Refund reason (admin only)</label>
                            <input id="reason" name="reason" required minlength="5" maxlength="500">
                        </div>
                        <button class="ui-btn ui-btn--danger">Record full refund</button>
                    </form>
                @endif
            @elseif ($order->status !== \App\Enums\OrderStatus::Canceled)
                @if ($order->customer_payment_ref)
                    <div class="ui-alert ui-alert--warn">
                        <x-icon name="alert" class="ui-i" />
                        <div>
                            Customer reports paying via <strong>{{ strtoupper($order->payment_choice) }}</strong> · Reference: <strong>{{ $order->customer_payment_ref }}</strong><br>
                            <small>Check this in your {{ strtoupper($order->payment_choice) }} account before confirming below.</small>
                        </div>
                    </div>
                @endif

                <div class="ui-card-head"><div><h2>Collect payment</h2><p>Choose how the customer is paying.</p></div></div>

                <form class="ui-form" method="POST" action="{{ route('orders.payment.store', $order) }}">
                    @csrf
                    <div class="ui-field">
                        <label for="method">Payment method</label>
                        <select name="payment_method" id="method">
                            <option value="cash">Cash</option>
                            <option value="gcash" @selected($order->payment_choice === 'gcash')>GCash</option>
                            <option value="maya" @selected($order->payment_choice === 'maya')>Maya</option>
                        </select>
                    </div>

                    <div class="ui-field">
                        <label for="discount">Discount ({{ $currency }})</label>
                        <input id="discount" type="number" name="discount" min="0" max="{{ $order->total }}" step="0.01" value="0">
                    </div>

                    <div class="pay-line" style="padding:0;border:0"><span>Amount due</span><strong class="pay-total" id="due">{{ $currency }}{{ number_format($order->total, 2) }}</strong></div>

                    <div class="ui-field" id="cash-box">
                        <label for="cash">Cash received</label>
                        <input id="cash" type="number" name="cash_received" step="0.01" min="0" value="{{ $order->total }}">
                    </div>
                    <div class="pay-line" id="change-box" style="padding:0;border:0"><span>Change</span><strong id="change">{{ $currency }}0.00</strong></div>

                    <div class="ui-field" id="reference-box" hidden>
                        <label for="reference">Verified GCash / Maya reference</label>
                        <input name="payment_reference" maxlength="100" id="reference" value="{{ $order->customer_payment_ref }}">
                    </div>

                    <p style="margin:0;font-size:13px;color:var(--muted)">Verify receipt of GCash/Maya funds in your merchant account before confirming. This form does not charge a wallet.</p>
                    <button class="ui-btn ui-btn--primary"><x-icon name="check" class="ui-i ui-i--sm" />Confirm payment</button>
                </form>

                <script>
                    const symbol = @json($currency), total = {{ (float) $order->total }};
                    const method = document.getElementById('method'), discount = document.getElementById('discount'),
                          cash = document.getElementById('cash'), ref = document.getElementById('reference');

                    function update() {
                        const digital = method.value !== 'cash';
                        const due = Math.max(0, Math.round((total - Number(discount.value || 0)) * 100) / 100);
                        document.getElementById('cash-box').hidden = digital;
                        document.getElementById('change-box').hidden = digital;
                        document.getElementById('reference-box').hidden = ! digital;
                        cash.required = ! digital;
                        ref.required = digital;
                        document.getElementById('due').textContent = symbol + due.toFixed(2);
                        document.getElementById('change').textContent = symbol + Math.max(0, Number(cash.value || 0) - due).toFixed(2);
                    }

                    [method, discount, cash].forEach(function (x) { x.addEventListener('input', update); });
                    update();
                </script>
            @else
                <div class="ui-alert ui-alert--bad" style="margin:0"><x-icon name="alert" class="ui-i" />Canceled order — payment unavailable.</div>
            @endif
        </section>
    </div>
</x-layouts.app>
