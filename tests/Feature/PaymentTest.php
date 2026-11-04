<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\User;

function payload(array $overrides = []): array
{
    return array_merge(['payment_method' => 'cash', 'cash_received' => 200], $overrides);
}

test('cashiers can confirm a cash payment once and see the change', function () {
    $cashier = User::factory()->cashier()->create();
    $order = Order::factory()->create(['total' => 150]);

    $this->actingAs($cashier)
        ->post(route('orders.payment.store', $order), payload())
        ->assertRedirect(route('orders.payment', $order));

    $order->refresh();

    expect($order->paid_by)->toBe($cashier->id)
        ->and($order->payment_method)->toBe('cash')
        ->and($order->paymentMethodOrCash())->toBe(PaymentMethod::Cash)
        ->and($order->amount_paid)->toBe('150.00')
        ->and($order->cash_received)->toBe('200.00');

    $this->get(route('orders.payment', $order))->assertOk()->assertSee('50.00');

    $this->post(route('orders.payment.store', $order), payload(['cash_received' => 300]))->assertSessionHasErrors('payment');
    expect($order->fresh()->cash_received)->toBe('200.00');
});

test('a discount lowers the amount charged', function () {
    $order = Order::factory()->create(['total' => 150]);

    $this->actingAs(User::factory()->cashier()->create())
        ->post(route('orders.payment.store', $order), payload(['discount' => 30, 'cash_received' => 120]))
        ->assertSessionHasNoErrors();

    expect($order->fresh()->amount_paid)->toBe('120.00')
        ->and($order->fresh()->discount)->toBe('30.00');
});

test('a discount cannot exceed the subtotal', function () {
    $order = Order::factory()->create(['total' => 150]);

    $this->actingAs(User::factory()->cashier()->create())
        ->post(route('orders.payment.store', $order), payload(['discount' => 151]))
        ->assertSessionHasErrors('discount');

    expect($order->fresh()->isPaid())->toBeFalse();
});

test('insufficient cash and canceled orders cannot be paid', function () {
    $order = Order::factory()->create(['total' => 150]);

    $this->actingAs(User::factory()->cashier()->create())
        ->post(route('orders.payment.store', $order), payload(['cash_received' => 100]))
        ->assertSessionHasErrors('cash_received');
    expect($order->fresh()->paid_at)->toBeNull();

    $order->update(['status' => OrderStatus::Canceled]);
    $this->post(route('orders.payment.store', $order), payload())->assertSessionHasErrors('payment');
    expect($order->fresh()->paid_at)->toBeNull();
});

test('gcash and maya payments need a reference but no cash', function () {
    $order = Order::factory()->create(['total' => 150]);
    $this->actingAs(User::factory()->cashier()->create());

    $this->post(route('orders.payment.store', $order), ['payment_method' => 'gcash'])
        ->assertSessionHasErrors('payment_reference');
    expect($order->fresh()->isPaid())->toBeFalse();

    $this->post(route('orders.payment.store', $order), ['payment_method' => 'gcash', 'payment_reference' => 'GC-12345'])
        ->assertSessionHasNoErrors();

    $order->refresh();

    expect($order->payment_method)->toBe('gcash')
        ->and($order->paymentMethodOrCash())->toBe(PaymentMethod::GCash)
        ->and($order->payment_reference)->toBe('GC-12345')
        ->and($order->cash_received)->toBeNull();
});

test('an unknown payment method is rejected', function () {
    $order = Order::factory()->create(['total' => 150]);

    $this->actingAs(User::factory()->cashier()->create())
        ->post(route('orders.payment.store', $order), ['payment_method' => 'bitcoin'])
        ->assertSessionHasErrors('payment_method');
});

test('paying an order that is still in the kitchen keeps its status', function () {
    $order = Order::factory()->create(['total' => 150]);

    $this->actingAs(User::factory()->cashier()->create())
        ->post(route('orders.payment.store', $order), payload());

    expect($order->fresh()->status)->toBe(OrderStatus::InProgress);
});

test('paying a ready to serve order completes it', function () {
    $order = Order::factory()->status(OrderStatus::ReadyToServe)->create(['total' => 150]);

    $this->actingAs(User::factory()->cashier()->create())
        ->post(route('orders.payment.store', $order), payload());

    expect($order->fresh()->status)->toBe(OrderStatus::Completed);
});

test('an unpaid order cannot be completed, a paid one can', function () {
    $this->actingAs(User::factory()->cashier()->create());
    $order = Order::factory()->status(OrderStatus::ReadyToServe)->create(['total' => 150]);

    $this->patch(route('orders.status', $order), ['status' => 'completed'])->assertSessionHasErrors('status');
    expect($order->fresh()->status)->toBe(OrderStatus::ReadyToServe);

    $order->update(['paid_at' => now()]);
    $this->patch(route('orders.status', $order), ['status' => 'completed'])->assertSessionHasNoErrors();
    expect($order->fresh()->status)->toBe(OrderStatus::Completed);
});

test('the receipt shows payment details for a paid order', function () {
    $order = Order::factory()->withItems(2)->create();
    $this->actingAs(User::factory()->cashier()->create(['name' => 'Casey Cashier']))
        ->post(route('orders.payment.store', $order), payload(['cash_received' => 99999]));

    $this->get(route('orders.receipt', $order))
        ->assertOk()
        ->assertSee('RECEIPT')
        ->assertSee('Casey Cashier')
        ->assertSee('Change')
        ->assertDontSee('UNPAID');
});

test('the receipt of an unpaid order is marked as an unpaid slip', function () {
    $order = Order::factory()->create(['total' => 150]);

    $this->actingAs(User::factory()->cashier()->create())
        ->get(route('orders.receipt', $order))
        ->assertOk()
        ->assertSee('ORDER SLIP')
        ->assertSee('AMOUNT DUE');
});

test('guests cannot record payment or view receipts', function () {
    $order = Order::factory()->create();

    $this->post(route('orders.payment.store', $order), payload())->assertRedirect(route('login'));
    $this->get(route('orders.receipt', $order))->assertRedirect(route('login'));
});

test('only admins can refund a paid order', function () {
    $order = Order::factory()->create(['total' => 150, 'paid_at' => now(), 'amount_paid' => 150, 'payment_method' => 'cash']);

    $this->actingAs(User::factory()->cashier()->create())
        ->post(route('admin.orders.refund', $order), ['reason' => 'Wrong order'])
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.orders.refund', $order), ['reason' => 'Wrong order'])
        ->assertSessionHasNoErrors();

    expect($order->fresh()->isRefunded())->toBeTrue()
        ->and($order->fresh()->status)->toBe(OrderStatus::Canceled);
});
