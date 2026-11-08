<?php

namespace Database\Seeders;

use App\Models\Canteen;
use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The real price lists of the two canteens.
 *
 *   php artisan db:seed --class=CanteenMenuSeeder
 *
 * Replaces ONLY the categories and dishes of the first two canteens (canteen 1
 * and canteen 2, in sort order). Orders, order history, staff accounts, logos
 * and payment settings are not touched.
 */
class CanteenMenuSeeder extends Seeder
{
    /**
     * Menus by canteen position: [0] = first canteen, [1] = second canteen.
     * Each dish is [name, price, optional description].
     *
     * @return array<int, array<string, array<int, array{0: string, 1: int, 2?: string}>>>
     */
    public static function menus(): array
    {
        $withRice = 'Served with rice.';

        return [
            // ---------------------------------------------------------- Canteen 1
            0 => [
                'Foods' => [
                    ['Tortang Talong', 25],
                    ['Fried Egg', 20],
                    ['Boiled Egg', 20],
                    ['Ginamay', 35],
                    ['Ginamay with Rice', 50, $withRice],
                    ['Steamed Rice', 35],
                    ['Ginaling', 35],
                    ['Ginaling with Rice', 50, $withRice],
                    ['Fried Chicken', 40],
                    ['Fried Chicken with Rice', 60, $withRice],
                    ['Sisig', 40],
                    ['Sisig with Rice', 70, $withRice],
                    ['Cup Noodles Spicy Bulalo', 25],
                    ['Cup Noodles Jampong', 35],
                    ['Bulalo', 30],
                    ['Ampalaya with Egg', 25],
                    ['Lumpia', 20, '3 pieces.'],
                    ['Siomai', 10, 'Price per piece.'],
                    ['Batong', 25],
                    ['Talong', 25],
                    ['Munchkins', 10, 'Price per piece.'],
                    ['New York Bread', 20],
                    ['Biko', 15],
                    ['Kalamay', 15],
                ],
                'Milk Tea' => [
                    ['Matcha Milk Tea', 50],
                    ['Choco Milk Tea', 50],
                    ['Red Velvet Milk Tea', 50],
                    ['Cookies & Cream Milk Tea', 50],
                    ['Black Forest Milk Tea', 50],
                    ['Taro Milk Tea', 50],
                ],
                'Iced Coffee' => [
                    ['Caramel Iced Coffee', 40],
                    ['Mocha Iced Coffee', 40],
                    ['Matcha Coffee', 50],
                ],
                'Drinks' => [
                    ['Tapioca Chocho', 35],
                    ['Tapioca Dalandan', 35],
                    ['Buko Juice', 30],
                    ['Water', 15],
                    ['Minute Maid', 15],
                    ['Yakult', 15],
                    ['C2', 20],
                    ['OK Fruit Drink', 15],
                ],
                'Biscuits & Snacks' => [
                    ['Presto', 10],
                    ['Skyflakes', 10],
                    ['Hansel', 10],
                    ['Fita', 10],
                    ['Fudge Bar', 15],
                    ['Taro Chips', 60],
                    ['Chicharon', 20],
                    ['Nuts', 10],
                    ['Watello', 20],
                    ['Latiao', 20],
                    ['Cal Cheese', 20],
                    ['Peanut', 10],
                    ['Banana Chips', 20],
                    ['Cream-O', 10],
                    ['Oreo', 10],
                ],
                'Ice Cream' => [
                    ['Ice Cream (Small)', 20, 'Price depends on the size.'],
                    ['Ice Cream (Large)', 30, 'Price depends on the size.'],
                ],
            ],

            // ---------------------------------------------------------- Canteen 2
            1 => [
                'Rice Meals' => [
                    ['Menudo', 45],
                    ['Menudo with Rice', 60, $withRice],
                    ['Chicken Pastil', 45],
                    ['Chicken Pastil with Rice', 60, $withRice],
                    ['Ginaling', 45],
                    ['Ginaling with Rice', 60, $withRice],
                    ['Burger Steak', 35],
                    ['Burger Steak with Rice', 50, $withRice],
                    ['Egg Soup', 25],
                    ['Egg Soup with Rice', 30, $withRice],
                    ['Hotdog', 25],
                    ['Hotdog with Rice', 30, $withRice],
                    ['Atay', 25],
                    ['Atay with Rice', 30, $withRice],
                    ['Gisado', 60],
                    ['Gisado with Rice', 75, $withRice],
                    ['Pansit', 25],
                    ['Pansit with Rice', 40, $withRice],
                    ['Pinakbet', 25],
                    ['Pinakbet with Rice', 40, $withRice],
                    ['Longganisa', 20, '3 pieces.'],
                    ['Longganisa with Rice', 35, '3 pieces, served with rice.'],
                ],
                'Snacks & Noodles' => [
                    ['Champorado', 15],
                    ['Pancake', 10],
                    ['Fries', 20],
                    ['Nuts', 10],
                    ['Banana Turon', 15],
                    ['Footlong', 30],
                    ['Siopao', 30],
                    ['Chicharon', 15],
                    ['Cookies', 30],
                    ['Macaroni', 30],
                    ['Cup Noodles Jumbo', 38],
                ],
                'Drinks' => [
                    ['Water', 15],
                    ['Minute Maid', 15],
                    ['Delight', 16],
                    ['Chuckie', 13],
                    ['Smart C', 20],
                    ['Yakult', 15],
                    ['Tapioca', 40],
                    ['C2', 20],
                    ['Lucky Day', 35],
                    ['Calamansi Juice', 30],
                    ['Nutriboost', 25],
                ],
                'Coffee' => [
                    ['Milo', 15],
                    ['Bear Brand', 20],
                    ['Nescafé Original', 15],
                    ['Kopiko Brown', 15],
                ],
                'Biscuits' => [
                    ['Skyflakes', 15],
                    ['Fudge Bar', 15],
                    ['Fita', 15],
                    ['Richoco', 20],
                    ['Presto', 15],
                    ['Dewberry', 15],
                    ['Oreo', 15],
                    ['Cream-O', 15],
                    ['Pillows', 15],
                    ['Choco Mucho', 15],
                    ['Cloud 9', 15],
                    ['Caramel Popcorn', 15],
                    ['Banana Chips', 15],
                ],
                'Ice Cream' => [
                    ['Cornetto', 30],
                    ['Ice Cream Cup', 30],
                    ['Graham Bar', 30],
                ],
                'Body Care' => [
                    ['Wipes', 25],
                    ['Tissue', 15],
                    ['Sister Napkin', 10],
                ],
            ],
        ];
    }

