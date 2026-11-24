<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\AuditLog;
use App\Models\Canteen;
use App\Models\CartItem;
use App\Models\DeliveryZone;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Str;

/*
 * Delivery fees by building and floor, and the security rules around them:
 * the server works out the fee, delivery is paid in advance, only admin and owner can change prices,
 * and every change is written to the audit trail.
 *
 * The canteen is in the Annex on the 6th floor (10 floors). The Main Building has 6 floors.
 */

beforeEach(function () {
    $this->canteen = Canteen::default();
    $this->canteen->update(['gcash_number' => '09171234567']);   // delivery needs an online payment option

    // The migration already created Annex and Main Building for the default canteen; start from a clean slate.
    DeliveryZone::withoutGlobalScopes()->delete();

    $this->annex = DeliveryZone::factory()->home(6)->create(['name' => 'Annex', 'floors' => 10, 'base_fee' => 10, 'per_floor_fee' => 2, 'sort_order' => 1]);
    $this->main = DeliveryZone::factory()->create(['name' => 'Main Building', 'floors' => 6, 'base_fee' => 25, 'per_floor_fee' => 2, 'sort_order' => 2]);
});

/** A guest's cart cookie holding one dish, the way a phone would have it. */
function cartCookie(float $price = 100): string
{
    $item = MenuItem::factory()->create(['price' => $price]);
    $token = 'cart-'.Str::random(8);
    CartItem::create(['guest_token' => $token, 'menu_item_id' => $item->id, 'quantity' => 1]);

    return $token;
}

/** Checks out a delivery order (GCash by default) with the given fields changed. */
function placeDelivery(array $fields = [], ?Canteen $canteen = null): Illuminate\Testing\TestResponse
{
    $canteen ??= Canteen::default();

    return test()->withCookie('shop_cart', cartCookie())
        ->withSession(['shop.checkout_token' => 'tok', 'shop.canteen_id' => $canteen->id])
        ->post('/checkout', array_merge([
            'customer_name' => 'Pat Reyes', 'phone' => '0917 123 4567', 'fulfillment' => 'delivery',
            'zone_id' => test()->annex->id, 'floor' => 8, 'room' => '804',
            'payment_choice' => 'gcash', 'checkout_token' => 'tok',
        ], $fields));
}

function zoneInput(array $over = []): array
{
    return array_merge(['name' => 'Annex', 'floors' => 10, 'base_fee' => '10.00', 'per_floor_fee' => '2.00', 'is_active' => 1, 'is_home' => 1, 'home_floor' => 6], $over);
}

/* ---------- the fee maths ---------- */

test('inside the canteen building the fee counts floors up or down from the canteen floor', function () {
    expect($this->annex->feeCentsForFloor(6))->toBe(1000)    // same floor: base fee only
        ->and($this->annex->feeCentsForFloor(8))->toBe(1400)  // 2 floors up
        ->and($this->annex->feeCentsForFloor(10))->toBe(1800) // 4 floors up
        ->and($this->annex->feeCentsForFloor(1))->toBe(2000); // 5 floors down
});

test('in another building the fee counts floors from the ground floor', function () {
    expect($this->main->feeCentsForFloor(1))->toBe(2500)
        ->and($this->main->feeCentsForFloor(3))->toBe(2900)
        ->and($this->main->feeCentsForFloor(6))->toBe(3500);
});

test('a fee can never go above the safety ceiling', function () {
    $zone = DeliveryZone::factory()->create(['name' => 'Tower', 'floors' => 60, 'base_fee' => 500, 'per_floor_fee' => 100]);

    expect($zone->feeCentsForFloor(60))->toBe(DeliveryZone::MAX_FEE * 100);
});

/* ---------- checkout: what the server decides ---------- */

test('a delivery order is charged food plus the fee, and the fee is saved on the order', function () {
    placeDelivery()->assertSessionHasNoErrors();

    $order = Order::withoutGlobalScopes()->sole();

    expect($order->type)->toBe(OrderType::Delivery)
        ->and($order->building)->toBe('Annex')
        ->and($order->floor)->toBe(8)
        ->and($order->room)->toBe('804')
        ->and($order->delivery_fee)->toBe('14.00')
        ->and($order->total)->toBe('114.00')
        ->and($order->foodSubtotal())->toBe(100.0)
        ->and($order->location)->toBe('Annex · Floor 8 · Room 804');
});

