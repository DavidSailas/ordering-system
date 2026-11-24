<?php

use App\Enums\OrderStatus;
use App\Models\BlockedPhone;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Support\CampusPass;
use App\Support\PhoneKey;
use Illuminate\Support\Str;

/** Places a guest order the way a customer's phone does (cart cookie + checkout page token). */
function guestOrder(array $fields = [], float $price = 100, bool $withPass = false, ?int $shownAgo = null): Illuminate\Testing\TestResponse
{
    $item = MenuItem::factory()->create(['price' => $price]);
    $cartToken = 'cart-'.Str::random(8);
    CartItem::create(['guest_token' => $cartToken, 'menu_item_id' => $item->id, 'quantity' => 1]);

    $session = ['shop.checkout_token' => 'tok'];
    if ($shownAgo !== null) {
        $session['shop.checkout_shown_at'] = now()->subSeconds($shownAgo)->timestamp;
    }

    $test = test()->withCookie('shop_cart', $cartToken)->withSession($session);

    if ($withPass) {
        $test = $test->withCookie(CampusPass::COOKIE, CampusPass::version().'.'.now()->addDay()->timestamp);
    }

    return $test->post('/checkout', array_merge([
        'customer_name' => 'Pat Reyes', 'phone' => '0917 123 4567', 'room' => '204', 'payment_choice' => 'cash', 'checkout_token' => 'tok',
    ], $fields));
}

/* ---------- campus pass ---------- */

test('the campus pass is off by default, so anyone can order', function () {
    expect(CampusPass::enabled())->toBeFalse();

    guestOrder()->assertRedirect(route('shop.orders'));
    expect(Order::count())->toBe(1);
});

test('with the pass on, visitors can browse and fill a cart but not check out', function () {
    CampusPass::setEnabled(true);
    $food = MenuItem::factory()->create();

    $this->get('/')->assertOk()->assertSee($food->name);
    $this->post('/cart', ['menu_item_id' => $food->id, 'quantity' => 1, 'action' => 'add'])->assertSessionHasNoErrors();
    $this->get('/checkout')->assertRedirect(route('pass.show'));
    guestOrder()->assertRedirect(route('pass.show'));

    expect(Order::count())->toBe(0);
    $this->get(route('pass.show'))->assertOk()->assertSee('Scan the QR code to order');
});

test('scanning the QR code gives the phone a pass, and then it can order', function () {
    CampusPass::setEnabled(true);

    $response = $this->get('/pass/'.CampusPass::secret());
    $response->assertRedirect(route('shop.canteens'))->assertCookie(CampusPass::COOKIE);

    guestOrder(withPass: true)->assertRedirect(route('shop.orders'));
    expect(Order::count())->toBe(1);
});

test('an old or wrong QR code is turned away without a pass', function () {
    CampusPass::setEnabled(true);

    $this->get('/pass/not-the-secret')->assertRedirect(route('pass.show'))->assertCookieMissing(CampusPass::COOKIE);
    $this->get(route('pass.show'))->assertOk();
});

test('the short code works for people who cannot scan, even typed in lower case with spaces', function () {
    CampusPass::setEnabled(true);
    $code = strtolower(substr(CampusPass::code(), 0, 3).' '.substr(CampusPass::code(), 3));

    $this->post(route('pass.store'), ['code' => 'WRONG1'])->assertSessionHasErrors('code');
    $this->post(route('pass.store'), ['code' => $code])->assertRedirect(route('shop.canteens'))->assertCookie(CampusPass::COOKIE);
});

test('after scanning, the customer lands back where they were going', function () {
    CampusPass::setEnabled(true);
    $this->get('/checkout')->assertRedirect(route('pass.show'));

    $this->post(route('pass.store'), ['code' => CampusPass::code()])->assertRedirect(route('shop.checkout'));
});

test('signed-in staff never need the pass', function () {
    CampusPass::setEnabled(true);
    $item = MenuItem::factory()->create();
    CartItem::create(['user_id' => ($staff = User::factory()->cashier()->create())->id, 'menu_item_id' => $item->id, 'quantity' => 1]);

    $this->actingAs($staff)->get('/checkout')->assertOk();
});

test('creating a new QR code cancels every pass handed out before', function () {
    CampusPass::setEnabled(true);
    $oldSecret = CampusPass::secret();
    $oldCode = CampusPass::code();

    guestOrder(withPass: true)->assertRedirect(route('shop.orders'));

    $oldPass = CampusPass::version().'.'.now()->addDay()->timestamp;
    CampusPass::regenerate();

    expect(CampusPass::secret())->not->toBe($oldSecret)->and(CampusPass::code())->not->toBe($oldCode);
    $this->withCookie(CampusPass::COOKIE, $oldPass)->get('/checkout')->assertRedirect(route('pass.show'));
    $this->get('/pass/'.$oldSecret)->assertRedirect(route('pass.show'))->assertCookieMissing(CampusPass::COOKIE);
    $this->post(route('pass.store'), ['code' => $oldCode])->assertSessionHasErrors('code');
});

