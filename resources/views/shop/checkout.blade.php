@extends('shop.layout')
@section('title', 'Checkout · Cecilian Bites')
@section('content')
@php
    $payOptions = \App\Support\StorePayments::available();
    $orderTotal = array_sum(array_column($lines, 'cents')) / 100;
    $itemCount = array_sum(array_column($lines, 'quantity'));

    // Delivery needs at least one building AND an online payment option (delivery is paid in advance).
    $canDeliver = $deliveryZones->isNotEmpty() && count($payOptions) > 0;
    $mode = old('fulfillment', $canDeliver && session('shop.profile.zone_id') ? 'delivery' : 'pickup');
    $mode = $canDeliver ? $mode : 'pickup';
    $zoneId = (int) old('zone_id', session('shop.profile.zone_id', $deliveryZones->first()?->id));
    $chosenZone = $deliveryZones->firstWhere('id', $zoneId) ?? $deliveryZones->first();
    $floorNow = (int) old('floor', session('shop.profile.floor', 1));
    $feeData = $deliveryZones->mapWithKeys(fn ($z) => [$z->id => ['floors' => $z->floors, 'fees' => $z->feeTable()]]);
    $pickupLabel = $pickupAt ? $pickupAt->name.', floor '.$pickupAt->home_floor : config('restaurant.name');
@endphp