test('the browser cannot set the fee or the total', function () {
    placeDelivery(['delivery_fee' => 0, 'total' => 1, 'fee' => 0])->assertSessionHasNoErrors();

    $order = Order::withoutGlobalScopes()->sole();

    expect($order->delivery_fee)->toBe('14.00')->and($order->total)->toBe('114.00');
});

test('changing the price later does not rewrite an order that was already placed', function () {
    placeDelivery()->assertSessionHasNoErrors();
    $this->annex->update(['base_fee' => 50]);

    expect(Order::withoutGlobalScopes()->sole()->delivery_fee)->toBe('14.00');
});

test('delivery must be paid in advance, so cash is refused', function () {
    placeDelivery(['payment_choice' => 'cash'])->assertSessionHasErrors('payment_choice');

    expect(Order::withoutGlobalScopes()->count())->toBe(0);
});

test('a floor that does not exist in the building is refused', function () {
    placeDelivery(['zone_id' => $this->main->id, 'floor' => 7])->assertSessionHasErrors('floor');   // Main has 6 floors
    placeDelivery(['floor' => 0])->assertSessionHasErrors('floor');
    placeDelivery(['floor' => 11])->assertSessionHasErrors('floor');                                  // Annex has 10 floors

    expect(Order::withoutGlobalScopes()->count())->toBe(0);
});

test('a building that is hidden, missing or belongs to another canteen is refused', function () {
    $this->main->update(['is_active' => false]);
    $other = Canteen::factory()->create();
    $foreign = DeliveryZone::factory()->create(['canteen_id' => $other->id, 'name' => 'Elsewhere']);

    placeDelivery(['zone_id' => $this->main->id, 'floor' => 2])->assertSessionHasErrors('floor');
    placeDelivery(['zone_id' => $foreign->id, 'floor' => 2])->assertSessionHasErrors('floor');
    placeDelivery(['zone_id' => 99999])->assertSessionHasErrors('floor');

    expect(Order::withoutGlobalScopes()->count())->toBe(0);
});

test('delivery needs a room so the runner can find the customer', function () {
    placeDelivery(['room' => ''])->assertSessionHasErrors('room');
});

test('with delivery switched off, customers can only pick up', function () {
    $this->canteen->update(['delivery_enabled' => false]);

    placeDelivery()->assertSessionHasErrors('fulfillment');
    expect(Order::withoutGlobalScopes()->count())->toBe(0);

    $this->withCookie('shop_cart', cartCookie())->withSession(['shop.canteen_id' => $this->canteen->id])
        ->get('/checkout')->assertOk()->assertDontSee('name="zone_id"', false);
});

test('pickup is free and needs no room', function () {
    placeDelivery(['fulfillment' => 'pickup', 'room' => '', 'payment_choice' => 'cash'])->assertSessionHasNoErrors();

    $order = Order::withoutGlobalScopes()->sole();

    expect($order->type)->toBe(OrderType::TakeAway)
        ->and($order->delivery_fee)->toBe('0.00')
        ->and($order->total)->toBe('100.00')
        ->and($order->building)->toBeNull();
});

test('the checkout page offers buildings and floors with their fees', function () {
    $this->withCookie('shop_cart', cartCookie())->withSession(['shop.canteen_id' => $this->canteen->id])
        ->get('/checkout')
        ->assertOk()
        ->assertSee('name="zone_id"', false)
        ->assertSee('Annex')
        ->assertSee('Main Building')
        ->assertSee('Floor 8 · ₱14.00', false);
});

test('delivery is hidden when the canteen has no online payment account', function () {
    $this->canteen->update(['gcash_number' => null, 'maya_number' => null]);

    $this->withCookie('shop_cart', cartCookie())->withSession(['shop.canteen_id' => $this->canteen->id])
        ->get('/checkout')->assertOk()->assertDontSee('name="zone_id"', false);
});

/* ---------- the runner ---------- */

test('runners only see delivery orders that are paid', function () {
    $runner = User::factory()->runner()->forCanteen($this->canteen)->create();
    $base = ['building' => 'Annex', 'floor' => 8, 'room' => '804'];

    Order::factory()->delivery()->status(OrderStatus::ReadyToServe)->create($base + ['customer_name' => 'Paid Pablo', 'paid_at' => now()]);
    Order::factory()->delivery()->status(OrderStatus::ReadyToServe)->create($base + ['customer_name' => 'Unpaid Una', 'paid_at' => null]);

    $this->actingAs($runner)->get(route('runner.dashboard'))
        ->assertOk()
        ->assertSee('Paid Pablo')
        ->assertSee('Annex · Floor 8 · Room 804')
        ->assertDontSee('Unpaid Una');
});

