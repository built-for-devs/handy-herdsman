<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cattle-for-sale board (spec §5.9)
|--------------------------------------------------------------------------
|
| A clients-only bulletin board — NOT a marketplace, no transactions. A client
| toggles one of their herd animals to "for sale" and picks which profile
| fields are shared; the listing then exposes ONLY those chosen fields.
|
| The shareable field catalogue is config (spec §7) so the set of fields a
| seller may reveal can change without a deploy. A field is only ever exposed
| on a listing when it is BOTH in this catalogue AND in the animal's
| `for_sale_shared_fields` selection — anything else stays private.
|
*/

return [
    // Cattle profile fields a seller may choose to share, in display order →
    // human label. Order here is the order fields render on a listing.
    'shareable_fields' => [
        'reg_name' => 'Registered name',
        'herd_number' => 'Herd number',
        'breed' => 'Breed',
        'animal_type' => 'Animal type',
        'dob' => 'Date of birth',
        'has_calved' => 'Has calved',
        'a2a2' => 'A2/A2',
        'notes' => 'Notes',
    ],
];
