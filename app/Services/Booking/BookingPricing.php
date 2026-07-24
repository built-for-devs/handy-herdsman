<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Models\RateConfig;
use App\Models\Service;

/**
 * Turns a service's editable `price_rule` plus the service-area result into a
 * client-facing quote (spec §2, §6.6, §10b — Money). No prices are hardcoded —
 * every number comes from `services.price_rule`, `rate_config` or
 * `service_area_rules`. No sales tax anywhere.
 */
class BookingPricing
{
    public function __construct(private ServiceAreaResolver $serviceArea) {}

    public function quote(Service $service, int $headcount, ServiceAreaResult $area): BookingQuote
    {
        $rule = (array) $service->price_rule;
        $model = $rule['model'] ?? 'flat';
        $headcount = max(1, $headcount);

        $base = 0.0;
        $additional = 0.0;
        $minApplied = false;

        if ($model === 'per_head') {
            // Per-head with a single-visit minimum (§6.4). The minimum applies
            // ONCE to the whole visit, not per animal.
            $perHead = (float) ($rule['per_head'] ?? 0);
            $minimum = (float) ($rule['visit_minimum'] ?? RateConfig::value('visit_minimum', ['price' => 0])['price'] ?? 0);
            $computed = $perHead * $headcount;
            $base = max($computed, $minimum);
            $minApplied = $base > $computed;
        } else {
            $base = (float) ($rule['price'] ?? 0);

            // Plan add-on for extra cows beyond the included count (§5.2).
            $included = (int) ($rule['included_cows'] ?? 0);
            $perAdditional = (float) ($rule['per_additional_cow'] ?? 0);

            if ($included > 0 && $perAdditional > 0 && $headcount > $included) {
                $additional = ($headcount - $included) * $perAdditional;
            }
        }

        return new BookingQuote(
            base: round($base, 2),
            additionalAnimals: round($additional, 2),
            distanceFee: $area->feeApplies ? round($area->feeAmount, 2) : 0.0,
            headcount: $headcount,
            visitMinimumApplied: $minApplied,
        );
    }

    /**
     * The normal visit rate a failed/aborted visit is STILL billed at (§10b).
     * Jeff can override the amount per visit — no automated trip-fee logic.
     */
    public function failedVisitAmount(): float
    {
        return (float) (RateConfig::value('standard_farm_call', ['price' => 100])['price'] ?? 100);
    }
}