test('the receipt shows the delivery fee on its own line', function () {
    $owner = User::factory()->owner()->forCanteen($this->canteen)->create();
    $order = Order::factory()->delivery()->create(['total' => 114, 'delivery_fee' => 14, 'building' => 'Annex', 'floor' => 8, 'room' => '804']);

    $this->actingAs($owner)->get(route('orders.receipt', $order))
        ->assertOk()
        ->assertSee('Delivery fee')
        ->assertSee('100.00')
        ->assertSee('14.00');
});

/* ---------- who may change prices ---------- */

test('only admin and owner accounts can open or change delivery fees', function () {
    $this->get(route('admin.delivery.index'))->assertRedirect(route('login'));

    foreach ([User::factory()->cashier(), User::factory()->runner(), User::factory()->customer()] as $factory) {
        $this->actingAs($factory->forCanteen($this->canteen)->create());

        $this->get(route('admin.delivery.index'))->assertForbidden();
        // Accounts without a canteen (customers) get "not found" for a specific building, everyone else "forbidden":
        // either way the request is refused, and the checks after the loop prove nothing changed.
        expect($this->put(route('admin.delivery.zones.update', $this->annex), zoneInput(['base_fee' => '1']))->status())->toBeIn([403, 404]);
        $this->post(route('admin.delivery.zones.store'), zoneInput(['name' => 'Sneaky', 'is_home' => 0]))->assertForbidden();
        expect($this->delete(route('admin.delivery.zones.destroy', $this->annex))->status())->toBeIn([403, 404]);
        $this->put(route('admin.delivery.settings'), ['delivery_enabled' => 0])->assertForbidden();
    }

    expect($this->annex->refresh()->base_fee)->toBe('10.00')
        ->and(DeliveryZone::withoutGlobalScopes()->count())->toBe(2);
});

test('an owner sees and edits only their own canteen\'s buildings', function () {
    $other = Canteen::factory()->create();
    $foreign = DeliveryZone::factory()->create(['canteen_id' => $other->id, 'name' => 'Elsewhere Hall']);
    $this->actingAs(User::factory()->owner()->forCanteen($this->canteen)->create());

    $this->get(route('admin.delivery.index'))->assertOk()->assertSee('Annex')->assertSee('Main Building')->assertDontSee('Elsewhere Hall');
    $this->put(route('admin.delivery.zones.update', $foreign), zoneInput(['name' => 'Hacked']))->assertNotFound();
    $this->delete(route('admin.delivery.zones.destroy', $foreign))->assertNotFound();

    expect($foreign->refresh()->name)->toBe('Elsewhere Hall');
});

test('an owner can change fees, floors and the canteen location', function () {
    $this->actingAs(User::factory()->owner()->forCanteen($this->canteen)->create());

    $this->put(route('admin.delivery.zones.update', $this->annex), zoneInput(['floors' => 12, 'base_fee' => '12.00', 'per_floor_fee' => '3.00', 'home_floor' => 5]))
        ->assertRedirect()->assertSessionHasNoErrors();

    $this->annex->refresh();
    expect($this->annex->floors)->toBe(12)
        ->and($this->annex->base_fee)->toBe('12.00')
        ->and($this->annex->per_floor_fee)->toBe('3.00')
        ->and($this->annex->home_floor)->toBe(5)
        ->and($this->annex->feeCentsForFloor(7))->toBe(1800);   // 12 + 2 floors × 3
});

test('an owner can add and remove a building', function () {
    $this->actingAs(User::factory()->owner()->forCanteen($this->canteen)->create());

    $this->post(route('admin.delivery.zones.store'), zoneInput(['name' => 'Library', 'floors' => 3, 'base_fee' => '20.00', 'is_home' => 0, 'home_floor' => '']))
        ->assertSessionHasNoErrors();

    $library = DeliveryZone::withoutGlobalScopes()->where('name', 'Library')->sole();
    expect($library->canteen_id)->toEqual($this->canteen->id)->and($library->floors)->toBe(3);

    $this->delete(route('admin.delivery.zones.destroy', $library))->assertRedirect();
    expect(DeliveryZone::withoutGlobalScopes()->where('name', 'Library')->exists())->toBeFalse();
});

