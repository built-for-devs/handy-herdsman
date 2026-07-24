<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Booking;
use App\Models\RateConfig;

/**
 * The authoritative money engine (spec §2, §5.2b, §6.6, §10b — Money). Turns a
 * set of services performed on one trip — plus per-booking flags — into an
 * itemised total. EVERY number comes from `services.price_rule` or
 * `rate_config`; nothing is hardcoded, so editing a rate changes totals with no
 * deploy (§7). No sales tax is ever added (§10b).
 *
 * Two rules the tests pin down:
 *  - the **distance fee is once per booking/protocol**, never per visit — one
 *    $30 covers all three protocol visits (§5.5);
 *  - the **per-visit minimum applies once per trip**, shared across every
 *    per-head task in that trip, not once per service (§5.2b).
 *
 * M10 reporting consumes {@see FeeBreakdown} — a flat list of coded lines whose
 * amounts sum to {@see FeeBreakdown::total()}.
 */
class FeeCalculator
{
    /**
     * @param  list<ServiceCharge>  $charges  services sharing ONE trip/visit
     */
    public function calculate(
        array $charges,
        bool $distanceFeeApplies = false,
        int $semenReceipts = 0,
        int $semenStorageCharges = 0,
    ): FeeBreakdown {
        $lines = [];

        // Per-head tasks are pooled so the visit minimum can be applied once
        // across the whole trip (§5.2b), regardless of how many per-head
        // services share it.
        $perHeadSubtotal = 0.0;
        $visitMinimum = 0.0;
        $hasPerHead = false;

        foreach ($charges as $charge) {
            if ($charge->model() === 'per_head') {
                $hasPerHead = true;
                $perHead = (float) ($charge->priceRule['per_head'] ?? 0);
                $amount = round($perHead * $charge->heads(), 2);
                $perHeadSubtotal += $amount;

                $visitMinimum = max($visitMinimum, $this->visitMinimumFor($charge));

                $lines[] = new FeeLine(
                    'per_head',
                    $this->lineLabel($charge, sprintf('%s × %d head', $charge->label ?? 'Per-head service', $charge->heads())),
                    $amount,
                );

                continue;
            }

            $base = (float) ($charge->priceRule['price'] ?? 0);
            $lines[] = new FeeLine('service_base', $charge->label ?? 'Service', round($base, 2));

            // Plan add-on for cows beyond the included count (§5.2).
            $included = (int) ($charge->priceRule['included_cows'] ?? 0);
            $perAdditional = (float) ($charge->priceRule['per_additional_cow'] ?? 0);

            if ($included > 0 && $perAdditional > 0 && $charge->heads() > $included) {
                $additional = round(($charge->heads() - $included) * $perAdditional, 2);
                $lines[] = new FeeLine('additional_cow', sprintf('Additional cow × %d', $charge->heads() - $included), $additional);
            }
        }

        // A SINGLE visit minimum floors the pooled per-head labor (§5.2b). If
        // multiple per-head tasks share one trip they share this one minimum.
        $visitMinimumApplied = false;

        if ($hasPerHead && $visitMinimum > 0 && $perHeadSubtotal < $visitMinimum) {
            $lines[] = new FeeLine(
                'visit_minimum_adjustment',
                'Per-visit minimum',
                round($visitMinimum - $perHeadSubtotal, 2),
            );
            $visitMinimumApplied = true;
        }

        // ONE distance fee per booking/protocol — never per visit (§5.5).
        if ($distanceFeeApplies) {
            $amount = round($this->rate('distance_fee', 'price', 0), 2);

            if ($amount > 0) {
                $lines[] = new FeeLine('distance_fee', 'Distance fee (per booking)', $amount);
            }
        }

        if ($semenReceipts > 0) {
            $amount = round($this->rate('semen_receipt_fee', 'price', 0) * $semenReceipts, 2);

            if ($amount > 0) {
                $lines[] = new FeeLine('semen_receipt', sprintf('Semen receipt × %d', $semenReceipts), $amount);
            }
        }

        if ($semenStorageCharges > 0) {
            $amount = round($this->rate('semen_storage_annual', 'price', 0) * $semenStorageCharges, 2);

            if ($amount > 0) {
                $lines[] = new FeeLine('semen_storage', sprintf('Semen storage × %d', $semenStorageCharges), $amount);
            }
        }

        return new FeeBreakdown($lines, $visitMinimumApplied);
    }

    /**
     * The total charged when a booking reaches `confirmed` (spec §5.5). Uses the
     * booking's single service, its animal count, and its per-booking distance
     * flag — the flag is set once by the M6 booking flow, never recomputed here.
     */
    public function forBooking(Booking $booking): FeeBreakdown
    {
        $service = $booking->service;

        $charges = $service !== null
            ? [ServiceCharge::forService($service, max(1, $booking->cattle()->count()))]
            : [];

        return $this->calculate($charges, distanceFeeApplies: (bool) $booking->distance_fee_flag);
    }

    private function visitMinimumFor(ServiceCharge $charge): float
    {
        // Prefer a minimum baked into the service's own price_rule, else fall
        // back to the shared `rate_config` visit minimum (§5.2b, §7).
        if (isset($charge->priceRule['visit_minimum'])) {
            return (float) $charge->priceRule['visit_minimum'];
        }

        return $this->rate('visit_minimum', 'price', 0);
    }

    private function lineLabel(ServiceCharge $charge, string $default): string
    {
        return $charge->label !== null ? $default : 'Per-head service';
    }

    private function rate(string $key, string $field, float $default): float
    {
        $value = RateConfig::value($key, []);

        return (float) (is_array($value) ? ($value[$field] ?? $default) : $default);
    }
}
