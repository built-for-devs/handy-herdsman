<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use Carbon\CarbonImmutable;

/**
 * One profitability line for a single appointment (a booking / trip). Profit is
 * derived entirely from records already captured — revenue from the booking's
 * payments, supply & drug cost from each visit's completion usage map ×
 * `supplies.unit_cost`, and mileage cost from the completion mileage ×
 * `rate_config` (spec §5.6c). Nothing is a separate bookkeeping entry.
 *
 * `drugCost` is the slice of supply cost from prescription items
 * (`supplies.is_prescription`); `supplyCost` is everything else. Their sum plus
 * mileage cost is the cost of goods, and profit is revenue minus that.
 */
final class AppointmentProfit
{
    public function __construct(
        public readonly int $bookingId,
        public readonly int $teamId,
        public readonly ?string $clientName,
        public readonly ?int $serviceId,
        public readonly ?string $serviceName,
        public readonly ?string $serviceType,
        public readonly float $revenue,
        public readonly float $supplyCost,
        public readonly float $drugCost,
        public readonly float $mileage,
        public readonly float $mileageCost,
        public readonly ?CarbonImmutable $date,
    ) {}

    /** Total cost of goods for the appointment: supplies + drugs + mileage. */
    public function cogs(): float
    {
        return round($this->supplyCost + $this->drugCost + $this->mileageCost, 2);
    }

    /** Profit = revenue − (supplies + drugs + mileage cost) (§5.6c). */
    public function profit(): float
    {
        return round($this->revenue - $this->cogs(), 2);
    }

    /** Margin as a fraction of revenue (0 when there is no revenue). */
    public function margin(): float
    {
        return $this->revenue > 0 ? round($this->profit() / $this->revenue, 4) : 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'booking_id' => $this->bookingId,
            'team_id' => $this->teamId,
            'client_name' => $this->clientName,
            'service_id' => $this->serviceId,
            'service_name' => $this->serviceName,
            'service_type' => $this->serviceType,
            'revenue' => round($this->revenue, 2),
            'supply_cost' => round($this->supplyCost, 2),
            'drug_cost' => round($this->drugCost, 2),
            'mileage' => round($this->mileage, 2),
            'mileage_cost' => round($this->mileageCost, 2),
            'cogs' => $this->cogs(),
            'profit' => $this->profit(),
            'margin' => $this->margin(),
            'date' => $this->date?->toDateString(),
        ];
    }
}
