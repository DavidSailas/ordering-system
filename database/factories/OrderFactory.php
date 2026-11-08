<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Models\Canteen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'dining_table_id' => null,
            'customer_name' => fake()->name(),
            'type' => OrderType::DineIn,
            'status' => OrderStatus::InProgress,
            'notes' => null,
            'total' => 0,
            'canteen_id' => fn () => Canteen::default()->id,
        ];
    }

    /**
     * Indicate that the order is for take away.
     */
    public function takeAway(): static
    {
        return $this->state(fn (array $attributes) => [
            'dining_table_id' => null,
            'type' => OrderType::TakeAway,
        ]);
    }

    /**
     * Indicate that the order is for delivery.
     */
    public function delivery(): static
    {
        return $this->state(fn (array $attributes) => [
            'dining_table_id' => null,
            'type' => OrderType::Delivery,
        ]);
    }

    /**
     * Set the order status.
     */
    public function status(OrderStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    /**
     * Attach random menu items to the order and compute its total.
     */
    public function withItems(int $count = 3): static
    {
        return $this->afterCreating(function (Order $order) use ($count): void {
            $menuItems = MenuItem::query()->inRandomOrder()->limit($count)->get();

            if ($menuItems->isEmpty()) {
                $menuItems = MenuItem::factory()->count($count)->create();
            }

            $total = 0;

            foreach ($menuItems as $menuItem) {
                $quantity = fake()->numberBetween(1, 3);

                $order->items()->create([
                    'menu_item_id' => $menuItem->id,
                    'name' => $menuItem->name,
                    'price' => $menuItem->price,
                    'quantity' => $quantity,
                ]);

                $total += (float) $menuItem->price * $quantity;
            }

            $order->update(['total' => $total]);
        });
    }
}
