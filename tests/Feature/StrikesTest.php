<?php

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Canteen;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderStrike;
use App\Models\PhoneSuspension;
use App\Models\User;
use App\Support\Strikes;
use App\Support\UnpaidOrders;
use Illuminate\Support\Str;

/*
 * Automatic strikes: an order nobody pays for is a missed order. After three, the phone number is
 * paused for three days, and the pause lifts by itself. No logins and no work for the staff.
 */

const STRIKE_PHONE = '0917 111 2222';

/** An unpaid order that is already late, so the next expiry run cancels it. */
function lateOrder(array $attributes = []): Order
{
    return Order::factory()->create(array_merge([
        'status' => OrderStatus::AwaitingPayment, 'paid_at' => null, 'total' => 100,
        'phone' => STRIKE_PHONE, 'phone_key' => '9171112222', 'created_at' => now()->subMinutes(20),
    ], $attributes));
}

/** A guest tries to check out with the given phone number. */
function strikeCheckout(string $phone = STRIKE_PHONE): Illuminate\Testing\TestResponse
{
    $item = MenuItem::factory()->create(['price' => 100]);
    $token = 'cart-'.Str::random(8);
    CartItem::create(['guest_token' => $token, 'menu_item_id' => $item->id, 'quantity' => 1]);

    return test()->withCookie('shop_cart', $token)
        ->withSession(['shop.checkout_token' => 'tok', 'shop.canteen_id' => Canteen::default()->id])
        ->post('/checkout', [
            'customer_name' => 'Pat Reyes', 'phone' => $phone, 'fulfillment' => 'pickup',
            'payment_choice' => 'cash', 'checkout_token' => 'tok',
        ]);
}

/* ---------- counting missed orders ---------- */

test('an order that is not paid in time is a missed order for that phone number', function () {
    $order = lateOrder();

    UnpaidOrders::expire();

    $strike = OrderStrike::sole();
    expect($strike->phone_key)->toBe('9171112222')
        ->and($strike->order_id)->toEqual($order->id)
        ->and(Strikes::current('9171112222'))->toBe(1)
        ->and(PhoneSuspension::count())->toBe(0);
});

test('an order the customer really tried to pay for is never a missed order', function () {
    lateOrder(['customer_payment_ref' => 'GC123456', 'created_at' => now()->subMinutes(70)]);

    expect(UnpaidOrders::expire())->toBe(1);   // it is cancelled…

    expect(OrderStrike::count())->toBe(0);     // …but the customer is not penalised
});

test('different ways of typing the same number count together', function () {
    lateOrder(['phone' => '0917 111 2222', 'phone_key' => App\Support\PhoneKey::from('0917 111 2222')]);
    lateOrder(['phone' => '+63 917-111-2222', 'phone_key' => App\Support\PhoneKey::from('+63 917-111-2222')]);

    UnpaidOrders::expire();

    expect(Strikes::current('9171112222'))->toBe(2);
});

test('strikes can be switched off', function () {
    config(['restaurant.strikes.max' => 0]);
    lateOrder();

    UnpaidOrders::expire();

    expect(OrderStrike::count())->toBe(0)->and(Strikes::enabled())->toBeFalse();
});

/* ---------- the automatic pause ---------- */

test('the third missed order pauses the phone number for three days', function () {
    lateOrder();
    lateOrder();
    expect(UnpaidOrders::expire())->toBe(2)->and(PhoneSuspension::count())->toBe(0);

    lateOrder();
    UnpaidOrders::expire();

    $pause = PhoneSuspension::sole();
    expect($pause->phone_key)->toBe('9171112222')
        ->and($pause->strikes)->toBe(3)
        ->and($pause->ends_at->between(now()->addDays(3)->subMinute(), now()->addDays(3)->addMinute()))->toBeTrue()
        ->and(Strikes::suspension(STRIKE_PHONE))->not->toBeNull()
        ->and(Strikes::suspension('+63 917 111 2222'))->not->toBeNull();   // typed another way, same person
});

test('a paused phone number cannot place an order, and is told when it can order again', function () {
    PhoneSuspension::create(['phone_key' => '9171112222', 'phone' => STRIKE_PHONE, 'reason' => 'test', 'strikes' => 3, 'starts_at' => now(), 'ends_at' => now()->addDays(2)]);

    strikeCheckout()->assertSessionHasErrors('phone');

    expect(Order::withoutGlobalScopes()->count())->toBe(0)
        ->and(session('errors')->first('phone'))->toContain('paused')->toContain(now()->addDays(2)->format('M j'));

    strikeCheckout('0918 999 8888')->assertSessionHasNoErrors();   // other people are not affected
});

