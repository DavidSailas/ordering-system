<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery: the buildings a runner brings orders to, what each costs, and where each canteen is.
 * Orders remember the fee they were charged, so changing a price later never rewrites history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('canteen_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedSmallInteger('floors')->default(1);
            $table->decimal('base_fee', 8, 2)->default(0);
            $table->decimal('per_floor_fee', 8, 2)->default(0);
            // The building the canteen itself is in (and its floor): fees there count floors from the canteen.
            $table->boolean('is_home')->default(false);
            $table->unsignedSmallInteger('home_floor')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['canteen_id', 'name']);
        });

        Schema::table('canteens', function (Blueprint $table): void {
            $table->boolean('delivery_enabled')->default(true);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedSmallInteger('floor')->nullable()->after('building');
            $table->decimal('delivery_fee', 10, 2)->default(0)->after('total');
        });

        // Starting values for the canteens that already exist (all editable on Admin → Delivery & fees).
        $now = now();

        foreach (DB::table('canteens')->pluck('id') as $canteenId) {
            DB::table('delivery_zones')->insert([
                ['canteen_id' => $canteenId, 'name' => 'Annex', 'floors' => 10, 'base_fee' => 10, 'per_floor_fee' => 2, 'is_home' => true, 'home_floor' => 6, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['canteen_id' => $canteenId, 'name' => 'Main Building', 'floors' => 6, 'base_fee' => 25, 'per_floor_fee' => 2, 'is_home' => false, 'home_floor' => null, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['floor', 'delivery_fee']);
        });

        Schema::table('canteens', function (Blueprint $table): void {
            $table->dropColumn('delivery_enabled');
        });

        Schema::dropIfExists('delivery_zones');
    }
};
