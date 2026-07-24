<?php

namespace Database\Factories;

use App\Models\Cattle;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cattle>
 */
class CattleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'reg_name' => fake()->firstName(),
            'herd_number' => (string) fake()->numberBetween(1, 999),
            'dob' => fake()->dateTimeBetween('-4 years', '-1 year'),
            'breed' => 'Angus',
            'animal_type' => 'cow',
            'has_calved' => true,
            'status' => 'active',
        ];
    }
}