test('a pass stops working after the number of days that is set', function () {
    CampusPass::setEnabled(true);
    $this->travel(31)->days();

    $expired = CampusPass::version().'.'.now()->subDay()->timestamp;
    $this->withCookie(CampusPass::COOKIE, $expired)->get('/checkout')->assertRedirect(route('pass.show'));
});

test('a made-up pass cookie does not work', function () {
    CampusPass::setEnabled(true);

    $this->withCookie(CampusPass::COOKIE, 'yes')->get('/checkout')->assertRedirect(route('pass.show'));
    $this->withCookie(CampusPass::COOKIE, '999.'.now()->addYear()->timestamp)->get('/checkout')->assertRedirect(route('pass.show'));
});

/* ---------- the administrator's page ---------- */

test('only the overall administrator manages order security', function () {
    $this->actingAs(User::factory()->owner()->create())->get(route('admin.order-security.index'))->assertForbidden();
    $this->actingAs(User::factory()->cashier()->create())->get(route('admin.order-security.index'))->assertForbidden();
    $this->actingAs(User::factory()->owner()->create())->get(route('admin.order-security.poster'))->assertForbidden();
    $this->actingAs(User::factory()->owner()->create())->patch(route('admin.order-security.pass'), ['enabled' => 1])->assertForbidden();

    $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.order-security.index'))
        ->assertOk()->assertSee(CampusPass::code())->assertSee(CampusPass::link());
});

test('the administrator can switch the pass on and off, print the poster and create a new code', function () {
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)->patch(route('admin.order-security.pass'), ['enabled' => 1])->assertSessionHas('status');
    expect(CampusPass::enabled())->toBeTrue();

    $this->actingAs($super)->get(route('admin.order-security.poster'))->assertOk()->assertSee('Scan to order')->assertSee(CampusPass::code());

    $before = CampusPass::secret();
    $this->actingAs($super)->post(route('admin.order-security.regenerate'))->assertSessionHas('status');
    expect(CampusPass::secret())->not->toBe($before);

    $this->actingAs($super)->patch(route('admin.order-security.pass'), ['enabled' => 0]);
    expect(CampusPass::enabled())->toBeFalse();
});

/* ---------- limits ---------- */

test('one phone number cannot have more than two unpaid orders open, however it is typed', function () {
    guestOrder(['phone' => '0917 123 4567'])->assertSessionHasNoErrors();
    guestOrder(['phone' => '+63 917-123-4567'])->assertSessionHasNoErrors();
    guestOrder(['phone' => '09171234567'])->assertSessionHasErrors('phone');

    expect(Order::count())->toBe(2);

    // a different number is not affected, and finishing an order frees a place
    guestOrder(['phone' => '0928 555 0001'])->assertSessionHasNoErrors();
    Order::query()->where('phone_key', '9171234567')->first()->forceFill(['status' => OrderStatus::Completed])->save();
    guestOrder(['phone' => '09171234567'])->assertSessionHasNoErrors();
    expect(Order::count())->toBe(4);
});

test('one connection cannot flood the canteen even with new phone numbers', function () {
    config(['restaurant.order_limits.max_open_unpaid_per_ip' => 3]);

    foreach (['0917 000 0001', '0917 000 0002', '0917 000 0003'] as $phone) {
        guestOrder(['phone' => $phone])->assertSessionHasNoErrors();
    }
    guestOrder(['phone' => '0917 000 0004'])->assertSessionHasErrors('cart');

    expect(Order::count())->toBe(3);
});

test('cash orders have a ceiling, bigger ones must be paid by GCash or Maya', function () {
    \App\Models\Canteen::default()->update(['gcash_number' => '09171234567']);

    guestOrder(price: 800)->assertSessionHasErrors('payment_choice');
    expect(Order::count())->toBe(0);

    guestOrder(['payment_choice' => 'gcash'], price: 800)->assertSessionHasNoErrors();
    expect(Order::count())->toBe(1);
});

test('the limits can be switched off', function () {
    config(['restaurant.order_limits.max_open_unpaid' => 0, 'restaurant.order_limits.max_open_unpaid_per_ip' => 0, 'restaurant.order_limits.max_cash_total' => 0]);

    foreach (range(1, 3) as $i) {
        guestOrder(price: 900)->assertSessionHasNoErrors();
    }

    expect(Order::count())->toBe(3);
});

test('an order remembers the phone key and connection it came from', function () {
    guestOrder(['phone' => '+63 917 123 4567'])->assertSessionHasNoErrors();

    $order = Order::sole();
    expect($order->phone_key)->toBe('9171234567')->and($order->ip_address)->toBe('127.0.0.1');
    expect(PhoneKey::from('0917-123-4567'))->toBe('9171234567')->and(PhoneKey::from('+639171234567'))->toBe('9171234567')->and(PhoneKey::from('12345'))->toBe('12345');
});

/* ---------- bot traps and delivery details ---------- */

test('the hidden trap field and instant submissions are rejected', function () {
    guestOrder(['website' => 'http://spam.example'])->assertSessionHasErrors('cart');
    guestOrder(shownAgo: 1)->assertSessionHasErrors('cart');
    expect(Order::count())->toBe(0);

    guestOrder(shownAgo: 20)->assertSessionHasNoErrors();
    expect(Order::count())->toBe(1);
});

