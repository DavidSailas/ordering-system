<?php

use App\Models\CartItem;
use App\Models\Canteen;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('only admins and owners can open the settings page', function () {
    $this->actingAs(User::factory()->cashier()->create())->get(route('admin.settings.edit'))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.settings.edit'))->assertOk()->assertSee('Settings');
});

test('an owner can save the settings of their own canteen', function () {
    Storage::fake('public');
    $canteen = Canteen::default();
    $this->actingAs(User::factory()->owner()->forCanteen($canteen)->create());

    $this->put(route('admin.settings.update'), [
        'name' => 'Cecilian Canteen',
        'accent' => '#0f8f7e',
        'is_open' => '1',
        'buildings' => "Main Building\nScience Building\n",
        'gcash_number' => '09171234567',
        'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
    ])->assertRedirect(route('admin.settings.edit'));

    $canteen->refresh();

    expect($canteen->name)->toBe('Cecilian Canteen')
        ->and($canteen->accent)->toBe('#0f8f7e')
        ->and($canteen->buildingList())->toBe(['Main Building', 'Science Building'])
        ->and($canteen->gcash_number)->toBe('09171234567');

    Storage::disk('public')->assertExists($canteen->logo_path);
});

test('orders are refused while the canteen is closed', function () {
    Canteen::default()->update(['is_open' => false]);

    $item = MenuItem::factory()->create();

    CartItem::create(['guest_token' => 'tok', 'menu_item_id' => $item->id, 'quantity' => 1]);

    $this->withCookie('shop_cart', 'tok')->withSession(['shop.checkout_token' => 'abc'])
        ->post(route('shop.purchase'), [
            'customer_name' => 'Jane', 'phone' => '09171234567', 'room' => '101',
            'payment_choice' => 'cash', 'checkout_token' => 'abc',
        ])->assertSessionHasErrors('cart');

    $this->assertDatabaseCount('orders', 0);
});
