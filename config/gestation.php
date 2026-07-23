<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Gestation / due-date presentation constants (spec §5.4b)
|--------------------------------------------------------------------------
|
| The breed -> average-gestation-days table and the heifer offset are
| EDITABLE DATA and live in the `gestation_config` table (no deploy to
| change them). This file holds the presentation-layer constants: how wide
| the estimate range is, and the calving-prep milestone offsets. A due date
| is ALWAYS a range/estimate, never a hard date — individual variation of
| ±5 days is normal (§5.4b).
|
*/

return [

    // Estimates are shown as a range: due_date ± this many days. Individual
    // variation is real (±5 days is normal), so we never surface a hard date.
    'estimate_spread_days' => 5,

    /*
     | Calving-prep milestone offsets, in days BEFORE the estimated due date.
     | These drive the calving-countdown reminders (§5.7 / §9.3). `dry_off`
     | is the far-out herd-management milestone; the rest are the countdown.
     */
    'milestones' => [
        'dry_off' => 60,     // ~2 months out: begin dry-off window
        'minus_4wk' => 28,
        'minus_2wk' => 14,
        'minus_5d' => 5,
        'minus_2d' => 2,
        'minus_1d' => 1,
    ],
];
