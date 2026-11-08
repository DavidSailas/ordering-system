@extends('shop.layout')
@section('title', 'Checkout · Cecilian Bites')
@section('content')
@php
    $payOptions = \App\Support\StorePayments::available();
    $buildings = config('restaurant.buildings');
    $orderTotal = array_sum(array_column($lines, 'cents')) / 100;
    $itemCount = array_sum(array_column($lines, 'quantity'));
@endphp

<div class="co-head">
    <div>
        <p class="eyebrow">Final step</p>
        <h1 class="page-title">Checkout</h1>
        <p class="co-sub">Tell us where to bring your order and how you would like to pay.</p>
    </div>
    <ol class="co-steps" aria-label="Order progress">
        <li class="is-done"><span>✓</span> Cart</li>
        <li class="is-current"><span>2</span> Details</li>
        <li><span>3</span> Done</li>
    </ol>
</div>

<div class="checkout-layout">
    <form id="checkout-form" class="checkout-form co-form" method="post" action="{{ route('shop.purchase') }}">
        @csrf
        <input type="hidden" name="checkout_token" value="{{ session('shop.checkout_token') }}">

        {{-- 1 · Who is ordering --}}
        <section class="co-card">
            <header class="co-card-head"><span class="co-num">1</span><div><h2>Your details</h2><p>So we know who the order is for.</p></div></header>
            <div class="co-grid">
                <label class="co-field">
                    <span class="co-label">Full name</span>
                    <input name="customer_name" autocomplete="name" value="{{ old('customer_name', session('shop.profile.customer_name', '')) }}" maxlength="120" placeholder="e.g. Juan Dela Cruz" required @class(['has-error' => $errors->has('customer_name')])>
                    @error('customer_name')<small class="co-error">{{ $message }}</small>@enderror
                </label>
                <label class="co-field">
                    <span class="co-label">Phone number</span>
                    <input name="phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('phone', session('shop.profile.phone', '')) }}" maxlength="25" placeholder="e.g. 0917 123 4567" required @class(['has-error' => $errors->has('phone')])>
                    @error('phone')<small class="co-error">{{ $message }}</small>@enderror
                </label>
            </div>
        </section>

        {{-- 2 · Where to bring it --}}
        <section class="co-card">
            <header class="co-card-head"><span class="co-num">2</span><div><h2>Delivery</h2><p>Where should we bring your order? Enter your building, your room, or both.</p></div></header>
            <div class="co-grid">
                <label class="co-field">
                    <span class="co-label">Building</span>
                    @if($buildings)
                        <select name="building" @class(['has-error' => $errors->has('building')])>
                            <option value="">Select building</option>
                            @foreach($buildings as $b)<option value="{{ $b }}" @selected(old('building', session('shop.profile.building', '')) === $b)>{{ $b }}</option>@endforeach
                        </select>
                    @else
                        <input name="building" maxlength="80" value="{{ old('building', session('shop.profile.building', '')) }}" placeholder="e.g. Main Building" @class(['has-error' => $errors->has('building')])>
                    @endif
                    @error('building')<small class="co-error">{{ $message }}</small>@enderror
                </label>
                <label class="co-field">
                    <span class="co-label">Room number</span>
                    <input name="room" maxlength="40" value="{{ old('room', session('shop.profile.room', '')) }}" placeholder="e.g. 204" @class(['has-error' => $errors->has('room')])>
                    @error('room')<small class="co-error">{{ $message }}</small>@enderror
                </label>
            </div>
            <label class="co-field">
                <span class="co-label">Order notes <em>optional</em></span>
                <textarea name="notes" rows="3" maxlength="1000" placeholder="Any requests for your order? (e.g. no ice, extra spoon)">{{ old('notes') }}</textarea>
            </label>
        </section>

        {{-- 3 · Payment --}}
        <section class="co-card">
            <header class="co-card-head"><span class="co-num">3</span><div><h2>Payment</h2><p>Choose how you would like to pay.</p></div></header>
            <fieldset class="pay-fieldset">
                <legend class="sr-only">How would you like to pay?</legend>
                <div class="pay-methods">
                    <label class="pay-method">
                        <input type="radio" name="payment_choice" value="cash" @checked(old('payment_choice', 'cash') === 'cash')>
                        <span class="pay-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 9.5v.01M18 14.5v.01"/></svg></span>
                        <span class="pay-method-body"><strong>Cash</strong><small>Pay when you receive your order</small></span>
                    </label>
                    @foreach($payOptions as $key => $account)
                        <label class="pay-method">
                            <input type="radio" name="payment_choice" value="{{ $key }}" @checked(old('payment_choice') === $key)>
                            <span class="pay-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M10.5 18h3"/></svg></span>
                            <span class="pay-method-body"><strong>{{ $account['label'] }}</strong><small>Send your payment to our {{ $account['label'] }} number after placing your order</small></span>
                        </label>
                    @endforeach
                </div>
                @error('payment_choice')<small class="co-error">{{ $message }}</small>@enderror
            </fieldset>
        </section>
    </form>

    <aside class="summary co-summary">
        <h2>Order summary</h2>
        <p class="co-from">From <strong>{{ config('restaurant.name') }}</strong> · {{ $itemCount }} {{ $itemCount === 1 ? 'item' : 'items' }}</p>

        <div class="co-items">
            @foreach($lines as $line)
                <div class="co-item">
                    <span class="co-qty">{{ $line['quantity'] }}×</span>
                    <span class="co-item-name">{{ $line['food']->name }}</span>
                    <strong>₱{{ number_format($line['cents'] / 100, 2) }}</strong>
                </div>
            @endforeach
        </div>

        <div class="total"><span>Total</span><strong>₱{{ number_format($orderTotal, 2) }}</strong></div>

        <button type="submit" form="checkout-form" class="button co-submit">Place order</button>
        <p class="co-note">Your order goes straight to the canteen. You can follow it under “My orders”.</p>
        <a class="co-edit" href="{{ route('shop.cart') }}">← Edit cart</a>
    </aside>
</div>
@endsection
@section('scripts')<script>document.querySelector('#checkout-form').addEventListener('submit',function(){document.querySelectorAll('button[form="checkout-form"]').forEach(function(b){b.disabled=true;b.textContent='Placing your order…';});});</script>@endsection
