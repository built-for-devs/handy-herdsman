<?php

namespace Database\Seeders;

use App\Models\GestationConfig;
use Illuminate\Database\Seeder;

/**
 * Breed -> average gestation days (spec §5.4b). Default 283; heifer offset is
 * stored as a special row. Editable, no deploy.
 */
class GestationConfigSeeder extends Seeder
{
    public function run(): void
    {
        $breeds = [
            'Holstein' => 280,
            'Jersey' => 279,
            'Ayrshire' => 282,
            'Milking Shorthorn' => 283,
            'Angus' => 283,
            'Guernsey' => 286,
            'Brown Swiss' => 288,
            'Hereford' => 288,
            'Charolais' => 286,
            'Simmental' => 287,
            'Gelbvieh' => 287,
            'Limousin' => 289,
            'Brangus' => 290,
            GestationConfig::DEFAULT_KEY => 283,      // default / unknown / crossbreed
            GestationConfig::HEIFER_OFFSET_KEY => -1, // heifers calve ~1-2 days earlier
        ];

        foreach ($breeds as $breed => $days) {
            GestationConfig::updateOrCreate(['breed' => $breed], ['gestation_days' => $days]);
        }
    }
}
