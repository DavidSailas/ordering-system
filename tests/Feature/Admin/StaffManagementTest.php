<?php

use App\Enums\UserRole;
use App\Models\Canteen;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
});

test('the staff page lists the accounts of the canteen with their roles', function () {
    User::factory()->cashier()->create(['name' => 'Casey Cashier']);
    User::factory()->runner()->create(['name' => 'Rico Runner']);
    User::factory()->cashier()->forCanteen(Canteen::factory()->create())->create(['name' => 'Elsewhere Eve']);

    $this->get(route('admin.staff.index'))
        ->assertOk()
        ->assertSee($this->owner->name)
        ->assertSee('Casey Cashier')
        ->assertSee('Rico Runner')
        ->assertDontSee('Elsewhere Eve');
});

test('an owner can create a cashier and the cashier can log in', function () {
    $this->post(route('admin.staff.store'), [
        'name' => 'New Cashier',
        'email' => 'new@example.com',
        'role' => 'cashier',
        'password' => 'secret-pass-123',
        'password_confirmation' => 'secret-pass-123',
    ])->assertRedirect(route('admin.staff.index'));

    $user = User::query()->where('email', 'new@example.com')->sole();
    expect($user->role)->toBe(UserRole::Cashier)->and($user->canteen_id)->toBe($this->owner->canteen_id);

    $this->post(route('logout'));

    $this->post(route('login'), [
        'email' => 'new@example.com',
        'password' => 'secret-pass-123',
    ])->assertRedirect(route('dashboard'));
});

test('an owner can create a runner who lands on the runner screen', function () {
    $this->post(route('admin.staff.store'), [
        'name' => 'New Runner', 'email' => 'runner@example.com', 'role' => 'runner',
        'password' => 'secret-pass-123', 'password_confirmation' => 'secret-pass-123',
    ])->assertRedirect(route('admin.staff.index'));

    $this->post(route('logout'));

    $this->post(route('login'), ['email' => 'runner@example.com', 'password' => 'secret-pass-123'])
        ->assertRedirect(route('runner.dashboard'));
});

test('an owner cannot hand out the admin, owner or customer roles', function (string $role) {
    $this->post(route('admin.staff.store'), [
        'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'role' => $role,
        'password' => 'secret-pass-123', 'password_confirmation' => 'secret-pass-123',
    ])->assertSessionHasErrors('role');

    $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
})->with(['admin', 'owner', 'customer']);

test('an owner cannot promote a cashier to admin', function () {
    $cashier = User::factory()->cashier()->create();

    $this->put(route('admin.staff.update', $cashier), [
        'name' => 'Promoted', 'email' => $cashier->email, 'role' => 'admin',
    ])->assertSessionHasErrors('role');

    expect($cashier->refresh()->role)->toBe(UserRole::Cashier);
});

test('an owner cannot touch staff of another canteen or other owners', function () {
    $stranger = User::factory()->cashier()->forCanteen(Canteen::factory()->create())->create();
    $colleague = User::factory()->owner()->forCanteen(Canteen::default())->create();

    $this->get(route('admin.staff.edit', $stranger))->assertNotFound();
    $this->delete(route('admin.staff.destroy', $stranger))->assertNotFound();
    $this->get(route('admin.staff.edit', $colleague))->assertForbidden();
});

test('a new account needs a password and a unique email', function () {
    $this->post(route('admin.staff.store'), [
        'name' => 'No Password',
        'email' => $this->owner->email,
        'role' => 'cashier',
    ])->assertSessionHasErrors(['email', 'password']);
});

test('an owner can change a role and reset a password', function () {
    $cashier = User::factory()->cashier()->create();

    $this->put(route('admin.staff.update', $cashier), [
        'name' => 'Now Runner',
        'email' => $cashier->email,
        'role' => 'runner',
        'password' => 'brand-new-pass-1',
        'password_confirmation' => 'brand-new-pass-1',
    ])->assertRedirect(route('admin.staff.index'));

    $cashier->refresh();

    expect($cashier->name)->toBe('Now Runner')
        ->and($cashier->role)->toBe(UserRole::Runner)
        ->and(Hash::check('brand-new-pass-1', $cashier->password))->toBeTrue();
});

test('leaving the password empty keeps the current one', function () {
    $cashier = User::factory()->cashier()->create();
    $before = $cashier->password;

    $this->put(route('admin.staff.update', $cashier), [
        'name' => 'Renamed',
        'email' => $cashier->email,
        'role' => 'cashier',
        'password' => '',
        'password_confirmation' => '',
    ])->assertRedirect(route('admin.staff.index'));

    expect($cashier->refresh()->password)->toBe($before);
});

test('an owner can deactivate and reactivate staff but not themselves', function () {
    $cashier = User::factory()->cashier()->create();

    $this->patch(route('admin.staff.active', $cashier))->assertRedirect();
    expect($cashier->refresh()->is_active)->toBeFalse();

    $this->patch(route('admin.staff.active', $cashier))->assertRedirect();
    expect($cashier->refresh()->is_active)->toBeTrue();

    $this->patch(route('admin.staff.active', $this->owner))->assertSessionHasErrors('staff');
    expect($this->owner->refresh()->is_active)->toBeTrue();
});

test('an owner cannot delete their own account but can delete staff', function () {
    $cashier = User::factory()->cashier()->create();

    $this->delete(route('admin.staff.destroy', $this->owner))->assertSessionHasErrors('staff');
    $this->assertModelExists($this->owner);

    $this->delete(route('admin.staff.destroy', $cashier))->assertRedirect();
    $this->assertModelMissing($cashier);
});

test('an administrator can appoint the owner of a canteen from the staff page', function () {
    $canteen = Canteen::factory()->create();
    $this->actingAs(User::factory()->superAdmin()->create())
        ->withSession(['staff.canteen_id' => $canteen->id]);

    $this->post(route('admin.staff.store'), [
        'name' => 'Head Of Canteen', 'email' => 'head@example.com', 'role' => 'owner',
        'password' => 'secret-pass-123', 'password_confirmation' => 'secret-pass-123',
    ])->assertRedirect(route('admin.staff.index'));

    $head = User::query()->where('email', 'head@example.com')->sole();

    expect($head->role)->toBe(UserRole::Owner)->and($head->canteen_id)->toBe($canteen->id);
});
