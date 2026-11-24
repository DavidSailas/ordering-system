<?php

use App\Enums\OrderStatus;
use App\Models\Canteen;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderStrike;
use App\Models\PhoneSuspension;
use App\Models\SchoolMember;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\PhoneKey;
use App\Support\SchoolId;
use App\Support\Strikes;
use App\Support\UnpaidOrders;
use Illuminate\Support\Str;

/*
 * ID tap: students and teachers tap, scan or type their school ID (plus last name) at checkout. No account,
 * no password. The name comes from the school list, and missed orders follow the ID instead of the phone.
 */

beforeEach(function () {
    $this->maria = SchoolMember::factory()->create(['id_number' => '2024-0001', 'name' => 'Maria Santos']);
});

/** A guest checks out one dish; $fields change what the form sends. */
function idCheckout(array $fields = []): Illuminate\Testing\TestResponse
{
    $item = MenuItem::factory()->create(['price' => 100]);
    $token = 'cart-'.Str::random(8);
    CartItem::create(['guest_token' => $token, 'menu_item_id' => $item->id, 'quantity' => 1]);

    return test()->withCookie('shop_cart', $token)
        ->withSession(['shop.checkout_token' => 'tok', 'shop.canteen_id' => Canteen::default()->id])
        ->post('/checkout', array_merge([
            'customer_name' => '', 'phone' => '0917 555 0001', 'fulfillment' => 'pickup',
            'payment_choice' => 'cash', 'checkout_token' => 'tok',
        ], $fields));
}

function tapMaria(array $fields = []): Illuminate\Testing\TestResponse
{
    return idCheckout(array_merge(['school_id' => '2024-0001', 'school_last_name' => 'Santos'], $fields));
}

/** An unpaid order that is already late, placed with a school ID and its own phone number. */
function lateIdOrder(SchoolMember $member, string $phone): Order
{
    return Order::factory()->create([
        'status' => OrderStatus::AwaitingPayment, 'paid_at' => null, 'total' => 100, 'created_at' => now()->subMinutes(20),
        'school_member_id' => $member->id, 'phone' => $phone, 'phone_key' => PhoneKey::from($phone),
    ]);
}

/* ---------- tapping the ID ---------- */

test('tapping a school ID fills in the name from the school list', function () {
    tapMaria(['customer_name' => 'Somebody Else'])->assertSessionHasNoErrors();

    $order = Order::withoutGlobalScopes()->sole();

    expect($order->customer_name)->toBe('Maria Santos')
        ->and($order->school_member_id)->toEqual($this->maria->id)
        ->and($order->schoolMember->id_number)->toBe('2024-0001');
});

test('the name can be left empty when an ID is tapped, but not otherwise', function () {
    tapMaria(['customer_name' => ''])->assertSessionHasNoErrors();
    expect(Order::withoutGlobalScopes()->count())->toBe(1);

    idCheckout(['customer_name' => ''])->assertSessionHasErrors('customer_name');   // no ID, no name
});

test('the ID can be typed in any format and the last name in any case', function () {
    tapMaria(['school_id' => '20240001', 'school_last_name' => 'SANTOS'])->assertSessionHasNoErrors();
    tapMaria(['school_id' => '2024 0001', 'school_last_name' => ' santos ', 'phone' => '0917 555 0002'])->assertSessionHasNoErrors();

    expect(Order::withoutGlobalScopes()->count())->toBe(2);
});

test('teachers and staff tap their ID the same way', function () {
    SchoolMember::factory()->employee()->create(['id_number' => 'E-0105', 'name' => 'Elena Navarro']);

    idCheckout(['school_id' => 'e0105', 'school_last_name' => 'Navarro'])->assertSessionHasNoErrors();

    expect(Order::withoutGlobalScopes()->sole()->customer_name)->toBe('Elena Navarro');
});

test('an ID with the wrong last name, or an unknown ID, is refused with the same message', function () {
    tapMaria(['school_last_name' => 'Reyes'])->assertSessionHasErrors('school_id');
    $wrongName = session('errors')->first('school_id');

    idCheckout(['school_id' => '2099-9999', 'school_last_name' => 'Santos'])->assertSessionHasErrors('school_id');
    $unknownId = session('errors')->first('school_id');

    expect($wrongName)->toBe($unknownId)->and($wrongName)->toContain('could not match')
        ->and(Order::withoutGlobalScopes()->count())->toBe(0);
});

test('a blocked ID cannot order', function () {
    SchoolMember::factory()->blocked()->create(['id_number' => '2024-0666', 'name' => 'Pat Prank']);

    idCheckout(['school_id' => '2024-0666', 'school_last_name' => 'Prank'])->assertSessionHasErrors('school_id');

    expect(session('errors')->first('school_id'))->toContain('cannot be used')->and(Order::withoutGlobalScopes()->count())->toBe(0);
});

