<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;

test('guests cannot place orders', function () {
    $this->postJson(route('orders.store'), [])->assertUnauthorized();
});

test('a dine-in order is stored with prices taken from the menu, not the client', function () {
    $user = User::factory()->create();
    $table = DiningTable::factory()->create();
    $chicken = MenuItem::factory()->create(['name' => 'BBQ Chicken', 'price' => 10]);
    $kebab = MenuItem::factory()->create(['name' => 'Arabic Kebab', 'price' => 28]);

    $response = $this->actingAs($user)->postJson(route('orders.store'), [
        'type' => 'dine_in',
        'customer_name' => 'John Doe',
        'dining_table_id' => $table->id,
        'notes' => 'No onions',
        'items' => [
            ['menu_item_id' => $chicken->id, 'quantity' => 2, 'price' => 0.01],
            ['menu_item_id' => $kebab->id, 'quantity' => 1],
        ],
    ]);

    $response->assertCreated()->assertJsonStructure(['id', 'number', 'total']);

    $order = Order::query()->with('items')->sole();

    expect($order->user_id)->toBe($user->id)
        ->and($order->type)->toBe(OrderType::DineIn)
        ->and($order->status)->toBe(OrderStatus::InProgress)
        ->and($order->dining_table_id)->toBe($table->id)
        ->and($order->notes)->toBe('No onions')
        ->and($order->total)->toBe('48.00')
        ->and($order->items)->toHaveCount(2)
        ->and($order->items->firstWhere('name', 'BBQ Chicken')->price)->toBe('10.00');
});

test('a take away order does not keep a table', function () {
    $table = DiningTable::factory()->create();
    $item = MenuItem::factory()->create(['price' => 5]);

    $this->actingAs(User::factory()->create())->postJson(route('orders.store'), [
        'type' => 'take_away',
        'customer_name' => 'Jane',
        'dining_table_id' => $table->id,
        'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
    ])->assertCreated();

    expect(Order::query()->sole()->dining_table_id)->toBeNull();
});

test('an occupied table cannot take a second order', function () {
    $busy = Order::factory()->status(OrderStatus::ReadyToServe)->create();
    $item = MenuItem::factory()->create();

    $this->actingAs(User::factory()->create())->postJson(route('orders.store'), [
        'type' => 'dine_in',
        'customer_name' => 'John Doe',
        'dining_table_id' => $busy->dining_table_id,
        'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
    ])->assertJsonValidationErrors('dining_table_id');
});

test('a table is free again once its order is completed', function () {
    $done = Order::factory()->status(OrderStatus::Completed)->create();
    $item = MenuItem::factory()->create();

    $this->actingAs(User::factory()->create())->postJson(route('orders.store'), [
        'type' => 'dine_in',
        'customer_name' => 'John Doe',
        'dining_table_id' => $done->dining_table_id,
        'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
    ])->assertCreated();
});

test('orders need at least one available dish', function () {
    $unavailable = MenuItem::factory()->unavailable()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('orders.store'), [
            'type' => 'take_away',
            'customer_name' => 'Jane',
            'items' => [],
        ])
        ->assertJsonValidationErrors('items');

    $this->actingAs(User::factory()->create())
        ->postJson(route('orders.store'), [
            'type' => 'take_away',
            'customer_name' => 'Jane',
            'items' => [['menu_item_id' => $unavailable->id, 'quantity' => 1]],
        ])
        ->assertJsonValidationErrors('items.0.menu_item_id');
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
