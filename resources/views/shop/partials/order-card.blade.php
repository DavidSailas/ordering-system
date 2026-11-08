<article class="cb-order-card">
  <div class="cb-order-card-head"><div><span class="cb-order-id">ORDER #{{ $order->number }}</span><p>{{ $order->created_at->format('M d, Y · h:i A') }}</p></div><span class="cb-order-badge cb-order-badge--{{ $order->status->value }}">{{ $order->status->label() }}</span></div>
  <div class="cb-order-progress" aria-label="Order status">
    @foreach(['Order placed', 'Preparing', 'Ready', 'Completed'] as $stepIndex => $step)
      @php
        $stage = match($order->status->value) { 'in_progress' => 1, 'ready_to_serve' => 2, 'completed' => 3, default => -1 };
      @endphp
      <div class="cb-order-step {{ $stage >= $stepIndex ? 'is-done' : '' }}"><span class="cb-order-dot">{{ $stage >= $stepIndex ? '✓' : $stepIndex + 1 }}</span><small>{{ $step }}</small></div>
    @endforeach
  </div>
  @if($order->relationLoaded('canteen') && $order->canteen)<p class="muted" style="margin:6px 0 0">{{ $order->canteen->name }}</p>@endif
  @if($order->location)<p class="muted" style="margin:6px 0 0">Deliver to: <b>{{ $order->location }}</b></p>@endif
  <div class="cb-order-items">
    @foreach($order->items as $item)
      <div><span><b>{{ $item->quantity }}×</b> {{ $item->name }}</span><strong>₱{{ number_format($item->price * $item->quantity, 2) }}</strong></div>
    @endforeach
  </div>
  <div class="cb-order-card-bottom"><div><small>@if($order->paid_at) Paid{{ in_array($order->payment_method, ['gcash', 'maya']) ? ' via '.($order->payment_method === 'gcash' ? 'GCash' : 'Maya') : '' }} @elseif($order->customer_payment_ref) {{ $order->payment_choice === 'gcash' ? 'GCash' : 'Maya' }} payment submitted · awaiting confirmation @elseif(in_array($order->payment_choice, ['gcash', 'maya'])) Awaiting {{ $order->payment_choice === 'gcash' ? 'GCash' : 'Maya' }} payment @else Payment at pickup @endif</small><strong>₱{{ number_format($order->total, 2) }}</strong></div><p>@switch($order->status->value) @case('ready_to_serve') Your order is ready for pickup! @break @case('completed') Thank you for ordering with us! @break @case('canceled') This order was cancelled. @break @default We're preparing your order. @endswitch</p></div>
  @php $payAccounts = \App\Support\StorePayments::available(); @endphp
  @if(!$order->paid_at && $order->status->value !== 'canceled' && $payAccounts)
    <div class="cb-order-pay">
      @if($order->customer_payment_ref)
        <span>Reference <strong>{{ $order->customer_payment_ref }}</strong> sent. The cashier will confirm it shortly.</span>
      @else
        <span>Prefer to pay now instead of at pickup?</span>
      @endif
      <div class="cb-order-pay-actions">
        @foreach($payAccounts as $key => $account)
          <a class="button {{ $order->customer_payment_ref && $order->payment_choice === $key ? 'secondary' : '' }}" href="{{ route('shop.pay', [$order, $key]) }}">{{ $order->customer_payment_ref && $order->payment_choice === $key ? 'Update '.$account['label'].' reference' : 'Pay with '.$account['label'] }}</a>
        @endforeach
      </div>
    </div>
  @endif
</article>
