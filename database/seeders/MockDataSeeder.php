<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\UserRole;
use App\Models\Canteen;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo data for presentations: TWO canteens with their real price lists (see CanteenMenuSeeder), owners,
 * cashiers, colours and payment accounts, plus about a week of orders each
 * (and a live queue for "today" so the cashier boards are full).
 *
 *   php artisan db:seed --class=MockDataSeeder
 *
 * WARNING: this REPLACES all canteens, categories, dishes and orders.
 * Staff accounts are kept (and re-assigned). Never run it on real data.
 *
 * Logins (password: "password"):
 *   admin@example.com            overall administrator (sees both canteens)
 *   owner.kitchen@example.com    owner of Cecilian Café
 *   cashier@example.com          cashier of Cecilian Café
 *   owner.brew@example.com       owner of Cecilian Eatery
 *   cashier.brew@example.com     cashier of Cecilian Eatery
 */
class MockDataSeeder extends Seeder
{
    /** @return array<int, array<string, mixed>> */
    private function canteens(): array
    {
        return [
            [
                'name' => 'Cecilian Café',
                'slug' => 'cecilian-cafe',
                'tagline' => 'Milk tea, iced coffee, snacks and drinks.',
                'accent' => '#e25806',
                'address' => 'Ground Floor, Main Building',
                'phone' => '0917 123 4567',
                'gcash' => ['0917 123 4567', 'Cecilian Cafe'],
                'maya' => ['0928 765 4321', 'Cecilian Cafe'],
                'owner' => ['owner.kitchen@example.com', 'Rosa Cecilio'],
                'cashier' => ['cashier@example.com', 'Carlo Mendoza'],
            ],
            [
                'name' => 'Cecilian Eatery',
                'slug' => 'cecilian-eatery',
                'tagline' => 'Rice meals, snacks, drinks and everyday essentials.',
                'accent' => '#d62d1a',
                'address' => '2nd Floor, Science Hall',
                'phone' => '0935 555 0147',
                'gcash' => ['0935 555 0147', 'Cecilian Eatery'],
                'maya' => ['0961 222 8830', 'Cecilian Eatery'],
                'owner' => ['owner.brew@example.com', 'Marco Villareal'],
                'cashier' => ['cashier.brew@example.com', 'Lea Ocampo'],
            ],
        ];
    }

    /** @var list<string> */
    private array $names = [
        'Maria Santos', 'Juan Dela Cruz', 'Angela Reyes', 'Mark Villanueva', 'Carla Mendoza', 'Paolo Garcia',
        'Bianca Torres', 'Rafael Ramos', 'Katrina Flores', 'Miguel Aquino', 'Sofia Bautista', 'Daniel Cruz',
        'Janelle Navarro', 'Lorenzo Castillo', 'Patricia Domingo', 'Gabriel Lopez', 'Isabel Fernandez', 'Nathan Salazar',
    ];

    /** @var list<string> */
    private array $buildings = ['Main Building', 'Science Hall', 'Library', 'Engineering Building', 'Administration Building'];

    public function run(): void
    {
        mt_srand(2026);

        $this->reset();

        $seeded = [];

        foreach ($this->canteens() as $index => $definition) {
            $canteen = $this->seedCanteen($definition, $index);
            $items = $this->seedMenu($canteen, $definition + ['menu' => CanteenMenuSeeder::menus()[$index]]);
            $cashier = $this->seedStaff($canteen, $definition);
            $this->seedHistory($canteen, $items, $cashier);
            $this->seedToday($canteen, $items, $cashier);
            $seeded[] = $canteen->name;
        }

        $this->seedOverallAdmin();

        $this->command?->info('Mock data ready for: '.implode(' and ', $seeded).'. '.MenuItem::withoutGlobalScopes()->count().' dishes, '.Order::withoutGlobalScopes()->count().' orders.');
    }

    private function reset(): void
    {
        DB::table('order_items')->delete();
        DB::table('orders')->delete();
        DB::table('menu_items')->delete();
        DB::table('categories')->delete();
        DB::table('canteens')->delete();

        Storage::disk('public')->deleteDirectory('menu');
        Storage::disk('public')->makeDirectory('menu');
    }