    public function run(): void
    {
        $canteens = Canteen::query()->orderBy('sort_order')->orderBy('id')->limit(2)->get();

        if ($canteens->isEmpty()) {
            $this->command?->warn('No canteens found. Run the migrations first.');

            return;
        }

        foreach ($canteens as $index => $canteen) {
            $count = $this->replaceMenu($canteen, self::menus()[$index]);
            $this->command?->info("{$canteen->name}: {$count} dishes loaded.");
        }
    }

    /**
     * @param  array<string, array<int, array{0: string, 1: int, 2?: string}>>  $menu
     */
    public function replaceMenu(Canteen $canteen, array $menu): int
    {
        return DB::transaction(function () use ($canteen, $menu): int {
            $oldImages = MenuItem::withoutGlobalScopes()
                ->where('canteen_id', $canteen->id)
                ->whereNotNull('image_path')
                ->pluck('image_path');

            // Past orders keep their own copy of the dish name and price (menu_item_id becomes null).
            MenuItem::withoutGlobalScopes()->where('canteen_id', $canteen->id)->delete();
            Category::withoutGlobalScopes()->where('canteen_id', $canteen->id)->delete();

            foreach ($oldImages as $path) {
                Storage::disk('public')->delete($path);
            }

            $count = 0;
            $sort = 0;

            foreach ($menu as $categoryName => $dishes) {
                $category = Category::create([
                    'canteen_id' => $canteen->id,
                    'name' => $categoryName,
                    'slug' => Str::slug($categoryName),
                    'sort_order' => $sort++,
                ]);

                foreach ($dishes as $dish) {
                    MenuItem::create([
                        'canteen_id' => $canteen->id,
                        'category_id' => $category->id,
                        'name' => $dish[0],
                        'description' => $dish[2] ?? null,
                        'price' => $dish[1],
                        'image_path' => null,
                        'is_available' => true,
                    ]);
                    $count++;
                }
            }

            return $count;
        });
    }
}
