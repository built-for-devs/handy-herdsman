<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Reminder & nurture engine defaults (spec §5.7, §10b)
|--------------------------------------------------------------------------
|
| Quiet hours and the 4 messaging categories. Non-urgent messages queue to
| the next allowed window; on-call / emergency confirmations bypass quiet
| hours. Per-client channel preferences and staff overrides live on the
| clients table; these are the system defaults.
|
*/

return [

    // Global quiet hours (local time, config.timezone). Non-urgent messages
    // defer to the next allowed window. Emergencies bypass (§10b).
    'quiet_hours' => [
        'start' => '21:00', // 9pm — stop sending
        'end' => '08:00',   // 8am — resume sending
    ],

    /*
     | The 4 channel-preference categories (§5.7). Each client picks a
     | channel per category (email / text / both); at least one must stay on.
     | `bypasses_quiet_hours` marks act-now categories that send at any hour.
     */
    'categories' => [
        1 => [
            'key' => 'time_sensitive',
            'label' => 'Time-sensitive / act-now',
            'default_channel' => 'both',      // a missed message costs a calf or a breeding
            'bypasses_quiet_hours' => true,
        ],
        2 => [
            'key' => 'appointment',
            'label' => 'Appointment & booking',
            'default_channel' => 'email',
            'bypasses_quiet_hours' => false,
        ],
        3 => [
            'key' => 'herd_followups',
            'label' => 'Herd follow-ups',
            'default_channel' => 'email',
            'bypasses_quiet_hours' => false,
        ],
        4 => [
            'key' => 'seasonal_content',
            'label' => 'Seasonal & content',
            'default_channel' => 'email',     // TCPA-safe default for promotional
            'bypasses_quiet_hours' => false,
        ],
    ],
];
