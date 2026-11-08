<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('phone', 25)->nullable()->after('customer_name');
            $table->string('building', 80)->nullable()->after('phone');
            $table->string('room', 40)->nullable()->after('building');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['phone', 'building', 'room']);
        });
    }
};