test('a canteen is in one building only', function () {
    $this->actingAs(User::factory()->owner()->forCanteen($this->canteen)->create());

    $this->put(route('admin.delivery.zones.update', $this->main), zoneInput(['name' => 'Main Building', 'floors' => 6, 'base_fee' => '25', 'per_floor_fee' => '2', 'home_floor' => 2]))
        ->assertSessionHasNoErrors();

    expect($this->main->refresh()->is_home)->toBeTrue()
        ->and($this->annex->refresh()->is_home)->toBeFalse()
        ->and($this->annex->home_floor)->toBeNull();
});

test('silly or mistyped prices are rejected', function () {
    $this->actingAs(User::factory()->owner()->forCanteen($this->canteen)->create());

    $this->put(route('admin.delivery.zones.update', $this->annex), zoneInput(['base_fee' => '5000']))->assertSessionHasErrors('base_fee');
    $this->put(route('admin.delivery.zones.update', $this->annex), zoneInput(['base_fee' => '-5']))->assertSessionHasErrors('base_fee');
    $this->put(route('admin.delivery.zones.update', $this->annex), zoneInput(['floors' => 0]))->assertSessionHasErrors('floors');
    $this->put(route('admin.delivery.zones.update', $this->annex), zoneInput(['floors' => 60, 'base_fee' => '500', 'per_floor_fee' => '100']))->assertSessionHasErrors('per_floor_fee');
    $this->put(route('admin.delivery.zones.update', $this->annex), zoneInput(['home_floor' => 11]))->assertSessionHasErrors('home_floor');   // Annex has 10 floors
    $this->post(route('admin.delivery.zones.store'), zoneInput(['name' => 'Annex', 'is_home' => 0]))->assertSessionHasErrors('name');          // duplicate name
    $this->post(route('admin.delivery.zones.store'), zoneInput(['name' => 'Gym', 'is_home' => 1, 'home_floor' => '']))->assertSessionHasErrors('home_floor');

    expect($this->annex->refresh()->base_fee)->toBe('10.00');
});

/* ---------- the audit trail ---------- */

test('every change to fees and buildings is recorded with who made it', function () {
    $owner = User::factory()->owner()->forCanteen($this->canteen)->create(['name' => 'Olive Owner']);
    $this->actingAs($owner);

    $this->put(route('admin.delivery.zones.update', $this->annex), zoneInput(['base_fee' => '12.00']));
    $this->post(route('admin.delivery.zones.store'), zoneInput(['name' => 'Library', 'is_home' => 0, 'home_floor' => '']));
    $this->put(route('admin.delivery.settings'), ['delivery_enabled' => 0]);
    $this->delete(route('admin.delivery.zones.destroy', $this->main));

    $logs = AuditLog::withoutGlobalScopes()->orderBy('id')->get();

    expect($logs->pluck('action')->all())->toBe(['delivery.updated', 'delivery.created', 'delivery.toggled', 'delivery.deleted'])
        ->and($logs->pluck('user_id')->unique()->values()->all())->toEqual([$owner->id])
        ->and($logs[0]->summary)->toContain('base fee 10.00 → 12.00')
        ->and($logs[0]->old['base_fee'])->toBe('10.00')
        ->and($logs[0]->new['base_fee'])->toBe('12.00');

    $this->get(route('admin.delivery.index'))->assertOk()->assertSee('Olive Owner')->assertSee('base fee 10.00 → 12.00');
});

test('saving without changing anything adds nothing to the audit trail', function () {
    $this->actingAs(User::factory()->owner()->forCanteen($this->canteen)->create());

    $this->put(route('admin.delivery.zones.update', $this->annex), zoneInput())->assertSessionHasNoErrors();

    expect(AuditLog::withoutGlobalScopes()->count())->toBe(0);
});

test('a new canteen starts with the Annex and Main Building already set up', function () {
    $canteen = Canteen::factory()->create();

    DeliveryZone::seedDefaultsFor($canteen);
    DeliveryZone::seedDefaultsFor($canteen);   // running it twice never duplicates

    $zones = DeliveryZone::withoutGlobalScopes()->where('canteen_id', $canteen->id)->orderBy('sort_order')->get();

    expect($zones->pluck('name')->all())->toBe(['Annex', 'Main Building'])
        ->and($zones[0]->is_home)->toBeTrue()
        ->and($zones[0]->home_floor)->toBe(6)
        ->and($zones[0]->floors)->toBe(10)
        ->and($zones[1]->floors)->toBe(6);
});
