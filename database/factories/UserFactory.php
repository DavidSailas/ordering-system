<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Canteen;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Cashier,
            'is_active' => true,
            'canteen_id' => fn () => Canteen::default()->id,
        ];
    }

    /**
     * Indicate that the user is an administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    /**
     * The overall administrator: an admin who belongs to no single canteen.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
            'canteen_id' => null,
        ]);
    }

    /**
     * A staff member of one specific canteen.
     */
    public function forCanteen(Canteen $canteen): static
    {
        return $this->state(fn (array $attributes) => ['canteen_id' => $canteen->id]);
    }

    /**
     * The head of one canteen.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Owner,
        ]);
    }

    /**
     * A runner who delivers orders for one canteen.
     */
    public function runner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Runner,
        ]);
    }

    /**
     * A student who orders food (belongs to no canteen).
     */
    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Customer,
            'canteen_id' => null,
        ]);
    }

    /**
     * An account that was switched off.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the user is a cashier.
     */
    public function cashier(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Cashier,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
