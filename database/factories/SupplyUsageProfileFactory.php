<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\SupplyUsageProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplyUsageProfile>
 */
class SupplyUsageProfileFactory extends Factory
{
    protected $model = SupplyUsageProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'consumables' => [],
            'notes' => null,
        ];
    }
}
