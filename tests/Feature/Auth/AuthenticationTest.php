<?php

use App\Enums\UserRole;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('the login and register screens render', function () {
    $this->get(route('login'))->assertOk()->assertSee('Welcome back')->assertSee('Email address')->assertSee('Password');
    $this->get(route('register'))->assertOk()->assertSee('Create your account')->assertSee('Confirm password');
});

test('cashiers log in and land on the cashier dashboard', function () {
    $user = User::factory()->cashier()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('admins log in and land on the admin dashboard', function () {
    $user = User::factory()->admin()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('customers log in and land on the storefront', function () {
    $user = User::factory()->create(['role' => UserRole::Customer]);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('users cannot log in with a wrong password', function () {
    $user = User::factory()->create();

    $this->from(route('login'))->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('logging in requires an email and a password', function () {
    $this->post(route('login'), [])->assertSessionHasErrors(['email', 'password']);

    $this->assertGuest();
});

test('registering creates a customer account and signs the customer in', function () {
    $this->post(route('register'), [
        'name' => 'Maria Cruz',
        'email' => 'maria@example.com',
        'password' => 'secret-pass-123',
        'password_confirmation' => 'secret-pass-123',
    ])->assertRedirect(route('home'));

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'maria@example.com', 'name' => 'Maria Cruz', 'role' => 'customer']);
});

test('registering can never create a staff or admin account', function () {
    $this->post(route('register'), [
        'role' => 'admin',
        'admin_code' => 'anything',
        'name' => 'Sneaky',
        'email' => 'sneaky@example.com',
        'password' => 'secret-pass-123',
        'password_confirmation' => 'secret-pass-123',
    ]);

    expect(User::query()->where('email', 'sneaky@example.com')->sole()->role)->toBe(UserRole::Customer);
});

test('registration requires a matching password confirmation and a unique email', function () {
    $existing = User::factory()->create();

    $response = $this->from(route('register'))->post(route('register'), [
        'name' => 'Maria Cruz',
        'email' => $existing->email,
        'password' => 'secret-pass-123',
        'password_confirmation' => 'something-else',
    ]);

    $response->assertSessionHasErrors(['email', 'password']);
    $this->assertGuest();
});

test('authenticated users are redirected away from the login screen', function () {
    $this->actingAs(User::factory()->cashier()->create())
        ->get(route('login'))
        ->assertRedirect(route('dashboard'));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('login'))
        ->assertRedirect(route('admin.dashboard'));
});

test('users can log out', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
