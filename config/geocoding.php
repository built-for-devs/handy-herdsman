<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Geocoding (spec §5.10, §9)
|--------------------------------------------------------------------------
|
| Client addresses are geocoded ONCE at onboarding and the lat/lng cached.
| The provider sits behind the App\Services\Geocoding\Geocoder abstraction so
| it is swappable and never hardcodes an API key. When no key is configured
| the null driver is used: the address is stored and coordinates are left null
| until a key is available and the queued GeocodeClient job is re-run.
|
*/

return [
    // 'google' when a key is present, otherwise the resolver falls back to the
    // null driver automatically (see AppServiceProvider).
    'driver' => env('GEOCODER_DRIVER', 'google'),

    'google' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
        'endpoint' => env(
            'GOOGLE_GEOCODING_ENDPOINT',
            'https://maps.googleapis.com/maps/api/geocode/json'
        ),
    ],

    // Farm origin — all distance math originates here (§2). Cached distance is
    // computed by a later milestone; the coordinates live here so there is a
    // single source of truth.
    'origin' => [
        'lat' => env('FARM_ORIGIN_LAT'),
        'lng' => env('FARM_ORIGIN_LNG'),
    ],
];
