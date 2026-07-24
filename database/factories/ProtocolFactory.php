<?php

namespace Database\Factories;

use App\Enums\PlanType;
use App\Models\Protocol;
use App\Models\Service;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Protocol>
 */
class ProtocolFactory extends Factory
{
    public function definition(): array
    {
        $team = Team::factory();

        return [
            'team_id' => $team,
            'service_id' => Service::factory()->syncPlan(),
            'plan_type' => PlanType::Sync->value,
            'animal_type' => 'cow',
            'status' => 'scheduled',
        ];
    }
}
