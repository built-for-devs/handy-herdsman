<?php

declare(strict_types=1);

namespace App\Services\Booking;

/**
 * The distance/service-area outcome for a client (spec §2, §6.6, §10b — Money).
 * The distance fee is PER BOOKING/PROTOCOL — a 20-mile client pays ONE $30 for
 * a whole three-visit protocol, never $30 × 3.
 */
final readonly class ServiceAreaResult
{
    public function __construct(
        public ?float $distanceMiles,
        public bool $inRange,
        public bool $feeApplies,
        public float $feeAmount,
        public bool $declined,
        public ?string $zoneLabel,
    ) {}

    public function toArray(): array
    {
        return [
            'distance_miles' => $this->distanceMiles,
            'in_range' => $this->inRange,
            'fee_applies' => $this->feeApplies,
            'fee_amount' => $this->feeAmount,
            'declined' => $this->declined,
            'zone_label' => $this->zoneLabel,
        ];
    }
}
