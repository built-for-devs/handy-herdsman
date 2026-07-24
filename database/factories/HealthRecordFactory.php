<?php

namespace Database\Factories;

use App\Models\Cattle;
use App\Models\HealthRecord;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthRecord>
 */
class HealthRecordFactory extends Factory
{
    public function definition(): array
    {
        $team = Team::factory();

        return [
            'team_id' => $team,
            'cattle_id' => Cattle::factory()->for($team),
            'type' => 'vaccination',
            'payload' => [],
            'recorded_at' => now(),
        ];
    }

    public function ofType(string $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }
}
