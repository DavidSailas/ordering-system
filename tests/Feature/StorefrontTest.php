<?php

use App\Models\User;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;

test('guests can browse and update their cart', function () {
    $food = MenuItem::factory()->create(['price' => 125]);
    $this->get('/')->assertOk()->assertSee($food->name);
    $this->post('/cart', ['menu_item_id' => $food->id, 'quantity' => 2, 'action' => 'add'])
        ->assertSessionHasNoErrors();
    $this->assertDatabaseHas('cart_items', ['menu_item_id' => $food->id, 'quantity' => 2, 'user_id' => null]);
    // the test client does not keep cookies between requests, so send the cart cookie by hand
    $this->withCookie('shop_cart', CartItem::firstOrFail()->guest_token)->get('/cart')->assertOk()->assertSee('250.00');
});

test('a guest\'s cart is saved in the database and survives a new session', function () {
    $food = MenuItem::factory()->create(['price' => 100]);
    $this->post('/cart', ['menu_item_id' => $food->id, 'quantity' => 3, 'action' => 'add']);

    $token = CartItem::firstOrFail()->guest_token;

    // a brand-new browser session that still has the cart cookie sees the same cart
    $this->flushSession();
    $this->withCookie('shop_cart', $token)->get('/cart')->assertOk()->assertSee('300.00');

    // lowering the quantity to 0 removes the saved row
    $this->withCookie('shop_cart', $token)->post('/cart', ['menu_item_id' => $food->id, 'quantity' => 0, 'action' => 'set']);
    expect(CartItem::count())->toBe(0);
});

test('a signed-in customer\'s cart belongs to their account and takes over the guest cart on login', function () {
    $food = MenuItem::factory()->create(['price' => 100]);
    $user = User::factory()->create(['password' => 'password']);
    CartItem::create(['guest_token' => 'guest-tok', 'menu_item_id' => $food->id, 'quantity' => 2]);

    $this->withCookie('shop_cart', 'guest-tok')
        ->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->assertDatabaseHas('cart_items', ['user_id' => $user->id, 'menu_item_id' => $food->id, 'quantity' => 2, 'guest_token' => null]);

    $this->actingAs($user)->post('/cart', ['menu_item_id' => $food->id, 'quantity' => 1, 'action' => 'add']);
    expect(CartItem::where('user_id', $user->id)->value('quantity'))->toBe(3);
});

test('checkout uses catalog prices and records order lines', function () {
    $food = MenuItem::factory()->create(['price' => 125.50]);
    CartItem::create(['guest_token' => 'tok', 'menu_item_id' => $food->id, 'quantity' => 2]);
    $this->withCookie('shop_cart', 'tok')->withSession(['shop.checkout_token' => 'test-token'])
        ->post('/checkout', ['customer_name' => 'Jonna', 'phone' => '09123456789', 'room' => '101', 'checkout_token' => 'test-token', 'payment_choice' => 'cash', 'total' => 1])
        ->assertRedirect(route('shop.orders'));
    expect(CartItem::count())->toBe(0);
    $order = Order::firstOrFail();
    expect($order->total)->toBe('251.00');
    expect($order->items->first()->quantity)->toBe(2);
    $this->get('/my-orders')->assertOk()->assertSee($order->number);
    $this->post('/checkout', ['customer_name' => 'Jonna', 'phone' => '09123456789', 'room' => '101', 'checkout_token' => 'test-token'])
        ->assertSessionHasErrors();
    expect(Order::count())->toBe(1);
});

test('unavailable items are rejected and another session cannot see orders', function () {
    $food = MenuItem::factory()->unavailable()->create();
    $this->post('/cart', ['menu_item_id' => $food->id, 'quantity' => 1, 'action' => 'add'])->assertSessionHasErrors();
    CartItem::create(['guest_token' => 'tok', 'menu_item_id' => $food->id, 'quantity' => 1]);
    $this->withCookie('shop_cart', 'tok')->withSession(['shop.checkout_token' => 'test-token'])
        ->post('/checkout', ['customer_name' => 'Jonna', 'phone' => '09123456789', 'room' => '101', 'checkout_token' => 'test-token'])
        ->assertSessionHasErrors();
    expect(Order::count())->toBe(0);
    $order = Order::factory()->create();
    $this->get('/my-orders')->assertOk()->assertDontSee('Order #'.$order->number);
});
