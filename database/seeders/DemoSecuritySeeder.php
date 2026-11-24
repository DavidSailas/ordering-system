<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Canteen;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStrike;
use App\Models\PhoneSuspension;
use App\Models\SchoolMember;
use App\Support\PhoneKey;
use Illuminate\Database\Seeder;

/**
 * DEMO ONLY. Gives the sample school list something to show for the security features:
 *
 *   2024-0002  Maria Santos    has 2 missed orders and an order waiting for payment, so the cashier sees the warning
 *   2024-0003  Carla Mendoza   is paused for 3 days (try ordering with her ID, then lift the pause as administrator)
 *   2024-0001  Juan Dela Cruz  is clean: use him to place a normal order
 *
 * Runs after SchoolMemberSeeder and is safe to run again.
 */
class DemoSecuritySeeder extends Seeder
{
    public function run(): void
    {
        $canteen = Canteen::query()->orderBy('sort_order')->first();
        $maria = SchoolMember::query()->where('id_key', SchoolMember::keyFor('2024-0002'))->first();
        $carla = SchoolMember::query()->where('id_key', SchoolMember::keyFor('2024-0003'))->first();
        $item = $canteen ? MenuItem::withoutGlobalScopes()->where('canteen_id', $canteen->id)->orderBy('id')->first() : null;

        if (! $canteen || ! $maria || ! $carla || ! $item) {
            return;
        }

        OrderStrike::query()->whereIn('school_member_id', [$maria->id, $carla->id])->delete();
        PhoneSuspension::query()->whereIn('school_member_id', [$maria->id, $carla->id])->delete();

        foreach ([[$maria, 2], [$carla, 3]] as [$member, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $when = now()->subDays($count - $i + 1);

                OrderStrike::forceCreate([
                    'phone_key' => '', 'phone' => '(earlier order)', 'school_member_id' => $member->id,
                    'reason' => 'An earlier order was not paid in time', 'created_at' => $when, 'updated_at' => $when,
                ]);
            }
        }

        PhoneSuspension::create([
            'phone_key' => '', 'phone' => $carla->id_number, 'school_member_id' => $carla->id,
            'reason' => '3 orders were not paid in time', 'strikes' => 3, 'starts_at' => now()->subHour(), 'ends_at' => now()->addDays(3),
        ]);

        $phone = '0917 100 0002';
        $order = Order::create([
            'canteen_id' => $canteen->id, 'school_member_id' => $maria->id, 'customer_name' => $maria->name,
            'phone' => $phone, 'phone_key' => PhoneKey::from($phone), 'type' => OrderType::TakeAway,
            'status' => OrderStatus::AwaitingPayment, 'payment_choice' => 'cash', 'total' => $item->price,
        ]);
        OrderItem::create(['order_id' => $order->id, 'menu_item_id' => $item->id, 'name' => $item->name, 'price' => $item->price, 'quantity' => 1]);
    }
}
