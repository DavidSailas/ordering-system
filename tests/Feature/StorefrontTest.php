<?php

use App\Models\MenuItem;
use App\Models\Order;

test('guests can browse and update their cart', function () {
    $food = MenuItem::factory()->create(['price' => 125]);
    $this->get('/')->assertOk()->assertSee($food->name);
    $this->post('/cart', ['menu_item_id' => $food->id, 'quantity' => 2, 'action' => 'add'])
        ->assertSessionHas('shop.cart.'.$food->id, 2);
    $this->get('/cart')->assertOk()->assertSee('250.00');
});

test('checkout uses catalog prices and records order lines', function () {
    $food = MenuItem::factory()->create(['price' => 125.50]);
    $this->withSession(['shop.cart' => [$food->id => 2], 'shop.checkout_token' => 'test-token'])
        ->post('/checkout', ['customer_name' => 'Jonna', 'phone' => '09123456789', 'checkout_token' => 'test-token', 'payment_choice' => 'cash', 'total' => 1])
        ->assertRedirect(route('shop.orders'))->assertSessionMissing('shop.cart');
    $order = Order::firstOrFail();
    expect($order->total)->toBe('251.00');
    expect($order->items->first()->quantity)->toBe(2);
    $this->get('/my-orders')->assertOk()->assertSee($order->number);
    $this->post('/checkout', ['customer_name' => 'Jonna', 'phone' => '09123456789', 'checkout_token' => 'test-token'])
        ->assertSessionHasErrors();
    expect(Order::count())->toBe(1);
});

test('unavailable items are rejected and another session cannot see orders', function () {
    $food = MenuItem::factory()->unavailable()->create();
    $this->post('/cart', ['menu_item_id' => $food->id, 'quantity' => 1, 'action' => 'add'])->assertSessionHasErrors();
    $this->withSession(['shop.cart' => [$food->id => 1], 'shop.checkout_token' => 'test-token'])
        ->post('/checkout', ['customer_name' => 'Jonna', 'phone' => '09123456789', 'checkout_token' => 'test-token'])
        ->assertSessionHasErrors();
    expect(Order::count())->toBe(0);
    $order = Order::factory()->create();
    $this->get('/my-orders')->assertOk()->assertDontSee('Order #'.$order->number);
});
