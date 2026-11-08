<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Canteen;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => Str::title(fake()->unique()->words(2, true)),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 3, 30),
            'image_path' => null,
            'is_available' => true,
            'canteen_id' => fn () => Canteen::default()->id,
        ];
    }

    /**
     * Indicate that the item can no longer be ordered.
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }
}
