<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Models\Client;
use App\Models\ServiceAreaRule;

/**
 * Resolves a client's distance from the farm into a service-area decision:
 * in range / out of range (fee) / declined zone (spec §2, §6.6, §10b).
 *
 * Distance is geocoded and CACHED at onboarding (M3) — this reader prefers the
 * cached mileage and only falls back to a straight-line estimate from the
 * cached coordinates, so it never triggers a paid Distance Matrix call on the
 * booking path (§5.10 — call it sparingly). Thresholds, fees and declined
 * zones are DATA in `service_area_rules`, editable without a deploy.
 */
class ServiceAreaResolver
{
    public function resolve(Client $client): ServiceAreaResult
    {
        $miles = $this->distanceMiles($client);
        $rules = ServiceAreaRule::query()->orderBy('sort_order')->get();

        // 1. Explicitly declined zones (by distance or by labelled area) → block.
        foreach ($rules->where('declined', true) as $rule) {
            if ($this->clientMatchesRule($client, $miles, $rule)) {
                return new ServiceAreaResult(
                    distanceMiles: $miles,
                    inRange: false,
                    feeApplies: false,
                    feeAmount: 0.0,
                    declined: true,
                    zoneLabel: $rule->zone_label,
                );
            }
        }

        // 2. Matching fee tier by mileage (0–15 no fee, >15 → $30, etc.).
        if ($miles !== null) {
            foreach ($rules->where('type', 'fee_tier')->where('declined', false) as $rule) {
                if ($this->milesInRule($miles, $rule)) {
                    $fee = (float) $rule->fee;

                    return new ServiceAreaResult(
                        distanceMiles: $miles,
                        inRange: true,
                        feeApplies: $fee > 0,
                        feeAmount: $fee,
                        declined: false,
                        zoneLabel: $rule->zone_label,
                    );
                }
            }
        }

        // 3. Unknown distance (not yet geocoded): treat as in-range, no fee.
        return new ServiceAreaResult(
            distanceMiles: $miles,
            inRange: true,
            feeApplies: false,
            feeAmount: 0.0,
            declined: false,
            zoneLabel: null,
        );
    }

    /**
     * Cached mileage first; otherwise a straight-line estimate from the cached
     * coordinates and the configured farm origin. Null when neither is known.
     */
    private function distanceMiles(Client $client): ?float
    {
        if ($client->cached_distance_miles !== null) {
            return (float) $client->cached_distance_miles;
        }

        $originLat = config('geocoding.origin.lat');
        $originLng = config('geocoding.origin.lng');

        if ($client->lat === null || $client->lng === null || $originLat === null || $originLng === null) {
            return null;
        }

        return $this->haversineMiles(
            (float) $originLat,
            (float) $originLng,
            (float) $client->lat,
            (float) $client->lng,
        );
    }

    private function clientMatchesRule(Client $client, ?float $miles, ServiceAreaRule $rule): bool
    {
        // A labelled declined zone matches on city/postal code.
        if ($rule->zone_label !== null && $rule->zone_label !== '') {
            $label = mb_strtolower($rule->zone_label);

            if (in_array($label, array_filter([
                mb_strtolower((string) $client->city),
                mb_strtolower((string) $client->postal_code),
            ]), true)) {
                return true;
            }
        }

        return $miles !== null && $this->milesInRule($miles, $rule);
    }

    private function milesInRule(float $miles, ServiceAreaRule $rule): bool
    {
        $min = $rule->min_miles !== null ? (float) $rule->min_miles : null;
        $max = $rule->max_miles !== null ? (float) $rule->max_miles : null;

        if ($min !== null && $miles < $min) {
            return false;
        }

        if ($max !== null && $miles >= $max) {
            return false;
        }

        return true;
    }

    private function haversineMiles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusMiles = 3958.8;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadiusMiles * 2 * asin(min(1.0, sqrt($a)));
    }
}
