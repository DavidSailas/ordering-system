<?php

use App\Enums\OrderStatus;
use App\Models\Canteen;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Support\UnpaidOrders;
use Illuminate\Support\Str;

/*
 * "Pay first, then we cook": a new order waits for payment, the kitchen cannot start it, and it cancels
 * itself when nobody pays, so a prank order never costs the canteen any food.
 */

beforeEach(function () {
    $this->canteen = Canteen::default();
    $this->canteen->update(['gcash_number' => '09171234567']);
    $this->cashier = User::factory()->cashier()->forCanteen($this->canteen)->create();
});

/** A guest checks out one dish, paying the given way. */
function payFirstCheckout(string $payment = 'cash'): Illuminate\Testing\TestResponse
{
    $item = MenuItem::factory()->create(['price' => 100]);
    $token = 'cart-'.Str::random(8);
    CartItem::create(['guest_token' => $token, 'menu_item_id' => $item->id, 'quantity' => 1]);

    return test()->withCookie('shop_cart', $token)
        ->withSession(['shop.checkout_token' => 'tok', 'shop.canteen_id' => Canteen::default()->id])
        ->post('/checkout', [
            'customer_name' => 'Pat Reyes', 'phone' => '0917 123 4567', 'fulfillment' => 'pickup',
            'payment_choice' => $payment, 'checkout_token' => 'tok',
        ]);
}

function waitingOrder(array $attributes = []): Order
{
    return Order::factory()->create(array_merge(['status' => OrderStatus::AwaitingPayment, 'paid_at' => null, 'total' => 150], $attributes));
}

/* ---------- new orders wait for payment ---------- */

test('a new order waits for payment instead of going to the kitchen', function () {
    payFirstCheckout('cash')->assertSessionHasNoErrors();
    expect(Order::withoutGlobalScopes()->latest('id')->first()->status)->toBe(OrderStatus::AwaitingPayment);

    payFirstCheckout('gcash')->assertSessionHasNoErrors();
    expect(Order::withoutGlobalScopes()->count())->toBe(2)
        ->and(Order::withoutGlobalScopes()->latest('id')->first()->status)->toBe(OrderStatus::AwaitingPayment);
});

test('the cashier board keeps waiting orders apart from the kitchen', function () {
    waitingOrder(['customer_name' => 'Waiting Wendy']);
    Order::factory()->create(['customer_name' => 'Cooking Carl', 'status' => OrderStatus::InProgress, 'paid_at' => now()]);

    $response = $this->actingAs($this->cashier)->get(route('dashboard'))->assertOk()->assertSee('Waiting for payment');

    expect($response->viewData('awaiting')->pluck('customer_name')->all())->toBe(['Waiting Wendy'])
        ->and($response->viewData('preparing')->pluck('customer_name')->all())->toBe(['Cooking Carl']);
});

test('the kitchen cannot start an order that is not paid', function () {
    $order = waitingOrder();

    $this->actingAs($this->cashier)
        ->patch(route('orders.status', $order), ['status' => OrderStatus::ReadyToServe->value])
        ->assertSessionHasErrors('status');

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment);
});

test('confirming the payment sends the order to the kitchen', function () {
    $order = waitingOrder();

    $this->actingAs($this->cashier)
        ->post(route('orders.payment.store', $order), ['payment_method' => 'cash', 'cash_received' => 200])
        ->assertSessionHasNoErrors();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::InProgress)->and($order->paid_at)->not->toBeNull();
});

test('an online payment also sends the order to the kitchen once the cashier verifies it', function () {
    $order = waitingOrder(['payment_choice' => 'gcash', 'customer_payment_ref' => 'GC123456']);

    $this->actingAs($this->cashier)
        ->post(route('orders.payment.store', $order), ['payment_method' => 'gcash', 'payment_reference' => 'GC123456'])
        ->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::InProgress);
});

