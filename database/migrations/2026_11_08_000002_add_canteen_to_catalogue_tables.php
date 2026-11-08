<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['categories', 'menu_items', 'orders', 'users'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('canteen_id')->nullable()->index();
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('canteen_number')->nullable();
        });

        // Two canteens may both have a "Drinks" category.
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_name_unique');
            $table->dropUnique('categories_slug_unique');
            $table->unique(['canteen_id', 'name']);
            $table->unique(['canteen_id', 'slug']);
        });

        // Everything that exists today becomes the first canteen.
        $settings = Schema::hasTable('settings') ? DB::table('settings')->pluck('value', 'key') : collect();
        $name = $settings['canteen_name'] ?? env('RESTAURANT_NAME', 'Cecilian Bites');

        $canteenId = DB::table('canteens')->insertGetId([
            'name' => $name,
            'slug' => Str::slug($name) ?: 'canteen-1',
            'accent' => '#e25806',
            'logo_path' => $settings['logo_path'] ?? null,
            'is_open' => ($settings['accepting_orders'] ?? '1') !== '0',
            'phone' => $settings['phone'] ?? env('RESTAURANT_PHONE'),
            'address' => $settings['address'] ?? env('RESTAURANT_ADDRESS'),
            'receipt_footer' => $settings['receipt_footer'] ?? null,
            'buildings' => $settings['buildings'] ?? null,
            'gcash_number' => $settings['gcash_number'] ?? env('STORE_GCASH_NUMBER'),
            'gcash_name' => $settings['gcash_name'] ?? null,
            'maya_number' => $settings['maya_number'] ?? env('STORE_MAYA_NUMBER'),
            'maya_name' => $settings['maya_name'] ?? null,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['categories', 'menu_items', 'orders'] as $tableName) {
            DB::table($tableName)->update(['canteen_id' => $canteenId]);
        }

        DB::table('orders')->update(['canteen_number' => DB::raw('id')]);
        DB::table('users')->whereIn('role', ['admin', 'cashier'])->update(['canteen_id' => $canteenId]);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['canteen_id', 'name']);
            $table->dropUnique(['canteen_id', 'slug']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('canteen_number');
        });

        foreach (['categories', 'menu_items', 'orders', 'users'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('canteen_id');
            });
        }
    }
};
