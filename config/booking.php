<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Booking & scheduling (spec §5.5, §10b — Scheduler & availability)
|--------------------------------------------------------------------------
|
| Operational knobs the booking scheduler reads. Working hours, max visits/day
| and the inter-appointment buffer are per-weekday DATA in `availability_rules`
| (not here) — this file holds only the scheduler's own search parameters and
| the couple of hard safety rules that are not per-weekday. Everything is
| editable without a deploy (spec §7).
|
*/

return [
    // Granularity (minutes) at which the scheduler probes candidate Visit 1
    // start times within a working day looking for a chain whose V1/V2/V3 all
    // land in bookable slots.
    'candidate_slot_minutes' => (int) env('BOOKING_CANDIDATE_SLOT_MINUTES', 30),

    // Assumed appointment length (minutes) used for collision detection between
    // Jeff's existing visits. Jeff's calendar is global (one person), so
    // collisions are checked across every team.
    'visit_duration_minutes' => (int) env('BOOKING_VISIT_DURATION_MINUTES', 60),

    // How many viable Visit 1 dates the protocol scheduler offers at once.
    'max_candidate_dates' => (int) env('BOOKING_MAX_CANDIDATE_DATES', 6),

    // Hard cap on how far PAST the search horizon the scheduler will look for
    // the "next viable dates" when nothing is bookable in the horizon (§10b —
    // never fail silently / never return an empty picker).
    'beyond_horizon_cap_days' => (int) env('BOOKING_BEYOND_HORIZON_CAP_DAYS', 180),

    // No Sunday-morning appointment is EVER bookable, even if a Sunday is
    // configured as a working day (§10b). Anything before this local time on a
    // Sunday is rejected. On-call emergencies bypass availability entirely.
    'sunday_morning_until' => env('BOOKING_SUNDAY_MORNING_UNTIL', '12:00'),

    // Default proposed local time for a single-visit standard/natural booking
    // when the client does not pick one.
    'default_visit_time' => env('BOOKING_DEFAULT_VISIT_TIME', '09:00'),
];
