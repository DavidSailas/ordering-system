<?php

namespace Database\Factories;

use App\Models\Canteen;
use App\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DeliveryZone> */
class DeliveryZoneFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'canteen_id' => fn () => Canteen::default()->id,
            'name' => fake()->unique()->words(2, true),
            'floors' => 6,
            'base_fee' => 10,
            'per_floor_fee' => 2,
            'is_home' => false,
            'home_floor' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /** The building the canteen is in, on the given floor. */
    public function home(int $floor = 6): static
    {
        return $this->state(fn () => ['is_home' => true, 'home_floor' => $floor]);
    }
}
