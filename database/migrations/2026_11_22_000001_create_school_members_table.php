<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The school's list of students and employees. A customer can only create an account (and so only
 * place orders) with an ID that is on this list, and each ID can be claimed by one account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_members', function (Blueprint $table) {
            $table->id();
            $table->string('id_number', 40);                 // as the school writes it, e.g. 2024-0001
            $table->string('id_key', 40)->unique();          // letters + digits only, upper case: how IDs are compared
            $table->string('name');                          // as on the school list
            $table->string('kind', 20)->default('student');  // student | employee
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->boolean('is_blocked')->default(false);
            $table->string('blocked_reason')->nullable();
            $table->timestamps();

            $table->index('is_blocked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_members');
    }
};
