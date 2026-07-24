<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

/**
 * Fallback geocoder used when no provider key is configured. The address is
 * stored by the caller and coordinates are left null until a key is available
 * and the queued GeocodeClient job is re-run (spec §5.10).
 */
final class NullGeocoder implements Geocoder
{
    public function geocode(string $address): GeocodeResult
    {
        return GeocodeResult::empty();
    }
}
