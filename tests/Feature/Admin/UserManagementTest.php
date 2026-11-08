<?php

use App\Enums\UserRole;
use App\Models\Canteen;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create();
    $this->actingAs($this->admin);
});

test('the users page lists every role and can filter by role', function () {
    User::factory()->runner()->create(['name' => 'Rico Runner']);
    User::factory()->customer()->create(['name' => 'Stella Student']);

    $this->get(route('admin.users.index'))->assertOk()->assertSee('Rico Runner')->assertSee('Stella Student');

    $this->get(route('admin.users.index', ['role' => 'runner']))
        ->assertOk()
        ->assertSee('Rico Runner')
        ->assertDontSee('Stella Student');
});

test('an admin can create an owner for a canteen', function () {
    $canteen = Canteen::default();

    $this->post(route('admin.users.store'), [
        'name' => 'New Owner', 'email' => 'owner@example.com', 'role' => 'owner', 'canteen_id' => $canteen->id,
        'password' => 'secret-pass-123', 'password_confirmation' => 'secret-pass-123',
    ])->assertRedirect(route('admin.users.index'));

    $owner = User::query()->where('email', 'owner@example.com')->sole();

    expect($owner->role)->toBe(UserRole::Owner)->and($owner->canteen_id)->toBe($canteen->id);
});

test('owners, cashiers and runners need a canteen but admins and customers get none', function () {
    $this->post(route('admin.users.store'), [
        'name' => 'No Canteen', 'email' => 'nc@example.com', 'role' => 'runner',
        'password' => 'secret-pass-123', 'password_confirmation' => 'secret-pass-123',
    ])->assertSessionHasErrors('canteen_id');

    $this->post(route('admin.users.store'), [
        'name' => 'Second Admin', 'email' => 'second@example.com', 'role' => 'admin', 'canteen_id' => Canteen::default()->id,
        'password' => 'secret-pass-123', 'password_confirmation' => 'secret-pass-123',
    ])->assertRedirect();

    expect(User::query()->where('email', 'second@example.com')->sole()->canteen_id)->toBeNull();
});

test('a canteen can only have one active owner', function () {
    $canteen = Canteen::default();
    User::factory()->owner()->forCanteen($canteen)->create();

    $this->post(route('admin.users.store'), [
        'name' => 'Second Owner', 'email' => 'second.owner@example.com', 'role' => 'owner', 'canteen_id' => $canteen->id,
        'password' => 'secret-pass-123', 'password_confirmation' => 'secret-pass-123',
    ])->assertSessionHasErrors('role');

    $this->assertDatabaseMissing('users', ['email' => 'second.owner@example.com']);
});

test('an admin can change a role and reset a password', function () {
    $cashier = User::factory()->cashier()->create();

    $this->put(route('admin.users.update', $cashier), [
        'name' => 'Now A Runner', 'email' => $cashier->email, 'role' => 'runner', 'canteen_id' => $cashier->canteen_id,
        'password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1',
    ])->assertRedirect(route('admin.users.index'));

    expect($cashier->refresh()->role)->toBe(UserRole::Runner)->and($cashier->name)->toBe('Now A Runner');
});

test('an admin cannot change their own role or deactivate themselves', function () {
    $this->put(route('admin.users.update', $this->admin), [
        'name' => $this->admin->name, 'email' => $this->admin->email, 'role' => 'cashier', 'canteen_id' => Canteen::default()->id,
    ])->assertSessionHasErrors('role');

    $this->patch(route('admin.users.active', $this->admin))->assertSessionHasErrors('user');

    expect($this->admin->refresh()->role)->toBe(UserRole::Admin)->and($this->admin->is_active)->toBeTrue();
});

test('the last active administrator cannot be demoted or deactivated', function () {
    $second = User::factory()->superAdmin()->create();
    $this->admin->update(['is_active' => false]);
    $this->actingAs($second);

    $this->patch(route('admin.users.active', $this->admin))->assertRedirect();
    expect($this->admin->refresh()->is_active)->toBeTrue();

    $this->admin->update(['is_active' => false]);
    $this->put(route('admin.users.update', $second), [
        'name' => $second->name, 'email' => $second->email, 'role' => 'cashier', 'canteen_id' => Canteen::default()->id,
    ])->assertSessionHasErrors('role');
});

test('a deactivated account cannot log in and an active session is signed out', function () {
    $runner = User::factory()->runner()->create();

    $this->patch(route('admin.users.active', $runner))->assertRedirect();
    expect($runner->refresh()->is_active)->toBeFalse();

    $this->post(route('logout'));
    $this->post(route('login'), ['email' => $runner->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();

    $runner->update(['is_active' => true]);
    $this->actingAs($runner)->get(route('runner.dashboard'))->assertOk();

    $runner->update(['is_active' => false]);
    $this->get(route('runner.dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});
