<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The shopping cart now lives in the database instead of the PHP session, so it
     * survives a closed browser, an expired session and a log-in on another device.
     * A signed-in customer's cart is tied to their account; a guest's cart is tied to
     * a random token kept in a long-lived cookie.
     */
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_token', 64)->nullable()->index();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();

            $table->unique(['user_id', 'menu_item_id']);
            $table->unique(['guest_token', 'menu_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