<div class="co-head">
    <div>
        <p class="eyebrow">Final step</p>
        <h1 class="page-title">Checkout</h1>
        <p class="co-sub">Choose pickup or delivery, then how you would like to pay.</p>
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
        {{-- Bot trap: people never see this field, scripts fill it in. --}}
        <div style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden" aria-hidden="true">
            <label>Leave this empty <input type="text" name="website" value="" tabindex="-1" autocomplete="off"></label>
        </div>

        {{-- 1 · Who is ordering --}}
        <section class="co-card">
            <header class="co-card-head"><span class="co-num">1</span><div><h2>Your details</h2><p>Tap or scan your school ID, or just type your name.</p></div></header>
            @php($idRequired = \App\Support\SchoolId::required())
            <div class="co-grid">
                <label class="co-field">
                    <span class="co-label">School ID @unless($idRequired)<em>optional</em>@endunless</span>
                    <input id="school-id" name="school_id" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="40" value="{{ old('school_id') }}" placeholder="Tap or scan your ID, or type the number" @required($idRequired) @class(['has-error' => $errors->has('school_id')])>
                    @error('school_id')<small class="co-error">{{ $message }}</small>@enderror
                </label>
                <label class="co-field">
                    <span class="co-label">Last name <em>with your ID</em></span>
                    <input id="school-last" name="school_last_name" autocomplete="family-name" maxlength="80" value="{{ old('school_last_name') }}" placeholder="e.g. Dela Cruz">
                </label>
            </div>
            <p class="co-note" style="margin:2px 0 14px">With your school ID we fill in your name for you. The last name keeps other people from using your ID.</p>
            <button type="button" id="scan-id" class="co-edit" hidden style="margin:0 0 14px">Scan my ID with the camera</button>
            <video id="scan-video" hidden playsinline muted style="width:100%;max-height:240px;margin:0 0 14px;border-radius:12px;background:#000"></video>
            <div class="co-grid">
                <label class="co-field">
                    <span class="co-label">Full name</span>
                    <input name="customer_name" autocomplete="name" value="{{ old('customer_name', session('shop.profile.customer_name', '')) }}" maxlength="120" placeholder="e.g. Juan Dela Cruz" id="co-name" required @class(['has-error' => $errors->has('customer_name')])>
                    @error('customer_name')<small class="co-error">{{ $message }}</small>@enderror
                </label>
                <label class="co-field">
                    <span class="co-label">Phone number</span>
                    <input name="phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('phone', session('shop.profile.phone', '')) }}" maxlength="25" placeholder="e.g. 0917 123 4567" required @class(['has-error' => $errors->has('phone')])>
                    @error('phone')<small class="co-error">{{ $message }}</small>@enderror
                </label>
            </div>
        </section>

        {{-- 2 · Pickup or delivery --}}
        <section class="co-card">
            <header class="co-card-head"><span class="co-num">2</span><div><h2>Pickup or delivery</h2><p>Collect it at the canteen, or have a student runner bring it to you.</p></div></header>
            <fieldset class="pay-fieldset">
                <legend class="sr-only">Pickup or delivery</legend>
                <div class="pay-methods">
                    <label class="pay-method">
                        <input type="radio" name="fulfillment" value="pickup" @checked($mode !== 'delivery')>
                        <span class="pay-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 9l1.5-5h13L20 9"/><path d="M5 9v11h14V9"/><path d="M9.5 20v-5h5v5"/></svg></span>
                        <span class="pay-method-body"><strong>Pickup</strong><small>Free · pick it up at {{ $pickupLabel }}</small></span>
                    </label>
                    @if($canDeliver)
                        <label class="pay-method">
                            <input type="radio" name="fulfillment" value="delivery" @checked($mode === 'delivery')>
                            <span class="pay-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0113 0c0 5.4-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.4"/></svg></span>
                            <span class="pay-method-body"><strong>Delivery</strong><small>A student runner brings it to your room · pay first with GCash or Maya</small></span>
                        </label>
                    @endif
                </div>
                @error('fulfillment')<small class="co-error">{{ $message }}</small>@enderror
            </fieldset>
            @unless($canDeliver)
                <p class="co-note" style="margin-top:12px">Delivery is not available right now. You can still order for pickup.</p>
            @endunless

            @if($canDeliver)
                <div id="delivery-fields" style="margin-top:14px;{{ $mode === 'delivery' ? '' : 'display:none' }}">
                    <div class="co-grid">
                        <label class="co-field">
                            <span class="co-label">Building</span>
                            <select name="zone_id" id="zone" @class(['has-error' => $errors->has('zone_id')])>
                                @foreach($deliveryZones as $z)<option value="{{ $z->id }}" @selected($chosenZone && $z->id === $chosenZone->id)>{{ $z->name }}</option>@endforeach
                            </select>
                            @error('zone_id')<small class="co-error">{{ $message }}</small>@enderror
                        </label>
                        <label class="co-field">
                            <span class="co-label">Floor</span>
                            <select name="floor" id="floor" @class(['has-error' => $errors->has('floor')])>
                                @if($chosenZone)
                                    @foreach($chosenZone->feeTable() as $f => $fee)<option value="{{ $f }}" @selected($f === $floorNow)>Floor {{ $f }} · ₱{{ number_format($fee, 2) }}</option>@endforeach
                                @endif
                            </select>
                            @error('floor')<small class="co-error">{{ $message }}</small>@enderror
                        </label>
                    </div>
                    <label class="co-field">
                        <span class="co-label">Room number</span>
                        <input name="room" maxlength="40" value="{{ old('room', session('shop.profile.room', '')) }}" placeholder="e.g. 804" @class(['has-error' => $errors->has('room')])>
                        @error('room')<small class="co-error">{{ $message }}</small>@enderror
                    </label>
                    <p class="co-note">The delivery fee depends on how far the runner has to go, and is shown in the summary.</p>
                </div>
            @endif

            <label class="co-field" style="margin-top:14px">
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
                <p class="co-note" id="pay-note" style="margin-top:10px;{{ $mode === 'delivery' ? '' : 'display:none' }}">Delivery orders are paid in advance with GCash or Maya, so the runner only brings orders that are already paid.</p>
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

        <div class="co-item" id="fee-row" style="display:none"><span class="co-qty"></span><span class="co-item-name">Delivery fee</span><strong id="fee-amount">₱0.00</strong></div>
        <div class="total"><span>Total</span><strong id="grand-total">₱{{ number_format($orderTotal, 2) }}</strong></div>

        <button type="submit" form="checkout-form" class="button co-submit">Place order</button>
        <p class="co-note">Delivery fees are worked out by the canteen when you place the order. You can follow your order under “My orders”.</p>
        @if(\App\Support\Strikes::enabled())
            <p class="co-note">Please pay within {{ config('restaurant.unpaid_expiry_minutes') }} minutes. An unpaid order is cancelled and counts as a missed order. After {{ \App\Support\Strikes::max() }} missed orders, ordering is paused for {{ \App\Support\Strikes::pauseDays() }} days.</p>
        @endif
        <a class="co-edit" href="{{ route('shop.cart') }}">← Edit cart</a>
    </aside>
