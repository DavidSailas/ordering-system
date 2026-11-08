<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canteens', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tagline', 160)->nullable();
            $table->string('accent', 7)->default('#e25806');
            $table->string('logo_path')->nullable();
            $table->boolean('is_open')->default(true);
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->string('receipt_footer', 160)->nullable();
            $table->text('buildings')->nullable();
            $table->string('gcash_number', 40)->nullable();
            $table->string('gcash_name', 80)->nullable();
            $table->string('maya_number', 40)->nullable();
            $table->string('maya_name', 80)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canteens');
    }
};
