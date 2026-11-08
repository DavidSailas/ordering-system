<?php

use App\Models\CartItem;
use App\Models\Canteen;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;

beforeEach(function () {
    $this->kitchen = Canteen::default();
    $this->brew = Canteen::factory()->create(['name' => 'Brew Corner', 'slug' => 'brew-corner']);

    $this->kitchenDish = MenuItem::factory()->create(['name' => 'Kitchen Adobo']);

    $brewCategory = Category::factory()->create(['canteen_id' => $this->brew->id]);
    $this->brewDish = MenuItem::factory()->create([
        'name' => 'Brew Burger',
        'canteen_id' => $this->brew->id,
        'category_id' => $brewCategory->id,
    ]);
});

test('an owner only sees their own canteen\'s dishes and orders', function () {
    Order::factory()->create(['customer_name' => 'Kitchen Karla']);
    Order::factory()->create(['customer_name' => 'Brew Bruno', 'canteen_id' => $this->brew->id]);

    $this->actingAs(User::factory()->admin()->forCanteen($this->brew)->create());

    $this->get(route('admin.foods.index'))->assertOk()->assertSee('Brew Burger')->assertDontSee('Kitchen Adobo');
    $this->get(route('admin.orders.index'))->assertOk()->assertSee('Brew Bruno')->assertDontSee('Kitchen Karla');
});

test('an owner cannot open another canteen\'s order or dish by its id', function () {
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->admin()->forCanteen($this->brew)->create());

    $this->get(route('admin.orders.show', $order))->assertNotFound();
    $this->get(route('admin.foods.edit', $this->kitchenDish))->assertNotFound();
});

test('a cashier only sees and changes their own canteen\'s orders', function () {
    $mine = Order::factory()->create(['customer_name' => 'Brew Bruno', 'canteen_id' => $this->brew->id]);
    $other = Order::factory()->create(['customer_name' => 'Kitchen Karla']);

    $this->actingAs(User::factory()->cashier()->forCanteen($this->brew)->create());

    $this->get(route('dashboard'))->assertOk()->assertSee('Brew Bruno')->assertDontSee('Kitchen Karla');
    $this->patch(route('orders.status', $other), ['status' => 'ready_to_serve'])->assertNotFound();
    $this->patch(route('orders.status', $mine), ['status' => 'ready_to_serve'])->assertRedirect();
});

test('owners only manage their own canteen\'s staff', function () {
    $kitchenCashier = User::factory()->cashier()->create(['name' => 'Kitchen Kiko']);

    $this->actingAs(User::factory()->admin()->forCanteen($this->brew)->create());

    $this->get(route('admin.staff.index'))->assertOk()->assertDontSee('Kitchen Kiko');
    $this->get(route('admin.staff.edit', $kitchenCashier))->assertNotFound();
});

test('a category cannot be created with another canteen\'s category id for a dish', function () {
    $this->actingAs(User::factory()->admin()->forCanteen($this->brew)->create());

    $this->post(route('admin.foods.store'), [
        'category_id' => $this->kitchenDish->category_id,
        'name' => 'Sneaky Dish',
        'price' => 50,
    ])->assertSessionHasErrors('category_id');
});

test('only the overall administrator can see all canteens and switch between them', function () {
    $this->actingAs(User::factory()->admin()->forCanteen($this->brew)->create());
    $this->get(route('admin.canteens.index'))->assertForbidden();

    $this->actingAs(User::factory()->superAdmin()->create());
    $this->get(route('admin.canteens.index'))->assertOk()->assertSee('Brew Corner')->assertSee($this->kitchen->name);

    $this->post(route('admin.canteens.switch', $this->brew))->assertRedirect(route('admin.dashboard'));
    $this->get(route('admin.foods.index'))->assertOk()->assertSee('Brew Burger')->assertDontSee('Kitchen Adobo');
});

test('the overall administrator can add a canteen', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $this->post(route('admin.canteens.store'), ['name' => 'Night Market', 'accent' => '#123456'])
        ->assertRedirect(route('admin.settings.edit'));

    $this->assertDatabaseHas('canteens', ['name' => 'Night Market', 'slug' => 'night-market']);
});

test('with several canteens customers choose one before seeing a menu', function () {
    $this->get('/')->assertRedirect(route('shop.canteens'));

    $this->get(route('shop.canteens'))->assertOk()->assertSee('Brew Corner')->assertSee($this->kitchen->name);

    $this->post(route('shop.canteens.choose', $this->brew))->assertRedirect(route('shop.menu'));
    $this->get('/menu')->assertOk()->assertSee('Brew Burger')->assertDontSee('Kitchen Adobo');
});

test('switching canteen removes the other canteen\'s dishes from the saved cart', function () {
    CartItem::create(['guest_token' => 'tok', 'menu_item_id' => $this->kitchenDish->id, 'quantity' => 2]);

    $this->withCookie('shop_cart', 'tok')->withSession(['shop.canteen_id' => $this->kitchen->id])
        ->post(route('shop.canteens.choose', $this->brew));

    expect(CartItem::count())->toBe(0);
});

test('choosing the same canteen again keeps the saved cart', function () {
    CartItem::create(['guest_token' => 'tok', 'menu_item_id' => $this->kitchenDish->id, 'quantity' => 2]);

    $this->withCookie('shop_cart', 'tok')
        ->post(route('shop.canteens.choose', $this->kitchen));

    expect(CartItem::count())->toBe(1);
});

test('a customer cannot add another canteen\'s dish to the cart', function () {
    $this->withSession(['shop.canteen_id' => $this->brew->id])
        ->post('/cart', ['menu_item_id' => $this->kitchenDish->id, 'quantity' => 1, 'action' => 'add'])
        ->assertNotFound();
});

test('an order is saved in the canteen the customer picked, with its own numbering', function () {
    Order::factory()->count(2)->create();

    CartItem::create(['guest_token' => 'cart-tok', 'menu_item_id' => $this->brewDish->id, 'quantity' => 1]);

    $this->withCookie('shop_cart', 'cart-tok')->withSession([
        'shop.canteen_id' => $this->brew->id,
        'shop.checkout_token' => 'tok',
    ])->post('/checkout', [
        'customer_name' => 'Jane', 'phone' => '09171234567', 'room' => '204',
        'payment_choice' => 'cash', 'checkout_token' => 'tok',
    ]);

    $order = Order::withoutGlobalScopes()->latest('id')->first();

    expect($order->canteen_id)->toEqual($this->brew->id)
        ->and($order->canteen_number)->toEqual(1)
        ->and($order->room)->toBe('204');
});
