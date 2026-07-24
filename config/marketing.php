<?php

// Static marketing/brand facts (spec §2). NOT pricing — prices/fees live in
// rate_config (§7). Editable here without a deploy touching business logic.
return [
    'brand' => 'Handy Herdsman',
    'tagline' => 'Cattle AI, herd management & ranch services — Valley Mills, TX.',

    'address' => [
        'street' => '1351 High Prairie Road',
        'city' => 'Valley Mills',
        'state' => 'TX',
        'zip' => '76689',
    ],

    // On-call text number surfaced on Contact + on-call CTAs (§5.1). Placeholder.
    'contact' => [
        'text_number' => env('HH_TEXT_NUMBER', '(254) 555-0142'),
        'email' => env('HH_CONTACT_EMAIL', 'jeff@handyherdsman.com'),
    ],

    // Standard service range in miles; over this triggers a distance fee (§2).
    'service_range_miles' => 15,

    // Stated on-site as trust signals — things we explicitly do NOT do (§2, §5.2).
    'not_offered' => [
        [
            'title' => 'Castration',
            'note' => 'Handled case-by-case, off-platform — not a bookable service here.',
        ],
        [
            'title' => 'Hoof trimming',
            'note' => 'We assess lameness/hooves, but trimming is referred out — see our Resource Directory.',
        ],
        [
            'title' => 'Anything requiring a veterinary license',
            'note' => 'Jeff does everything legal in Texas without a vet license, and is honest about that line. Vet work goes to your vet; he can handle the cattle chute-side.',
        ],
    ],
];
