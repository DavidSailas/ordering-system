<?php

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('the dashboard lists categories and available dishes from the database', function () {
    $mains = Category::factory()->create(['name' => 'Main Course', 'slug' => 'main-course']);
    MenuItem::factory()->for($mains)->create(['name' => 'BBQ Chicken', 'price' => 10]);
    MenuItem::factory()->for($mains)->unavailable()->create(['name' => 'Sold Out Stew']);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Main Course')
        ->assertSee('BBQ Chicken')
        ->assertSee('10.00')
        ->assertDontSee('Sold Out Stew');
});

test('dishes can be filtered by category', function () {
    $mains = Category::factory()->create(['name' => 'Main Course', 'slug' => 'main-course']);
    $drinks = Category::factory()->create(['name' => 'Drinks', 'slug' => 'drinks']);
    MenuItem::factory()->for($mains)->create(['name' => 'BBQ Chicken']);
    MenuItem::factory()->for($drinks)->create(['name' => 'Iced Tea']);

    $this->get(route('dashboard', ['category' => 'drinks']))
        ->assertOk()
        ->assertSee('Iced Tea')
        ->assertDontSee('BBQ Chicken');
});

test('dishes can be searched by name', function () {
    $category = Category::factory()->create();
    MenuItem::factory()->for($category)->create(['name' => 'BBQ Chicken']);
    MenuItem::factory()->for($category)->create(['name' => 'Iced Tea']);

    $this->get(route('dashboard', ['q' => 'tea']))
        ->assertOk()
        ->assertSee('Iced Tea')
        ->assertDontSee('BBQ Chicken');
});

test('the incoming orders page shows recent orders and can be filtered by type', function () {
    Order::factory()->create(['customer_name' => 'Dine In Dana']);
    Order::factory()->takeAway()->create(['customer_name' => 'Takeaway Tom']);

    $this->get(route('cashier.incoming'))
        ->assertOk()
        ->assertSee('Dine In Dana')
        ->assertSee('Takeaway Tom');

    $this->get(route('cashier.incoming', ['type' => 'take_away']))
        ->assertOk()
        ->assertSee('Takeaway Tom')
        ->assertDontSee('Dine In Dana');
});