test('waiting orders are not counted as sales until they are paid', function () {
    waitingOrder();
    Order::factory()->create(['status' => OrderStatus::InProgress]);
    Order::factory()->create(['status' => OrderStatus::Canceled]);

    expect(Order::withoutGlobalScopes()->countedAsSales()->count())->toBe(1);
});

/* ---------- unpaid orders cancel themselves ---------- */

test('an order that is not paid in time is cancelled', function () {
    $late = waitingOrder(['created_at' => now()->subMinutes(20)]);
    $fresh = waitingOrder(['created_at' => now()->subMinutes(5)]);

    expect(UnpaidOrders::expire())->toBe(1);

    expect($late->fresh()->status)->toBe(OrderStatus::Canceled)
        ->and($late->fresh()->canceled_at)->not->toBeNull()
        ->and($fresh->fresh()->status)->toBe(OrderStatus::AwaitingPayment);
});

test('an order whose online payment was already sent gets longer to be checked', function () {
    $checking = waitingOrder(['customer_payment_ref' => 'GC123456', 'created_at' => now()->subMinutes(20)]);
    $forgotten = waitingOrder(['customer_payment_ref' => 'GC999999', 'created_at' => now()->subMinutes(70)]);

    UnpaidOrders::expire();

    expect($checking->fresh()->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($forgotten->fresh()->status)->toBe(OrderStatus::Canceled);
});

test('paid orders and orders already in the kitchen are never cancelled by the timer', function () {
    $paid = waitingOrder(['paid_at' => now(), 'created_at' => now()->subHours(2)]);
    $cooking = Order::factory()->create(['status' => OrderStatus::InProgress, 'paid_at' => null, 'created_at' => now()->subHours(2)]);

    expect(UnpaidOrders::expire())->toBe(0);

    expect($paid->fresh()->status)->toBe(OrderStatus::AwaitingPayment)->and($cooking->fresh()->status)->toBe(OrderStatus::InProgress);
});

test('the scheduled command cancels late orders', function () {
    $late = waitingOrder(['created_at' => now()->subMinutes(30)]);

    $this->artisan('orders:expire-unpaid')->assertSuccessful();

    expect($late->fresh()->status)->toBe(OrderStatus::Canceled);
});

test('opening the cashier board also cancels late orders, so it works without a cron job', function () {
    $late = waitingOrder(['created_at' => now()->subMinutes(30)]);

    $this->actingAs($this->cashier)->get(route('dashboard'))->assertOk();

    expect($late->fresh()->status)->toBe(OrderStatus::Canceled);
});

test('the waiting time can be changed in the settings', function () {
    config(['restaurant.unpaid_expiry_minutes' => 5]);
    $order = waitingOrder(['created_at' => now()->subMinutes(6)]);

    UnpaidOrders::expire();

    expect($order->fresh()->status)->toBe(OrderStatus::Canceled);
});

test('a cancelled order can no longer be paid', function () {
    $order = waitingOrder(['status' => OrderStatus::Canceled, 'canceled_at' => now()]);

    $this->actingAs($this->cashier)
        ->post(route('orders.payment.store', $order), ['payment_method' => 'cash', 'cash_received' => 200])
        ->assertSessionHasErrors('payment');

    expect($order->fresh()->paid_at)->toBeNull();
});

/* ---------- what customers see ---------- */

test('customers see that their order is waiting for payment and when it cancels itself', function () {
    $order = waitingOrder();

    $this->withSession(['shop.orders' => [$order->id]])->get(route('shop.orders'))
        ->assertOk()
        ->assertSee('Waiting for payment')
        ->assertSee($order->autoCancelAt()->format('g:i A'));
});

test('an unpaid order that was cancelled tells the customer it was never paid', function () {
    $order = waitingOrder(['created_at' => now()->subMinutes(30)]);

    $this->withSession(['shop.orders' => [$order->id]])->get(route('shop.orders'))   // opening the page expires it
        ->assertOk()
        ->assertSee('cancelled before it was paid');
});
