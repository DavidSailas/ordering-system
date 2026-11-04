<?php

use App\Models\User;

$adminRoutes = [
    'admin.dashboard',
    'admin.foods.index',
    'admin.foods.create',
    'admin.categories.index',
    'admin.tables.index',
    'admin.orders.index',
    'admin.reports.index',
    'admin.staff.index',
    'admin.staff.create',
];

test('cashiers are forbidden from every admin page', function (string $routeName) {
    $this->actingAs(User::factory()->cashier()->create())
        ->get(route($routeName))
        ->assertForbidden();
})->with($adminRoutes);

test('admins can open every admin page', function (string $routeName) {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route($routeName))
        ->assertOk();
})->with($adminRoutes);

test('admins can also use the cashier screen', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Admin management');
});

test('cashiers do not see the admin shortcut', function () {
    $this->actingAs(User::factory()->cashier()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Admin management');
});

test('a cashier cannot change menu data through the admin routes', function () {
    $this->actingAs(User::factory()->cashier()->create())
        ->post(route('admin.categories.store'), ['name' => 'Hacked'])
        ->assertForbidden();

    $this->assertDatabaseMissing('categories', ['name' => 'Hacked']);
});