test('guessing an ID is locked out after a few wrong tries', function () {
    foreach (range(1, 5) as $_) {
        tapMaria(['school_last_name' => 'Wrong'])->assertSessionHasErrors('school_id');
    }

    // even the right last name is now refused for a while
    tapMaria()->assertSessionHasErrors('school_id');
    expect(session('errors')->first('school_id'))->toContain('Too many tries')->and(Order::withoutGlobalScopes()->count())->toBe(0);

    // other people are not affected
    SchoolMember::factory()->create(['id_number' => '2024-0002', 'name' => 'Juan Dela Cruz']);
    idCheckout(['school_id' => '2024-0002', 'school_last_name' => 'Dela Cruz'])->assertSessionHasNoErrors();
});

test('a correct tap clears earlier wrong tries', function () {
    foreach (range(1, 3) as $_) {
        tapMaria(['school_last_name' => 'Wrong']);
    }

    tapMaria()->assertSessionHasNoErrors();
    foreach (range(1, 4) as $_) {
        tapMaria(['school_last_name' => 'Wrong', 'phone' => '0917 555 0009']);
    }

    tapMaria(['phone' => '0917 555 0003'])->assertSessionHasNoErrors();   // 4 new wrong tries are still under the limit of 5
});

/* ---------- making the ID compulsory ---------- */

test('when the ID is compulsory, an order without one is refused', function () {
    SchoolId::setRequired(true);

    idCheckout(['customer_name' => 'Pat Reyes'])->assertSessionHasErrors('school_id');
    expect(Order::withoutGlobalScopes()->count())->toBe(0);

    tapMaria()->assertSessionHasNoErrors();
    expect(Order::withoutGlobalScopes()->count())->toBe(1);

    SchoolId::setRequired(false);
    idCheckout(['customer_name' => 'Pat Reyes', 'phone' => '0917 555 0004'])->assertSessionHasNoErrors();
});

test('the checkout page shows the ID fields', function () {
    $item = MenuItem::factory()->create();
    $token = 'cart-'.Str::random(8);
    CartItem::create(['guest_token' => $token, 'menu_item_id' => $item->id, 'quantity' => 1]);

    $this->withCookie('shop_cart', $token)->withSession(['shop.canteen_id' => Canteen::default()->id])
        ->get('/checkout')->assertOk()
        ->assertSee('name="school_id"', false)->assertSee('name="school_last_name"', false)->assertSee('optional');

    SchoolId::setRequired(true);

    $this->withCookie('shop_cart', $token)->withSession(['shop.canteen_id' => Canteen::default()->id])
        ->get('/checkout')->assertOk()->assertSee('name="school_id"', false);
});

test('only the overall administrator can make the ID compulsory', function () {
    $this->actingAs(User::factory()->owner()->create())->patch(route('admin.order-security.school-id'), ['required' => 1])->assertForbidden();
    $this->actingAs(User::factory()->cashier()->create())->patch(route('admin.order-security.school-id'), ['required' => 1])->assertForbidden();
    expect(SchoolId::required())->toBeFalse();

    $this->actingAs(User::factory()->superAdmin()->create())->patch(route('admin.order-security.school-id'), ['required' => 1])->assertRedirect();
    expect(SchoolId::required())->toBeTrue()->and(SiteSetting::get('school_id.required'))->toBe('1');

    $this->patch(route('admin.order-security.school-id'), ['required' => 0]);
    expect(SchoolId::required())->toBeFalse();
});

/* ---------- strikes follow the ID ---------- */

test('missed orders follow the school ID even when the phone number changes', function () {
    lateIdOrder($this->maria, '0917 600 0001');
    lateIdOrder($this->maria, '0917 600 0002');
    lateIdOrder($this->maria, '0917 600 0003');

    UnpaidOrders::expire();

    $pause = PhoneSuspension::query()->where('school_member_id', $this->maria->id)->sole();
    expect(OrderStrike::count())->toBe(3)
        ->and(Strikes::currentForMember($this->maria->id))->toBe(0)    // the count starts again after a pause begins
        ->and($pause->strikes)->toBe(3)
        ->and($pause->label())->toBe('Maria Santos (2024-0001)')
        ->and(PhoneSuspension::query()->whereNull('school_member_id')->count())->toBe(0);   // each phone only missed one
});

test('a paused ID cannot order from any phone, and is told when it can order again', function () {
    foreach (['0917 600 0001', '0917 600 0002', '0917 600 0003'] as $phone) {
        lateIdOrder($this->maria, $phone);
    }
    UnpaidOrders::expire();

    tapMaria(['phone' => '0917 999 8888'])->assertSessionHasErrors('school_id');   // a brand new phone does not help

    expect(session('errors')->first('school_id'))->toContain('paused')->toContain('school ID')
        ->and(Order::withoutGlobalScopes()->where('status', OrderStatus::AwaitingPayment->value)->count())->toBe(0);
});

