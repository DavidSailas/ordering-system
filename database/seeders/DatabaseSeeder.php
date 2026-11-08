<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with demo data for two canteens.
     * Every demo account uses the password "password" (see MockDataSeeder).
     */
    public function run(): void
    {
        $this->call([
            MockDataSeeder::class,
        ]);
    }
}
