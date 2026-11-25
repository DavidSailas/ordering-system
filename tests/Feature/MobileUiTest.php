<?php

use App\Enums\OrderStatus;
use App\Models\Canteen;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\SchoolMember;
use App\Models\User;
use Illuminate\Support\Str;

/* Mobile polish: the pieces of the phone layout that the server draws. (The look itself is checked on a real phone.) */

function mobileCheckoutPage(): Illuminate\Testing\TestResponse
{
    $item = MenuItem::factory()->create();
    $token = 'cart-'.Str::random(8);
    CartItem::create(['guest_token' => $token, 'menu_item_id' => $item->id, 'quantity' => 1]);

    return test()->withCookie('shop_cart', $token)->withSession(['shop.canteen_id' => Canteen::default()->id])->get('/checkout');
}

test('the mobile polish stylesheet exists and both layouts load it', function () {
    expect(file_exists(public_path('polish.css')))->toBeTrue();

    mobileCheckoutPage()->assertOk()->assertSee('polish.css', false);

    $cashier = User::factory()->cashier()->forCanteen(Canteen::default())->create();
    $this->actingAs($cashier)->get(route('dashboard'))->assertOk()->assertSee('polish.css', false);
});

test('the checkout shows the ID panel with a camera button that stays hidden until the phone can scan', function () {
    mobileCheckoutPage()->assertOk()
        ->assertSee('class="co-id"', false)
        ->assertSee('id="scan-id"', false)
        ->assertSee('class="co-scan"', false)
        ->assertSee('class="co-help"', false)
        ->assertDontSee('class="co-edit" hidden', false);
});

test('the cashier board has four column tabs with their counts', function () {
    Order::factory()->create(['status' => OrderStatus::InProgress, 'paid_at' => now()]);
    $cashier = User::factory()->cashier()->forCanteen(Canteen::default())->create();

    $this->actingAs($cashier)->get(route('dashboard'))->assertOk()
        ->assertSee('class="board-tabs"', false)
        ->assertSee('data-col="0"', false)->assertSee('data-col="1"', false)->assertSee('data-col="2"', false)->assertSee('data-col="3"', false)
        ->assertSee('Waiting')->assertSee('Preparing')->assertSee('Ready')->assertSee('Done')
        ->assertDontSee('data-alert', false);   // nothing is waiting for payment, so the Waiting tab is not highlighted
});

test('the Waiting tab is highlighted when orders wait for payment', function () {
    Order::factory()->create(['status' => OrderStatus::AwaitingPayment, 'paid_at' => null, 'created_at' => now()->subMinutes(2)]);
    $cashier = User::factory()->cashier()->forCanteen(Canteen::default())->create();

    $this->actingAs($cashier)->get(route('dashboard'))->assertOk()->assertSee('data-alert', false);
});

test('order cards use tidy flag lines for the school ID, unpaid orders and missed orders', function () {
    $member = SchoolMember::factory()->create(['id_number' => '2024-0001', 'name' => 'Maria Santos']);
    Order::factory()->create([
        'status' => OrderStatus::AwaitingPayment, 'paid_at' => null, 'school_member_id' => $member->id, 'created_at' => now()->subMinutes(2),
    ]);
    $cashier = User::factory()->cashier()->forCanteen(Canteen::default())->create();

    $this->actingAs($cashier)->get(route('dashboard'))->assertOk()
        ->assertSee('class="order-flag"', false)
        ->assertSee('order-flag order-flag--wait', false)
        ->assertSee('ID 2024-0001');
});