test('a paused ID does not stop other people, or the phone numbers it used without the ID', function () {
    foreach (['0917 600 0001', '0917 600 0002', '0917 600 0003'] as $phone) {
        lateIdOrder($this->maria, $phone);
    }
    UnpaidOrders::expire();

    SchoolMember::factory()->create(['id_number' => '2024-0002', 'name' => 'Juan Dela Cruz']);
    idCheckout(['school_id' => '2024-0002', 'school_last_name' => 'Dela Cruz', 'phone' => '0917 700 0001'])->assertSessionHasNoErrors();

    idCheckout(['customer_name' => 'Walk In', 'phone' => '0917 600 0001'])->assertSessionHasNoErrors();   // the phone itself is not paused
});

test('the pause on an ID lifts by itself', function () {
    foreach (['0917 600 0001', '0917 600 0002', '0917 600 0003'] as $phone) {
        lateIdOrder($this->maria, $phone);
    }
    UnpaidOrders::expire();
    expect(Strikes::memberSuspension($this->maria))->not->toBeNull();

    $this->travelTo(now()->addDays(4));

    expect(Strikes::memberSuspension($this->maria))->toBeNull();
    tapMaria()->assertSessionHasNoErrors();
});

test('an ID cannot have more unpaid orders open than the limit, even with different phones', function () {
    tapMaria(['phone' => '0917 555 0001'])->assertSessionHasNoErrors();
    tapMaria(['phone' => '0917 555 0002'])->assertSessionHasNoErrors();
    tapMaria(['phone' => '0917 555 0003'])->assertSessionHasErrors('school_id');

    expect(session('errors')->first('school_id'))->toContain('already has 2 unpaid orders')
        ->and(Order::withoutGlobalScopes()->count())->toBe(2);
});

/* ---------- staff and administrator ---------- */

test('the cashier sees the school ID, and earlier missed orders for it', function () {
    OrderStrike::forceCreate(['phone_key' => '9175550001', 'phone' => '0917 555 0001', 'school_member_id' => $this->maria->id, 'reason' => 'x']);
    OrderStrike::forceCreate(['phone_key' => '9175550002', 'phone' => '0917 555 0002', 'school_member_id' => $this->maria->id, 'reason' => 'y']);
    Order::factory()->create([
        'status' => OrderStatus::AwaitingPayment, 'paid_at' => null, 'school_member_id' => $this->maria->id,
        'customer_name' => 'Maria Santos', 'phone' => '0917 555 0003', 'phone_key' => '9175550003', 'created_at' => now()->subMinutes(2),
    ]);

    $cashier = User::factory()->cashier()->forCanteen(Canteen::default())->create();

    $this->actingAs($cashier)->get(route('dashboard'))->assertOk()
        ->assertSee('ID 2024-0001')->assertSee('Student')->assertSee('2 missed orders');
});

test('the order page shows which school ID placed the order', function () {
    $order = Order::factory()->create(['school_member_id' => $this->maria->id, 'customer_name' => 'Maria Santos']);
    $owner = User::factory()->owner()->forCanteen(Canteen::default())->create();

    $this->actingAs($owner)->get(route('admin.orders.show', $order))->assertOk()->assertSee('School ID')->assertSee('2024-0001');
});

test('the administrator sees a paused ID by name and can lift the pause', function () {
    foreach (['0917 600 0001', '0917 600 0002', '0917 600 0003'] as $phone) {
        lateIdOrder($this->maria, $phone);
    }
    UnpaidOrders::expire();
    $pause = PhoneSuspension::query()->where('school_member_id', $this->maria->id)->sole();
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get(route('admin.order-security.index'))
        ->assertOk()->assertSee('Maria Santos (2024-0001)')->assertSee('School ID at checkout');

    $this->delete(route('admin.order-security.lift', $pause))->assertRedirect();

    expect(Strikes::memberSuspension($this->maria))->toBeNull();
});

test('blocking an ID on the school list stops it ordering at once', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->patch(route('admin.school-ids.block', $this->maria), ['reason' => 'Prank orders'])->assertSessionHasNoErrors();
    expect($this->maria->fresh()->is_blocked)->toBeTrue();

    app('auth')->forgetGuards();
    tapMaria()->assertSessionHasErrors('school_id');

    $this->actingAs($admin)->patch(route('admin.school-ids.block', $this->maria));
    app('auth')->forgetGuards();
    tapMaria()->assertSessionHasNoErrors();
});