    /** @param  array<string, mixed>  $d */
    private function seedCanteen(array $d, int $index): Canteen
    {
        return Canteen::create([
            'name' => $d['name'],
            'slug' => $d['slug'],
            'tagline' => $d['tagline'],
            'accent' => $d['accent'],
            'is_open' => true,
            'phone' => $d['phone'],
            'address' => $d['address'],
            'receipt_footer' => 'Salamat! Enjoy your meal.',
            'buildings' => implode("\n", $this->buildings),
            'gcash_number' => $d['gcash'][0],
            'gcash_name' => $d['gcash'][1],
            'maya_number' => $d['maya'][0],
            'maya_name' => $d['maya'][1],
            'sort_order' => $index,
        ]);
    }

    /**
     * @param  array<string, mixed>  $d
     * @return Collection<int, MenuItem>
     */
    private function seedMenu(Canteen $canteen, array $d): Collection
    {
        $sort = 0;

        foreach ($d['menu'] as $categoryName => $dishes) {
            $category = Category::create([
                'canteen_id' => $canteen->id,
                'name' => $categoryName,
                'slug' => Str::slug($categoryName),
                'sort_order' => $sort++,
            ]);

            foreach ($dishes as $dish) {
                $name = $dish[0];
                $price = $dish[1];
                $description = $dish[2] ?? null;
                $imagePath = null;
                $source = database_path('seeders/images/'.Str::slug($name).'.webp');

                if (File::exists($source)) {
                    $imagePath = 'menu/'.$canteen->slug.'-'.Str::slug($name).'.webp';
                    Storage::disk('public')->put($imagePath, File::get($source));
                }

                MenuItem::create([
                    'canteen_id' => $canteen->id,
                    'category_id' => $category->id,
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'image_path' => $imagePath,
                    'is_available' => true,
                ]);
            }
        }

        return MenuItem::withoutGlobalScopes()->where('canteen_id', $canteen->id)->where('is_available', true)->get();
    }

    /** @param  array<string, mixed>  $d */
    private function seedStaff(Canteen $canteen, array $d): User
    {
        User::query()->updateOrCreate(
            ['email' => $d['owner'][0]],
            ['name' => $d['owner'][1], 'password' => 'password', 'role' => UserRole::Owner, 'canteen_id' => $canteen->id],
        );

        User::query()->updateOrCreate(
            ['email' => 'runner.'.Str::slug($canteen->name).'@example.com'],
            ['name' => 'Runner · '.$canteen->name, 'password' => 'password', 'role' => UserRole::Runner, 'canteen_id' => $canteen->id],
        );

        return User::query()->updateOrCreate(
            ['email' => $d['cashier'][0]],
            ['name' => $d['cashier'][1], 'password' => 'password', 'role' => UserRole::Cashier, 'canteen_id' => $canteen->id],
        );
    }

    private function seedOverallAdmin(): void
    {
        // any older staff account that now points at a deleted canteen goes to the first canteen
        $first = Canteen::query()->orderBy('id')->first();

        User::query()
            ->whereIn('role', [UserRole::Admin->value, UserRole::Owner->value, UserRole::Cashier->value, UserRole::Runner->value])
            ->whereNotIn('canteen_id', Canteen::query()->pluck('id'))
            ->update(['canteen_id' => $first?->id]);

        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Overall Admin', 'password' => 'password', 'role' => UserRole::Admin, 'canteen_id' => null],
        );

