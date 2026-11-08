<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Seed a handful of demo orders so the order line is not empty.
     */
    public function run(): void
    {
        $user = User::query()->where('role', UserRole::Cashier->value)->first();
        $statuses = [
            OrderStatus::ReadyToServe,
            OrderStatus::InProgress,
            OrderStatus::Canceled,
            OrderStatus::Completed,
        ];

        foreach ($statuses as $index => $status) {
            Order::factory()
                ->status($status)
                ->withItems()
                ->create([
                    'user_id' => $user?->id,
                    'phone' => '0917123456'.$index,
                    'building' => 'Main Building',
                    'room' => (string) (101 + $index),
                ]);
        }

        Order::factory()->takeAway()->withItems(2)->create(['user_id' => $user?->id]);
        Order::factory()->delivery()->withItems(4)->create(['user_id' => $user?->id]);
    }
}