test('the pause lifts by itself and the count starts again from zero', function () {
    lateOrder(); lateOrder(); lateOrder();
    UnpaidOrders::expire();
    expect(Strikes::suspension(STRIKE_PHONE))->not->toBeNull();

    $this->travelTo(now()->addDays(4));

    expect(Strikes::suspension(STRIKE_PHONE))->toBeNull()
        ->and(Strikes::current('9171112222'))->toBe(0);

    strikeCheckout()->assertSessionHasNoErrors();   // allowed again

    // one more miss is only strike 1 of 3, not an instant second pause
    lateOrder(['created_at' => now()->subMinutes(20)]);
    UnpaidOrders::expire();

    expect(Strikes::current('9171112222'))->toBe(1)->and(Strikes::suspension(STRIKE_PHONE))->toBeNull();
});

test('old missed orders fade away', function () {
    foreach ([1, 2] as $_) {
        OrderStrike::forceCreate(['phone_key' => '9171112222', 'phone' => STRIKE_PHONE, 'reason' => 'old', 'created_at' => now()->subDays(31), 'updated_at' => now()->subDays(31)]);
    }

    lateOrder();
    UnpaidOrders::expire();

    expect(Strikes::current('9171112222'))->toBe(1)->and(PhoneSuspension::count())->toBe(0);
});

test('the numbers can be changed in the settings', function () {
    config(['restaurant.strikes.max' => 2, 'restaurant.strikes.pause_days' => 7]);

    lateOrder(); lateOrder();
    UnpaidOrders::expire();

    $pause = PhoneSuspension::sole();
    expect($pause->ends_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()))->toBeTrue();
});

/* ---------- what staff and customers see ---------- */

test('the cashier sees when a waiting order comes from a number that missed orders before', function () {
    OrderStrike::forceCreate(['phone_key' => '9171112222', 'phone' => STRIKE_PHONE, 'reason' => 'x']);
    OrderStrike::forceCreate(['phone_key' => '9171112222', 'phone' => STRIKE_PHONE, 'reason' => 'y']);
    lateOrder(['created_at' => now()->subMinutes(2)]);   // still inside its payment time

    $cashier = User::factory()->cashier()->forCanteen(Canteen::default())->create();

    $this->actingAs($cashier)->get(route('dashboard'))->assertOk()->assertSee('2 missed orders');
});

test('the checkout page explains the rule before the customer orders', function () {
    $item = MenuItem::factory()->create();
    $token = 'cart-'.Str::random(8);
    CartItem::create(['guest_token' => $token, 'menu_item_id' => $item->id, 'quantity' => 1]);

    $this->withCookie('shop_cart', $token)->withSession(['shop.canteen_id' => Canteen::default()->id])
        ->get('/checkout')->assertOk()->assertSee('counts as a missed order');
});

/* ---------- the administrator ---------- */

test('the administrator sees paused numbers and can lift a pause early', function () {
    $pause = PhoneSuspension::create(['phone_key' => '9171112222', 'phone' => STRIKE_PHONE, 'reason' => '3 orders were not paid in time', 'strikes' => 3, 'starts_at' => now(), 'ends_at' => now()->addDays(3)]);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get(route('admin.order-security.index'))
        ->assertOk()->assertSee(STRIKE_PHONE)->assertSee('Lift pause');

    $this->delete(route('admin.order-security.lift', $pause))->assertRedirect();

    expect($pause->fresh()->lifted_at)->not->toBeNull()
        ->and($pause->fresh()->lifted_by)->toEqual($admin->id)
        ->and(Strikes::suspension(STRIKE_PHONE))->toBeNull()
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'strike.lifted')->count())->toBe(1);
});

test('only the overall administrator can lift a pause', function () {
    $pause = PhoneSuspension::create(['phone_key' => '9171112222', 'phone' => STRIKE_PHONE, 'reason' => 'x', 'strikes' => 3, 'starts_at' => now(), 'ends_at' => now()->addDays(3)]);

    foreach ([User::factory()->owner()->create(), User::factory()->cashier()->create()] as $user) {
        $this->actingAs($user)->delete(route('admin.order-security.lift', $pause))->assertForbidden();
    }

    expect($pause->fresh()->lifted_at)->toBeNull();
});
