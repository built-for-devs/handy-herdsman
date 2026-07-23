<?php

namespace Database\Seeders;

use App\Models\AvailabilityRule;
use Illuminate\Database\Seeder;

/**
 * Jeff's default operational availability (spec §7, §10b). Sundays off by
 * default (no Sunday mornings ever); emergencies bypass availability entirely.
 * All values editable — the scheduler reads this table.
 */
class AvailabilitySeeder extends Seeder
{
    public function run(): void
    {
        // 0 = Sunday .. 6 = Saturday
        for ($day = 0; $day <= 6; $day++) {
            $isSunday = ($day === 0);

            AvailabilityRule::updateOrCreate(
                ['day_of_week' => $day],
                [
                    'is_working_day' => ! $isSunday,      // Sundays off by default
                    'start_time' => $isSunday ? null : '07:00',
                    'end_time' => $isSunday ? null : '18:00',
                    'max_visits_per_day' => 6,
                    'buffer_minutes' => 30,               // travel between appointments
                ]
            );
        }
    }
}
