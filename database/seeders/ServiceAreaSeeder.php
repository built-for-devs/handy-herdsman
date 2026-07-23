<?php

namespace Database\Seeders;

use App\Models\ServiceAreaRule;
use Illuminate\Database\Seeder;

/**
 * Service-area rules (spec §2, §10b). ~15mi standard; over 15 -> $30 flat per
 * protocol; further is case-by-case. Mileage-based, from the farm.
 */
class ServiceAreaSeeder extends Seeder
{
    public function run(): void
    {
        // Standard range: no fee within 15 miles.
        ServiceAreaRule::updateOrCreate(
            ['type' => 'fee_tier', 'zone_label' => 'standard'],
            ['min_miles' => 0, 'max_miles' => 15, 'fee' => 0, 'declined' => false, 'sort_order' => 0]
        );

        // Over 15 miles: $30 flat distance fee (per booking/protocol).
        ServiceAreaRule::updateOrCreate(
            ['type' => 'fee_tier', 'zone_label' => 'distance_fee'],
            ['min_miles' => 15, 'max_miles' => null, 'fee' => 30, 'declined' => false, 'sort_order' => 1]
        );
    }
}
