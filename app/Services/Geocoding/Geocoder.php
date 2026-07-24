<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

/**
 * Config-driven geocoder abstraction (spec §5.10, §9). Implementations must
 * never hardcode an API key; the concrete driver is resolved from config.
 */
interface Geocoder
{
    /**
     * Resolve a one-line address into coordinates. Returns an empty result
     * (null lat/lng) when the address can't be resolved.
     */
    public function geocode(string $address): GeocodeResult;
}
