<?php

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

test('the menu page lists dishes, including sold out ones, and can filter them', function () {
    $mains = Category::factory()->create(['name' => 'Main Course', 'slug' => 'main-course']);
    $drinks = Category::factory()->create(['name' => 'Drinks', 'slug' => 'drinks']);
    MenuItem::factory()->for($mains)->create(['name' => 'BBQ Chicken']);
    MenuItem::factory()->for($drinks)->unavailable()->create(['name' => 'Iced Tea']);

    $this->get(route('admin.foods.index'))
        ->assertOk()
        ->assertSee('BBQ Chicken')
        ->assertSee('Iced Tea')
        ->assertSee('Sold out');

    $this->get(route('admin.foods.index', ['category' => 'drinks']))
        ->assertSee('Iced Tea')
        ->assertDontSee('BBQ Chicken');

    $this->get(route('admin.foods.index', ['q' => 'bbq']))
        ->assertSee('BBQ Chicken')
        ->assertDontSee('Iced Tea');
});

test('a dish can be added with a photo', function () {
    Storage::fake('public');
    $category = Category::factory()->create();

    $this->post(route('admin.foods.store'), [
        'name' => 'Sisig',
        'category_id' => $category->id,
        'price' => '14.50',
        'description' => 'Sizzling pork',
        'is_available' => '1',
        'image' => UploadedFile::fake()->image('sisig.jpg', 800, 600),
    ])->assertRedirect(route('admin.foods.index'));

    $item = MenuItem::query()->where('name', 'Sisig')->sole();

    expect($item->price)->toBe('14.50')
        ->and($item->is_available)->toBeTrue()
        ->and($item->image_path)->not->toBeNull();

    Storage::disk('public')->assertExists($item->image_path);
});

test('new dishes show up on the cashier screen', function () {
    $category = Category::factory()->create();

    $this->post(route('admin.foods.store'), [
        'name' => 'Sisig',
        'category_id' => $category->id,
        'price' => 14.5,
        'is_available' => '1',
    ]);

    $this->get(route('dashboard'))->assertSee('Sisig');
});

test('a dish needs a name, a category and a valid price', function () {
    $this->post(route('admin.foods.store'), ['name' => '', 'category_id' => 999, 'price' => 'free'])
        ->assertSessionHasErrors(['name', 'category_id', 'price']);

    expect(MenuItem::query()->count())->toBe(0);
});

test('a dish can be edited and its photo replaced', function () {
    Storage::fake('public');
    $item = MenuItem::factory()->create(['name' => 'Old Name', 'price' => 5, 'image_path' => 'menu/old.jpg']);
    Storage::disk('public')->put('menu/old.jpg', 'old');

    $this->put(route('admin.foods.update', $item), [
        'name' => 'New Name',
        'category_id' => $item->category_id,
        'price' => '9.00',
        'is_available' => '0',
        'image' => UploadedFile::fake()->image('new.png', 800, 600),
    ])->assertRedirect(route('admin.foods.index'));

    $item->refresh();

    expect($item->name)->toBe('New Name')
        ->and($item->price)->toBe('9.00')
        ->and($item->is_available)->toBeFalse();

    Storage::disk('public')->assertMissing('menu/old.jpg');
    Storage::disk('public')->assertExists($item->image_path);
});

test('a dish photo can be removed', function () {
    Storage::fake('public');
    $item = MenuItem::factory()->create(['image_path' => 'menu/old.jpg']);
    Storage::disk('public')->put('menu/old.jpg', 'old');

    $this->put(route('admin.foods.update', $item), [
        'name' => $item->name,
        'category_id' => $item->category_id,
        'price' => $item->price,
        'is_available' => '1',
        'remove_image' => '1',
    ])->assertRedirect();

    expect($item->refresh()->image_path)->toBeNull();
    Storage::disk('public')->assertMissing('menu/old.jpg');
});

test('availability can be toggled', function () {
    $item = MenuItem::factory()->create();

    $this->patch(route('admin.foods.availability', $item))->assertRedirect();
    expect($item->refresh()->is_available)->toBeFalse();

    $this->patch(route('admin.foods.availability', $item))->assertRedirect();
    expect($item->refresh()->is_available)->toBeTrue();
});

test('a dish can be deleted', function () {
    $item = MenuItem::factory()->create();

    $this->delete(route('admin.foods.destroy', $item))->assertRedirect(route('admin.foods.index'));

    $this->assertModelMissing($item);
});
