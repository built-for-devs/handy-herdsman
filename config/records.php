<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Herd records portal (spec §5.5, §6, §10b)
|--------------------------------------------------------------------------
|
| Config over code (spec §7): the health-record type catalogue, the Body
| Condition Score range, and the media disk all live here so they can change
| without a deploy. The vet-scopeable subset lives in `config/clients.php`.
|
*/

return [
    // health_records.type catalogue → human label. body_condition entries also
    // carry a 1-9 bcs_score; all types accept a free-form JSON payload.
    'types' => [
        'vaccination' => 'Vaccination',
        'treatment' => 'Treatment',
        'nutrition' => 'Nutrition regime',
        'dehorning' => 'Dehorning / disbudding',
        'preg_check' => 'Pregnancy check',
        'body_condition' => 'Body condition',
        'general' => 'General',
    ],

    // Body Condition Score scale (§5.5, §5.6c) — 1 emaciated, 9 obese, 5-6 target.
    'bcs' => [
        'min' => (int) env('RECORDS_BCS_MIN', 1),
        'max' => (int) env('RECORDS_BCS_MAX', 9),
    ],

    // Filesystem disk photo/media uploads are stored on. Local in dev; swap to
    // a cloud disk in production via FILESYSTEM_DISK/RECORDS_MEDIA_DISK (§228).
    'media_disk' => env('RECORDS_MEDIA_DISK', env('FILESYSTEM_DISK', 'local')),
];
