<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_phones', function (Blueprint $table) {
            $table->id();
            $table->string('phone_key', 20)->unique();   // the last 10 digits: 0917 123 4567 and +63 917 123 4567 are the same
            $table->string('phone', 40);                 // as it was typed, to show on screen
            $table->string('reason')->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_phones');
    }
};
