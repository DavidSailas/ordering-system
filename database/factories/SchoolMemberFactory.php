<?php

namespace Database\Factories;

use App\Models\SchoolMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolMember>
 */
class SchoolMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_number' => fake()->unique()->numerify('20##-####'),
            'name' => fake()->firstName().' '.fake()->lastName(),
            'kind' => 'student',
            'is_blocked' => false,
        ];
    }

    public function employee(): static
    {
        return $this->state(['kind' => 'employee']);
    }

    public function blocked(string $reason = 'Prank orders'): static
    {
        return $this->state(['is_blocked' => true, 'blocked_reason' => $reason]);
    }
}