        User::query()->where('email', 'admin@example.com')->update(['canteen_id' => null]);
    }

    /** Six past days of finished orders, so reports and charts have a trend. */
    private function seedHistory(Canteen $canteen, Collection $items, User $cashier): void
    {
        for ($daysAgo = 6; $daysAgo >= 1; $daysAgo--) {
            $count = mt_rand(4, 9);

            for ($i = 0; $i < $count; $i++) {
                $when = Carbon::now()->subDays($daysAgo)->setTime(mt_rand(8, 17), mt_rand(0, 59));
                $canceled = mt_rand(1, 14) === 1;

                $this->makeOrder($canteen, $items, $cashier, [
                    'status' => $canceled ? OrderStatus::Canceled : OrderStatus::Completed,
                    'paid' => ! $canceled,
                    'method' => $this->pick(['cash', 'cash', 'cash', 'gcash', 'maya']),
                ], $when);
            }
        }
    }

    /** A live queue for today. */
    private function seedToday(Canteen $canteen, Collection $items, User $cashier): void
    {
        $now = Carbon::now();

        $queue = [
            ['status' => OrderStatus::InProgress, 'paid' => false, 'method' => 'cash', 'ago' => 2, 'notes' => 'No onions please.'],
            ['status' => OrderStatus::InProgress, 'paid' => false, 'method' => 'gcash', 'ago' => 6],
            ['status' => OrderStatus::InProgress, 'paid' => false, 'method' => 'maya', 'ago' => 9, 'reference' => 'MY'.mt_rand(100000, 999999)],
            ['status' => OrderStatus::InProgress, 'paid' => true, 'method' => 'gcash', 'ago' => 14],
            ['status' => OrderStatus::ReadyToServe, 'paid' => false, 'method' => 'cash', 'ago' => 22],
            ['status' => OrderStatus::ReadyToServe, 'paid' => true, 'method' => 'gcash', 'ago' => 30],
            ['status' => OrderStatus::Completed, 'paid' => true, 'method' => 'cash', 'ago' => 55],
            ['status' => OrderStatus::Completed, 'paid' => true, 'method' => 'cash', 'ago' => 80],
            ['status' => OrderStatus::Completed, 'paid' => true, 'method' => 'maya', 'ago' => 110],
            ['status' => OrderStatus::Canceled, 'paid' => false, 'method' => 'cash', 'ago' => 120],
        ];

        foreach ($queue as $row) {
            $this->makeOrder($canteen, $items, $cashier, $row, $now->copy()->subMinutes($row['ago']));
        }
    }

    /**
     * @param  array{status: OrderStatus, paid: bool, method: string, notes?: string, reference?: string}  $spec
     */
    private function makeOrder(Canteen $canteen, Collection $items, User $cashier, array $spec, Carbon $createdAt): Order
    {
        $lines = $items->shuffle()->take(mt_rand(1, 4));

        $order = Order::create([
            'canteen_id' => $canteen->id,
            'customer_name' => $this->pick($this->names),
            'phone' => '09'.mt_rand(10, 99).mt_rand(1000000, 9999999),
            'building' => $this->pick($this->buildings),
            'room' => (string) (mt_rand(1, 4) * 100 + mt_rand(1, 20)),
            'type' => OrderType::TakeAway,
            'status' => $spec['status'],
            'notes' => $spec['notes'] ?? null,
            'payment_choice' => $spec['method'],
            'total' => 0,
        ]);

        $total = 0.0;

        foreach ($lines as $item) {
            $quantity = mt_rand(1, 3);

            OrderItem::create([
                'order_id' => $order->id,
                'menu_item_id' => $item->id,
                'name' => $item->name,
                'price' => $item->price,
                'quantity' => $quantity,
            ]);

            $total += (float) $item->price * $quantity;
        }

        $update = ['total' => $total];

        if (isset($spec['reference'])) {
            $update['customer_payment_ref'] = $spec['reference'];
            $update['payment_submitted_at'] = $createdAt->copy()->addMinutes(2);
        }

        if ($spec['paid']) {
            $discount = mt_rand(1, 8) === 1 ? round($total * 0.1, 2) : 0.0;
            $due = round($total - $discount, 2);

            $update += [
                'paid_at' => $createdAt->copy()->addMinutes(mt_rand(3, 12)),
                'paid_by' => $cashier->id,
                'payment_method' => $spec['method'],
                'discount' => $discount,
                'amount_paid' => $due,
                'cash_received' => $spec['method'] === 'cash' ? (ceil($due / 50) * 50) : null,
                'payment_reference' => $spec['method'] === 'cash' ? null : strtoupper(substr($spec['method'], 0, 2)).mt_rand(100000, 999999),
            ];
        }

        if ($spec['status'] === OrderStatus::Canceled) {
            $update['canceled_at'] = $createdAt->copy()->addMinutes(5);
        }

        $order->update($update);

        // back-date the row so charts and "x minutes ago" make sense
        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt->copy()->addMinutes(15)])->saveQuietly();

        return $order;
    }

    /** @param  list<string>  $values */
    private function pick(array $values): string
    {
        return (string) $values[mt_rand(0, count($values) - 1)];
    }
}
