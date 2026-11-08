<?php

namespace Database\Factories;

use App\Models\Canteen;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Canteen>
 */
class CanteenFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true)).' Canteen';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'accent' => '#e25806',
            'is_open' => true,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['is_open' => false]);
    }
}
