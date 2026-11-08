<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Canteen;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'sort_order' => fake()->numberBetween(0, 20),
            'canteen_id' => fn () => Canteen::default()->id,
        ];
    }
}
