<?php

namespace Database\Factories;

use App\Models\Cattle;
use App\Models\Team;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    public function definition(): array
    {
        $team = Team::factory();

        return [
            'team_id' => $team,
            'cattle_id' => Cattle::factory()->for($team),
            'type' => 'standard',
            'scheduled_at' => now()->addDays(2),
        ];
    }
}
