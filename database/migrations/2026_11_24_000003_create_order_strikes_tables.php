<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Missed orders ("strikes") and the automatic pauses they cause. Both are kept per phone number
 * (the same key the phone block uses) and are school-wide, so a customer cannot skip a pause by
 * ordering from the other canteen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_strikes', function (Blueprint $table): void {
            $table->id();
            $table->string('phone_key', 20)->index();
            $table->string('phone', 40);
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 160);
            $table->timestamps();
        });

        Schema::create('phone_suspensions', function (Blueprint $table): void {
            $table->id();
            $table->string('phone_key', 20)->index();
            $table->string('phone', 40);
            $table->string('reason', 160);
            $table->unsignedSmallInteger('strikes')->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->index();
            $table->timestamp('lifted_at')->nullable();
            $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_suspensions');
        Schema::dropIfExists('order_strikes');
    }
};