test('a pickup order needs no room, but a delivery order needs a building, a floor and a room', function () {
    guestOrder(['room' => ''])->assertSessionHasNoErrors();   // pickup is the default

    // Delivery is covered in DeliveryFeeTest; here we only check that it cannot skip its location.
    guestOrder(['fulfillment' => 'delivery', 'payment_choice' => 'gcash', 'room' => ''])->assertSessionHasErrors(['zone_id', 'floor', 'room']);
});

test('the checkout page contains the hidden trap field', function () {
    $item = MenuItem::factory()->create();
    CartItem::create(['guest_token' => 'abc', 'menu_item_id' => $item->id, 'quantity' => 1]);

    $this->withCookie('shop_cart', 'abc')->get('/checkout')->assertOk()->assertSee('name="website"', false);
});

/* ---------- blocked numbers ---------- */

test('a blocked phone number cannot order, however it is typed', function () {
    BlockedPhone::block('0917 123 4567', 'Prank orders');

    guestOrder(['phone' => '+63 917 123 4567'])->assertSessionHasErrors('phone');
    expect(Order::count())->toBe(0);

    guestOrder(['phone' => '0917 999 9999'])->assertSessionHasNoErrors();
});

test('a cashier can report a fake order: it is cancelled and the number is blocked', function () {
    $order = Order::factory()->create(['phone' => '0917 111 2222', 'status' => OrderStatus::InProgress, 'paid_at' => null]);
    $cashier = User::factory()->cashier()->create(['canteen_id' => $order->canteen_id]);

    $this->actingAs($cashier)->post(route('orders.report-fake', $order))->assertSessionHas('status');

    expect($order->fresh()->status)->toBe(OrderStatus::Canceled)->and($order->fresh()->canceled_at)->not->toBeNull();
    expect(BlockedPhone::isBlocked('+63 917 111 2222'))->toBeTrue();
    expect(BlockedPhone::query()->sole()->blocked_by)->toBe($cashier->id);
});

test('a paid order cannot be reported as fake, and other canteens cannot report it', function () {
    $paid = Order::factory()->create(['phone' => '0917 333 4444', 'status' => OrderStatus::InProgress, 'paid_at' => now()]);
    $cashier = User::factory()->cashier()->create(['canteen_id' => $paid->canteen_id]);

    $this->actingAs($cashier)->post(route('orders.report-fake', $paid))->assertSessionHasErrors('order');
    expect($paid->fresh()->status)->toBe(OrderStatus::InProgress)->and(BlockedPhone::count())->toBe(0);

    $other = \App\Models\Canteen::factory()->create();
    $outsider = User::factory()->cashier()->create(['canteen_id' => $other->id]);
    $unpaid = Order::factory()->create(['phone' => '0917 555 6666', 'paid_at' => null, 'status' => OrderStatus::InProgress]);

    // another canteen's order is simply not visible to them
    expect($this->actingAs($outsider)->post(route('orders.report-fake', $unpaid))->status())->toBeIn([403, 404]);
    expect(BlockedPhone::count())->toBe(0);
});

test('customers cannot report orders', function () {
    $order = Order::factory()->create(['phone' => '0917 777 8888', 'paid_at' => null]);

    expect($this->actingAs(User::factory()->customer()->create())->post(route('orders.report-fake', $order))->status())->toBeIn([403, 404]);
    expect(BlockedPhone::count())->toBe(0);
    expect($this->flushSession()->post(route('orders.report-fake', $order))->status())->toBeIn([302, 403, 404]);
});

test('the owner can block a number from the order page, and the administrator can unblock it', function () {
    $order = Order::factory()->create(['phone' => '0917 222 3333']);
    $owner = User::factory()->owner()->create(['canteen_id' => $order->canteen_id]);

    $this->actingAs($owner)->get(route('admin.orders.show', $order))->assertOk()->assertSee('Block this number');
    $this->actingAs($owner)->post(route('orders.block-phone', $order))->assertSessionHas('status');
    expect(BlockedPhone::isBlocked('09172223333'))->toBeTrue();
    $this->actingAs($owner)->get(route('admin.orders.show', $order))->assertSee('This number is blocked');

    $this->actingAs($owner)->delete(route('admin.order-security.unblock', BlockedPhone::sole()))->assertForbidden();

    $super = User::factory()->superAdmin()->create();
    $this->actingAs($super)->get(route('admin.order-security.index'))->assertSee('0917 222 3333');
    $this->actingAs($super)->delete(route('admin.order-security.unblock', BlockedPhone::sole()))->assertSessionHas('status');
    expect(BlockedPhone::isBlocked('0917 222 3333'))->toBeFalse();
});

test('the administrator can block a number by hand', function () {
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)->post(route('admin.order-security.block'), ['phone' => '0917 000 1111', 'reason' => 'Prank'])->assertSessionHasNoErrors();
    $this->actingAs($super)->post(route('admin.order-security.block'), ['phone' => 'abc'])->assertSessionHasErrors('phone');

    expect(BlockedPhone::isBlocked('09170001111'))->toBeTrue();
});
