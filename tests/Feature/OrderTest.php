<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

test('the walk-in order endpoint no longer exists', function () {
    $this->actingAs(User::factory()->cashier()->create())
        ->postJson('/orders', [])
        ->assertNotFound();
});

test('an order moves from in progress to ready to serve to completed', function () {
    $this->actingAs(User::factory()->create());
    $order = Order::factory()->create();

    $this->patch(route('orders.status', $order), ['status' => 'ready_to_serve'])->assertRedirect();
    expect($order->refresh()->status)->toBe(OrderStatus::ReadyToServe);

    $order->update(['paid_at' => now()]);

    $this->patch(route('orders.status', $order), ['status' => 'completed'])->assertRedirect();
    expect($order->refresh()->status)->toBe(OrderStatus::Completed);
});

test('an order cannot skip a step', function () {
    $this->actingAs(User::factory()->create());
    $order = Order::factory()->create();

    $this->patch(route('orders.status', $order), ['status' => 'completed'])
        ->assertSessionHasErrors('status');

    expect($order->refresh()->status)->toBe(OrderStatus::InProgress);
});

test('only an admin can cancel, and only while the order is still open', function () {
    $open = Order::factory()->create();
    $finished = Order::factory()->status(OrderStatus::Completed)->create();

    $this->actingAs(User::factory()->cashier()->create())
        ->patch(route('orders.status', $open), ['status' => 'canceled'])
        ->assertForbidden();
    expect($open->refresh()->status)->toBe(OrderStatus::InProgress);

    $this->actingAs(User::factory()->admin()->create());
    $this->patch(route('orders.status', $open), ['status' => 'canceled'])->assertRedirect();
    expect($open->refresh()->status)->toBe(OrderStatus::Canceled);

    $this->patch(route('orders.status', $finished), ['status' => 'canceled'])
        ->assertSessionHasErrors('status');
    expect($finished->refresh()->status)->toBe(OrderStatus::Completed);
});
