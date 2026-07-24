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

    /*
     | Nurture scheduling driven by a FINAL preg-check result (§10b — Preg
     | check results). M7 writes the reminder rows; M9's engine sends them.
     | Config over code: offsets are editable, never hardcoded.
     |
     |  - `bred`    → calving-countdown reminders at the gestation milestones
     |               (config/gestation.php `milestones`), category 1.
     |  - `open`    → a rebreed prompt this many days out ("don't lose the
     |               season"), category 3.
     |  - `recheck` → schedule another check this many days out, category 3.
     */
    'nurture' => [
        'calving_countdown' => [
            'category' => 1,
            'template' => 'calving_countdown',
        ],
        'rebreed_prompt' => [
            'category' => 3,
            'template' => 'rebreed_prompt',
            'after_days' => 0,
        ],
        'preg_recheck' => [
            'category' => 3,
            'template' => 'preg_recheck',
            'after_days' => 30,
        ],
    ],

    /*
     | Transactional reminders around a breeding (§5.7 — "Around a breeding").
     | Offsets are counted from the insemination date (the completed breeding
     | visit). `suppress_if_preg_check` cancels the message at send time if the
     | client has since booked/recorded a pregnancy check — no point nagging
     | someone who is already on it. Config over code: edit freely, no deploy.
     */
    'transactional' => [
        'breeding' => [
            [
                'offset_days' => 21,
                'category' => 1,
                'template' => 'return_to_heat', // watch for return-to-heat; did she settle?
            ],
            [
                'offset_days' => 30,
                'category' => 3,
                'template' => 'book_preg_check', // time to confirm the pregnancy
            ],
            [
                'offset_days' => 60,
                'category' => 3,
                'template' => 'book_preg_check_followup',
                'suppress_if_preg_check' => true, // only if no check booked yet
            ],
        ],
    ],

    /*
     | Lifecycle & nurture (§5.7 — the recurring-revenue loop). Post-calving
     | rebreed cadence + the referral ask. Offsets in days AFTER calving.
     */
    'lifecycle' => [
        'calving' => [
            [
                'offset_days' => 30,
                'category' => 3,
                'template' => 'rebreed_timeline', // plan her rebreed; here's the timeline
            ],
            [
                'offset_days' => 50,
                'category' => 3,
                'template' => 'rebreed_booking', // direct rebreed booking prompt (core loop)
            ],
            [
                'offset_days' => 7,
                'category' => 4,
                'template' => 'referral_ask', // promotional — opt-in gated (§5.7)
            ],
        ],
        // Due date passed with no calving logged — care check + data capture.
        'calving_care_check' => [
            'category' => 1,
            'template' => 'calving_care_check',
            'after_due_days' => 1,
        ],
    ],

    /*
     | Data-driven nudges from a client's own logged records (§5.7 — cat.3).
     | Thresholds are editable data. The daily `reminders:nurture-sweep`
     | command scans for these; `dedupe_key` keeps every scan idempotent.
     */
    'data_driven' => [
        // Body condition out of the 5-6 target range at the last visit — the
        // #1 driver of AI failure — triggers a nutrition follow-up (§5.5, §5.6c).
        'bcs' => [
            'target_min' => (int) env('REMINDERS_BCS_TARGET_MIN', 5),
            'target_max' => (int) env('REMINDERS_BCS_TARGET_MAX', 6),
            'after_days' => 3,
            'category' => 3,
            'template' => 'nutrition_followup',
        ],
        // Semen storage nearing the free-year anniversary — renews at $50, or
        // want to use these straws? Fires this many days before storage_free_until.
        'storage_renewal' => [
            'lead_days' => 30,
            'category' => 3,
            'template' => 'storage_renewal',
        ],
        // Straws sitting unused past this many months — ready to breed?
        'straws_unused' => [
            'after_months' => 6,
            'category' => 3,
            'template' => 'straws_unused',
        ],
        // Dormant client (no activity in N months) — soft re-engagement tied to
        // a blog piece (§5.7 — retention).
        'dormant' => [
            'after_months' => 6,
            'category' => 4,
            'template' => 'dormant_reengagement',
            'pillar' => 'nutrition',
        ],
    ],

    /*
     | Message copy per template, resolved at send time (§5.7). Config over
     | code so Jeff can reword without a deploy. Nurture emails may append a
     | blog link (payload `post_title`/`post_url`) so content does double duty.
     | `pillar` marks templates whose email pulls a matching blog post.
     */
    'templates' => [
        'calving_countdown' => [
            'subject' => 'Calving countdown — get ready',
            'line' => 'Your cow is approaching her due date. Review her body condition and colostrum plan, and watch for calving signs.',
            'sms' => 'Handy Herdsman: calving is near for your cow. Watch for signs — reply if you need calving support.',
            'cta_label' => 'Book calving support',
            'cta_path' => '/book',
        ],
        'calving_care_check' => [
            'subject' => 'Did she calve? How did it go?',
            'line' => 'Her due date has passed. Let us know how calving went so we can update her records — and reach out if you need newborn-calf care.',
            'sms' => 'Handy Herdsman: has your cow calved yet? Let us know how it went.',
            'cta_label' => 'Log the calving',
            'cta_path' => '/portal',
        ],
        'return_to_heat' => [
            'subject' => 'Watch for return-to-heat',
            'line' => "It's about 21 days since breeding — watch for signs she has returned to heat. If she cycles again, she may not have settled.",
            'sms' => 'Handy Herdsman: ~21 days since breeding. Watch for return-to-heat to see if she settled.',
            'cta_label' => 'Learn the signs',
            'cta_path' => '/blog',
            'pillar' => 'heat detection',
        ],
        'book_preg_check' => [
            'subject' => 'Time to confirm the pregnancy',
            'line' => "It's been about 30 days since breeding — a good time to book a pregnancy check and confirm she settled.",
            'sms' => 'Handy Herdsman: ~30 days post-breeding. Book a pregnancy check to confirm.',
            'cta_label' => 'Book a preg check',
            'cta_path' => '/book',
        ],
        'book_preg_check_followup' => [
            'subject' => 'Still need a pregnancy check?',
            'line' => "We don't have a pregnancy check on record yet. Confirming now protects the breeding season — let's get her checked.",
            'sms' => 'Handy Herdsman: no preg check on record yet. Book one to confirm she settled.',
            'cta_label' => 'Book a preg check',
            'cta_path' => '/book',
        ],
        'rebreed_prompt' => [
            'subject' => "Let's rebreed — don't lose the season",
            'line' => 'She came back open. Rebreeding promptly keeps her on schedule — book an AI appointment now.',
            'sms' => 'Handy Herdsman: she came back open. Book a rebreed now so you don\'t lose the season.',
            'cta_label' => 'Book AI now',
            'cta_path' => '/book',
        ],
        'preg_recheck' => [
            'subject' => 'Time for a recheck',
            'line' => 'Her last check was inconclusive. Book a follow-up pregnancy check so we know where she stands.',
            'sms' => 'Handy Herdsman: time to recheck your cow. Book a follow-up preg check.',
            'cta_label' => 'Book a recheck',
            'cta_path' => '/book',
        ],
        'rebreed_timeline' => [
            'subject' => "Planning her rebreed — here's the timeline",
            'line' => "She's about a month post-calving. Here's the timeline to get her rebred and keep her on an annual cycle.",
            'sms' => 'Handy Herdsman: ~30 days post-calving. Time to plan her rebreed.',
            'cta_label' => 'See the plan',
            'cta_path' => '/blog',
            'pillar' => 'AI protocol',
        ],
        'rebreed_booking' => [
            'subject' => "She's ready to rebreed",
            'line' => "She's recovered and ready. Book her rebreed now to stay on a 12-month calving interval.",
            'sms' => 'Handy Herdsman: your cow is ready to rebreed. Book her AI appointment.',
            'cta_label' => 'Book AI now',
            'cta_path' => '/book',
        ],
        'referral_ask' => [
            'subject' => 'Know someone who could use Jeff?',
            'line' => "Congratulations! If you've been happy with our work, we'd be grateful if you shared us with a neighbor.",
            'sms' => 'Handy Herdsman: glad it went well! Know a neighbor who could use Jeff? Send them our way.',
            'cta_label' => 'Refer a rancher',
            'cta_path' => '/contact',
        ],
        'nutrition_followup' => [
            'subject' => 'Body condition follow-up',
            'line' => 'Her body condition was outside the 5-6 target range at the last visit. Condition is the #1 driver of AI success — let\'s review her nutrition and minerals.',
            'sms' => 'Handy Herdsman: body condition was off-target last visit. Let\'s review nutrition — it drives breeding success.',
            'cta_label' => 'Read the nutrition guide',
            'cta_path' => '/blog',
            'pillar' => 'nutrition',
        ],
        'storage_renewal' => [
            'subject' => 'Semen storage renewal coming up',
            'line' => 'Your free storage year is ending. Storage renews at $50/yr — or if these straws are ready, let\'s book a breeding.',
            'sms' => 'Handy Herdsman: your free semen storage year is ending ($50/yr after). Renew or book a breeding?',
            'cta_label' => 'Manage storage',
            'cta_path' => '/portal',
        ],
        'straws_unused' => [
            'subject' => 'Ready to breed with these straws?',
            'line' => "You've had straws in storage for a while. Whenever you're ready, we can get you on the calendar to put them to work.",
            'sms' => 'Handy Herdsman: got straws sitting in storage — ready to breed with them? Let\'s book.',
            'cta_label' => 'Book AI',
            'cta_path' => '/book',
        ],
        'dormant_reengagement' => [
            'subject' => "We're here when you're ready",
            'line' => "It's been a while! Here's something from the herd that might help. Reach out any time — we'd love to get you back on the calendar.",
            'sms' => 'Handy Herdsman: it\'s been a while — we\'re here whenever you\'re ready to breed again.',
            'cta_label' => 'Read the latest',
            'cta_path' => '/blog',
            'pillar' => 'nutrition',
        ],
    ],
];
