<?php

use App\Models\Canteen;
use App\Models\User;

$backOfficeRoutes = [
    'admin.dashboard',
    'admin.foods.index',
    'admin.foods.create',
    'admin.categories.index',
    'admin.settings.edit',
    'admin.orders.index',
    'admin.reports.index',
    'admin.staff.index',
    'admin.staff.create',
];

$adminOnlyRoutes = [
    'admin.users.index',
    'admin.users.create',
    'admin.canteens.index',
];

test('cashiers are forbidden from every admin page', function (string $routeName) {
    $this->actingAs(User::factory()->cashier()->create())
        ->get(route($routeName))
        ->assertForbidden();
})->with(array_merge($backOfficeRoutes, $adminOnlyRoutes));

test('runners are forbidden from every admin page', function (string $routeName) {
    $this->actingAs(User::factory()->runner()->create())
        ->get(route($routeName))
        ->assertForbidden();
})->with(array_merge($backOfficeRoutes, $adminOnlyRoutes));

test('customers are forbidden from every staff page', function (string $routeName) {
    $this->actingAs(User::factory()->customer()->create())
        ->get(route($routeName))
        ->assertForbidden();
})->with(array_merge($backOfficeRoutes, $adminOnlyRoutes, ['dashboard', 'runner.dashboard']));

test('admins can open every admin page', function (string $routeName) {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route($routeName))
        ->assertOk();
})->with(array_merge($backOfficeRoutes, $adminOnlyRoutes));

test('owners can open the back office of their canteen', function (string $routeName) {
    $this->actingAs(User::factory()->owner()->create())
        ->get(route($routeName))
        ->assertOk();
})->with($backOfficeRoutes);

test('owners cannot open the administrator pages', function (string $routeName) {
    $this->actingAs(User::factory()->owner()->create())
        ->get(route($routeName))
        ->assertForbidden();
})->with($adminOnlyRoutes);

test('admins and owners can also use the cashier screen', function (string $factoryState) {
    $this->actingAs(User::factory()->{$factoryState}()->create())
        ->get(route('dashboard'))
        ->assertOk();
})->with(['admin', 'owner']);

test('only runners, owners and admins can open the runner screen', function () {
    $this->actingAs(User::factory()->runner()->create())->get(route('runner.dashboard'))->assertOk();
    $this->actingAs(User::factory()->owner()->create())->get(route('runner.dashboard'))->assertOk();
    $this->actingAs(User::factory()->cashier()->create())->get(route('runner.dashboard'))->assertForbidden();
});

test('runners cannot use the cashier screen', function () {
    $this->actingAs(User::factory()->runner()->create())
        ->get(route('dashboard'))
        ->assertForbidden();
});

test('the cashier screen has no admin management links', function () {
    $this->actingAs(User::factory()->cashier()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Admin management')
        ->assertDontSee(route('admin.dashboard'))
        ->assertDontSee('Walk-in');
});

test('a cashier cannot change menu data through the admin routes', function () {
    $this->actingAs(User::factory()->cashier()->create())
        ->post(route('admin.categories.store'), ['name' => 'Hacked'])
        ->assertForbidden();

    $this->assertDatabaseMissing('categories', ['name' => 'Hacked']);
});

test('an owner only sees the orders of their own canteen', function () {
    $kitchen = Canteen::default();
    $brew = Canteen::factory()->create();
    $otherOrder = \App\Models\Order::factory()->create(['canteen_id' => $brew->id]);

    $this->actingAs(User::factory()->owner()->forCanteen($kitchen)->create())
        ->get(route('admin.orders.show', $otherOrder))
        ->assertNotFound();
});

test('the order policy lets staff open their own canteen orders and customers only their own', function () {
    $canteen = Canteen::default();
    $other = Canteen::factory()->create();
    $mine = User::factory()->customer()->create();
    $theirs = User::factory()->customer()->create();

    $order = \App\Models\Order::withoutGlobalScopes()->create([
        'canteen_id' => $canteen->id, 'user_id' => $mine->id, 'customer_name' => 'Me', 'type' => 'take_away',
        'status' => 'in_progress', 'total' => 50,
    ]);

    expect($mine->can('view', $order))->toBeTrue()
        ->and($theirs->can('view', $order))->toBeFalse()
        ->and(User::factory()->cashier()->forCanteen($canteen)->make()->can('view', $order))->toBeTrue()
        ->and(User::factory()->cashier()->forCanteen($other)->make()->can('view', $order))->toBeFalse()
        ->and(User::factory()->superAdmin()->make()->can('view', $order))->toBeTrue();
});
