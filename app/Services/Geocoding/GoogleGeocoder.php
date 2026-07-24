<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Google Geocoding API driver (spec §9). The key comes from config, never a
 * literal. Any failure degrades to an empty result rather than throwing so a
 * transient outage never blocks onboarding.
 */
final class GoogleGeocoder implements Geocoder
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly string $key,
        private readonly string $endpoint,
    ) {}

    public function geocode(string $address): GeocodeResult
    {
        if (trim($address) === '') {
            return GeocodeResult::empty();
        }

        try {
            $response = $this->http
                ->get($this->endpoint, [
                    'address' => $address,
                    'key' => $this->key,
                ]);

            if (! $response->successful()) {
                return GeocodeResult::empty();
            }

            $location = $response->json('results.0.geometry.location');

            if (! is_array($location) || ! isset($location['lat'], $location['lng'])) {
                return GeocodeResult::empty();
            }

            return new GeocodeResult((float) $location['lat'], (float) $location['lng']);
        } catch (Throwable $e) {
            Log::warning('Geocoding failed', ['message' => $e->getMessage()]);

            return GeocodeResult::empty();
        }
    }
}
