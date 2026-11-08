<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Until now a canteen's head was an "admin" account tied to one canteen. That is exactly
     * what the new Owner role is, so those accounts become owners. Admins with no canteen
     * (the overall administrators) are not touched.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'admin')
            ->whereNotNull('canteen_id')
            ->update(['role' => 'owner']);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'owner')
            ->whereNotNull('canteen_id')
            ->update(['role' => 'admin']);
    }
};
