<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Protocol timing constants (spec §3, §7, §10b)
|--------------------------------------------------------------------------
|
| The physiological timing constants that drive the CIDR 10-day sync
| protocol and the AI Timing Calculator. These change ONLY if breeding
| guidance changes — one edit here. All hour offsets are ABSOLUTE
| DURATIONS and must be added as such (never calendar arithmetic), so a
| DST boundary can never shift a breeding window (§10b — Timing math).
|
| Operational availability (working hours, max visits/day, buffer,
| blackout dates) is editable data and lives in the availability_rules /
| blackout_dates tables, NOT here.
|
*/

return [

    // All timestamps are stored in UTC and computed/displayed in this zone.
    'timezone' => 'America/Chicago',

    // Visit 1 -> Visit 2 is exactly 7 days (absolute duration).
    'v1_to_v2_days' => 7,

    /*
     | Visit 3 windows, measured in absolute hours AFTER Visit 2's actual
     | completed timestamp. Recommended AI time = midpoint of the window
     | (maximises buffer on both sides). The full window is the acceptable
     | range; scheduling defaults to the midpoint, adjustable only within
     | the window.
     */
    'windows' => [
        'cow' => ['start_hours' => 60, 'end_hours' => 66],    // midpoint ~63h
        'heifer' => ['start_hours' => 52, 'end_hours' => 56], // midpoint ~54h
    ],

    /*
     | Computed-display "age stage" labels derived from `dob` (§10b). The
     | animal_type stored on cattle is never "calf" — the label is derived
     | so it stays correct as the animal ages with zero maintenance.
     */
    'age_stages' => [
        'calf_max_months' => 6,       // under 6 months -> "calf"
        'weanling_max_months' => 12,  // 6-12 months -> "weanling / yearling"
        // over 12 months -> plain animal_type (heifer / cow / bull / steer)
    ],

    /*
     | Services that physically breed/AI the animal. bull/steer are BLOCKED
     | from these (§10b — Animal type). Any service with type `protocol`
     | breeds the animal; the slugs below cover breeding services that are
     | not of type `protocol` (e.g. on-call heat breeding). Editable data —
     | adding a breeding service later is config, not a deploy.
     */
    'breeding_service_slugs' => [
        'on-call-heat-breeding',
    ],

    // Sundays are off by default; no Sunday-morning appointment is ever
    // bookable. On-call emergencies bypass availability entirely (§10b).
    'no_sunday_mornings' => true,

    // How far ahead the scheduler searches for a viable Visit 1 date before
    // reporting that none exists and offering later dates (§10b — Scheduler).
    'search_horizon_days' => 60,
];
