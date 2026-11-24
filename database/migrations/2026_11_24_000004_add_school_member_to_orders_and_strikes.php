<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ID tap: an order can remember which school ID was tapped for it, and missed orders / pauses can follow
 * that ID, so changing phone number or SIM does not start the count again.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['orders', 'order_strikes', 'phone_suspensions'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('school_member_id')->nullable()->constrained('school_members')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['orders', 'order_strikes', 'phone_suspensions'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('school_member_id');
            });
        }
    }
};