</div>
@endsection
@section('scripts')<script>
document.querySelector('#checkout-form').addEventListener('submit',function(){document.querySelectorAll('button[form="checkout-form"]').forEach(function(b){b.disabled=true;b.textContent='Placing your order…';});});
(function () {
    var id = document.getElementById('school-id'), last = document.getElementById('school-last'), name = document.getElementById('co-name');
    if (!id || !name) { return; }
    // The name is only needed when no school ID is given (with an ID it comes from the school list).
    function sync() { name.required = id.value.trim() === ''; }
    id.addEventListener('input', sync); sync();
    // An ID card reader "types" the number and presses Enter: move on to the last name instead of sending the form.
    id.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); if (last) { last.focus(); } } });
    // Phones: read the barcode / QR code on the ID with the camera, where the browser supports it.
    var btn = document.getElementById('scan-id'), video = document.getElementById('scan-video');
    if (!btn || !('BarcodeDetector' in window) || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { return; }
    btn.hidden = false;
    btn.addEventListener('click', function () {
        var stream, timer, detector = new BarcodeDetector();
        function stop() { clearInterval(timer); if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); } video.hidden = true; btn.disabled = false; }
        btn.disabled = true;
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (s) {
            stream = s; video.srcObject = s; video.hidden = false; video.play();
            timer = setInterval(function () {
                detector.detect(video).then(function (codes) {
                    if (codes.length) { id.value = codes[0].rawValue; sync(); stop(); if (last) { last.focus(); } }
                }).catch(function () {});
            }, 300);
        }).catch(function () { stop(); });
    });
})();
(function () {
    var form = document.getElementById('checkout-form');
    var box = document.getElementById('delivery-fields');
    if (!box) { return; }
    // The prices come from the server. They are only shown here; the server works the fee out again when the order is placed.
    var data = @json($feeData), food = {{ $orderTotal }};
    var zone = document.getElementById('zone'), floor = document.getElementById('floor'), room = form.querySelector('[name=room]');
    var feeRow = document.getElementById('fee-row'), feeAmt = document.getElementById('fee-amount'), grand = document.getElementById('grand-total'), payNote = document.getElementById('pay-note');
    var cash = form.querySelector('input[name=payment_choice][value=cash]');
    function money(n) { return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function delivering() { var c = form.querySelector('input[name=fulfillment]:checked'); return !!c && c.value === 'delivery'; }
    function fillFloors(keep) {
        var z = data[zone.value]; floor.innerHTML = ''; if (!z) { return; }
        for (var f = 1; f <= z.floors; f++) {
            var o = document.createElement('option'); o.value = f; o.textContent = 'Floor ' + f + ' · ' + money(z.fees[f]);
            if (String(keep) === String(f)) { o.selected = true; } floor.appendChild(o);
        }
    }
    function update() {
        var d = delivering();
        box.style.display = d ? '' : 'none';
        [zone, floor, room].forEach(function (el) { if (el) { el.disabled = !d; } });   // disabled fields are not sent for pickup
        if (cash) { cash.disabled = d; }
        payNote.style.display = d ? '' : 'none';
        if (d && cash && cash.checked) { var alt = form.querySelector('input[name=payment_choice]:not([value=cash])'); if (alt) { alt.checked = true; } }
        var z = data[zone.value], fee = (d && z && z.fees[floor.value]) ? z.fees[floor.value] : 0;
        feeRow.style.display = d ? '' : 'none'; feeAmt.textContent = money(fee); grand.textContent = money(food + fee);
    }
    form.querySelectorAll('input[name=fulfillment]').forEach(function (r) { r.addEventListener('change', update); });
    zone.addEventListener('change', function () { fillFloors(1); update(); });
    floor.addEventListener('change', update);
    update();
})();
</script>@endsection
