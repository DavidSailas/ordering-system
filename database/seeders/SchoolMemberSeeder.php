<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\SchoolMember;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * DEMO ONLY. A tiny sample of the school list so the sign-up can be tried out, plus one demo student
 * account (student@example.com / password, ID 2024-0001, last name "Dela Cruz").
 *
 * For real use: sign in as the overall administrator, open "School IDs", delete these samples and
 * import the school's real list.
 */
class SchoolMemberSeeder extends Seeder
{
    public function run(): void
    {
        $sample = [
            ['2024-0001', 'Juan Dela Cruz', 'student'],
            ['2024-0002', 'Maria Santos', 'student'],
            ['2024-0003', 'Carla Mendoza', 'student'],
            ['2024-0004', 'Gabriel Lopez', 'student'],
            ['2024-0005', 'Patricia Domingo', 'student'],
            ['E-0101', 'Roberto Reyes', 'employee'],
            ['E-0102', 'Elena Navarro', 'employee'],
        ];

        // Adds the samples without touching anyone else on the list, so re-seeding never wipes a real list.
        foreach ($sample as [$idNumber, $name, $kind]) {
            SchoolMember::firstOrNew(['id_key' => SchoolMember::keyFor($idNumber)])
                ->fill(['id_number' => $idNumber, 'name' => $name, 'kind' => $kind])
                ->save();
        }

        $student = User::query()->updateOrCreate(
            ['email' => 'student@example.com'],
            ['name' => 'Juan Dela Cruz', 'password' => 'password', 'role' => UserRole::Customer, 'is_active' => true, 'canteen_id' => null],
        );
        $student->forceFill(['email_verified_at' => now()])->save();

        SchoolMember::query()->where('id_key', '20240001')->update(['user_id' => $student->id]);
    }
}
