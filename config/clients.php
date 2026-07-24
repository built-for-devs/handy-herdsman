<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Client lifecycle (spec §10b — Client status)
|--------------------------------------------------------------------------
|
| new    -> first booking requires staff review (SLA below)
| active -> promoted automatically after ONE completed visit; books freely
| inactive -> after the idle threshold with no activity; reverts to review
|
| All timing is config-driven — never hardcoded (spec §7).
|
*/

return [
    // Idle window after which an active client reverts to `inactive`
    // (and therefore back to staff review on their next booking).
    'idle_threshold_days' => (int) env('CLIENT_IDLE_THRESHOLD_DAYS', 365),

    // First-time booking review SLA (§10b — Bookings). Surfaced to the client
    // as `provisional` until reviewed.
    'first_booking_review_sla_hours' => (int) env('CLIENT_FIRST_BOOKING_REVIEW_SLA_HOURS', 24),

    // Health-record types a client may grant an invited vet read access to
    // (spec §4, §6 — health_records.type). Profile access is granted separately.
    'vet_scopeable_record_types' => [
        'vaccination',
        'treatment',
        'nutrition',
        'dehorning',
        'preg_check',
        'body_condition',
        'general',
    ],
];
