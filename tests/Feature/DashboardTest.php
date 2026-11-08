<?php

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('the cashier home page is the incoming orders board', function () {
    Order::factory()->create(['customer_name' => 'Preparing Pia', 'building' => 'Main Building', 'room' => '204']);
    Order::factory()->status(OrderStatus::ReadyToServe)->create(['customer_name' => 'Ready Rico']);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Incoming orders')
        ->assertSee('Preparing Pia')
        ->assertSee('Main Building · Room 204')
        ->assertSee('Ready Rico');
});

test('the incoming board can be searched by name or room', function () {
    Order::factory()->create(['customer_name' => 'Alice Reyes', 'room' => '101']);
    Order::factory()->create(['customer_name' => 'Bob Santos', 'room' => '202']);

    $this->get(route('dashboard', ['q' => 'alice']))->assertOk()->assertSee('Alice Reyes')->assertDontSee('Bob Santos');
    $this->get(route('dashboard', ['q' => '202']))->assertOk()->assertSee('Bob Santos')->assertDontSee('Alice Reyes');
});

test('the old incoming orders url redirects to the dashboard', function () {
    $this->get(route('cashier.incoming'))->assertRedirect('/dashboard');
});
