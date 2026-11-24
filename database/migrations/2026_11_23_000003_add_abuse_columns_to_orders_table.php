<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('phone_key', 20)->nullable()->index()->after('phone');
            $table->string('ip_address', 45)->nullable()->index()->after('phone_key');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['phone_key']);
            $table->dropIndex(['ip_address']);
            $table->dropColumn(['phone_key', 'ip_address']);
        });
    }
};
