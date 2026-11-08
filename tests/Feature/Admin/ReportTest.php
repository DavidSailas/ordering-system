<?php

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

test('the dashboard shows todays paid sales without counting refunded or unpaid orders', function () {
    $cashier = User::factory()->cashier()->create(['name' => 'Casey Cashier']);

    Order::factory()->create(['user_id' => $cashier->id, 'total' => 40, 'paid_at' => now(), 'amount_paid' => 40]);
    Order::factory()->status(OrderStatus::Completed)->create(['user_id' => $cashier->id, 'total' => 10.5, 'paid_at' => now(), 'amount_paid' => 10.5]);
    Order::factory()->status(OrderStatus::Canceled)->create(['user_id' => $cashier->id, 'total' => 999, 'paid_at' => now(), 'amount_paid' => 999, 'refunded_at' => now()]);
    Order::factory()->create(['user_id' => $cashier->id, 'total' => 500]); // not paid yet

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('50.50')
        ->assertDontSee('1,049.50');
});

test('the report totals revenue, dishes and cashiers for a date range', function () {
    $category = Category::factory()->create(['name' => 'Main Course']);
    $chicken = MenuItem::factory()->for($category)->create(['name' => 'BBQ Chicken', 'price' => 10]);
    $casey = User::factory()->cashier()->create(['name' => 'Casey Cashier']);

    $order = Order::factory()->create(['user_id' => $casey->id, 'total' => 30]);
    $order->items()->create(['menu_item_id' => $chicken->id, 'name' => 'BBQ Chicken', 'price' => 10, 'quantity' => 3]);

    $canceled = Order::factory()->status(OrderStatus::Canceled)->create(['user_id' => $casey->id, 'total' => 500]);
    $canceled->items()->create(['menu_item_id' => $chicken->id, 'name' => 'BBQ Chicken', 'price' => 10, 'quantity' => 50]);

    $this->get(route('admin.reports.index', ['from' => now()->toDateString(), 'to' => now()->toDateString()]))
        ->assertOk()
        ->assertSee('₱30.00')
        ->assertSee('BBQ Chicken')
        ->assertSee('3 sold')
        ->assertDontSee('50 sold')
        ->assertSee('Main Course')
        ->assertSee('Casey Cashier');
});

test('orders outside the chosen dates are left out of the report', function () {
    Order::factory()->create(['total' => 77, 'created_at' => now()->subDays(10)]);

    $this->get(route('admin.reports.index', ['from' => now()->subDays(2)->toDateString(), 'to' => now()->toDateString()]))
        ->assertOk()
        ->assertDontSee('₱77.00');
});

test('the report validates its date range', function () {
    $this->get(route('admin.reports.index', ['from' => '2026-10-10', 'to' => '2026-10-01']))
        ->assertSessionHasErrors('to');

    $this->get(route('admin.reports.index', ['from' => 'not-a-date']))
        ->assertSessionHasErrors('from');
});
