@extends('shop.layout')
@section('title', 'Pay with '.$account['label'].' · Cecilian Bites')
@section('content')
<section class="cb-pay-page">
  <a class="cb-back-link" href="{{ route('shop.orders') }}">← Back to My orders</a>
  <p class="eyebrow" style="margin-top:14px">Online payment</p>
  <h1 class="page-title">Pay with {{ $account['label'] }}</h1>
  <p class="muted">Order #{{ $order->number }} is already placed. Send your payment, then enter the reference number below so the cashier can confirm it.</p>

  <div class="checkout-layout">
    <div class="panel checkout-form">
      <div class="pay-step">
        <span class="pay-step-no">1</span>
        <div>
          <h2>Send ₱{{ number_format($order->total, 2) }} to our {{ $account['label'] }}</h2>
          <p class="muted">Open your {{ $account['label'] }} app and send the exact amount to this number.</p>
          <div class="pay-account">
            <div>
              <small>{{ $account['label'] }} number</small>
              <strong id="pay-number">{{ $account['number'] }}</strong>
              <span>{{ $account['name'] }}</span>
            </div>
            <button type="button" class="button secondary" id="copy-number">Copy number</button>
          </div>
          @if($qr)<img class="pay-qr" src="{{ $qr }}" alt="{{ $account['label'] }} QR code">@endif
        </div>
      </div>

      <form class="pay-step" method="post" action="{{ route('shop.pay.store', [$order, $method]) }}">
        @csrf
        <span class="pay-step-no">2</span>
        <div>
          <h2>Enter your reference number</h2>
          <p class="muted">You'll find it in your {{ $account['label'] }} confirmation message or transaction history.</p>
          <label>Reference number
            <input name="reference" value="{{ old('reference', $order->payment_choice === $method ? $order->customer_payment_ref : '') }}" minlength="6" maxlength="40" autocomplete="off" required placeholder="e.g. 1234 567 890123">
          </label>
          <button class="button" style="margin-top:14px">Submit payment details</button>
        </div>
      </form>

      <p class="pay-secure">Your order stays unpaid until the cashier confirms the {{ $account['label'] }} payment. You can still pay at pickup instead.</p>
    </div>

    <aside class="panel summary">
      <h2>Order summary</h2>
      @foreach($order->items as $item)
        <div class="summary-line"><span>{{ $item->quantity }} × {{ $item->name }}</span><strong>₱{{ number_format($item->price * $item->quantity, 2) }}</strong></div>
      @endforeach
      <div class="total"><span>Amount to send</span><strong>₱{{ number_format($order->total, 2) }}</strong></div>
    </aside>
  </div>
</section>
@endsection
@section('scripts')
<script>
document.getElementById('copy-number').addEventListener('click', function () {
  var button = this, text = document.getElementById('pay-number').textContent.trim();
  var done = function () { button.textContent = 'Copied'; setTimeout(function () { button.textContent = 'Copy number'; }, 1800); };
  if (navigator.clipboard) { navigator.clipboard.writeText(text).then(done); }
  else { var r = document.createRange(); r.selectNodeContents(document.getElementById('pay-number')); var s = getSelection(); s.removeAllRanges(); s.addRange(r); document.execCommand('copy'); done(); }
});
</script>
@endsection
