<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_choice', 12)->nullable();
            $table->string('customer_payment_ref', 100)->nullable();
            $table->timestamp('payment_submitted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_choice', 'customer_payment_ref', 'payment_submitted_at']);
        });
    }
};
