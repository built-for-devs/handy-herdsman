<?php

declare(strict_types=1);

namespace App\Services\Booking;

/**
 * A transparent price breakdown shown BEFORE the client confirms (spec §6.6,
 * §10b — Money). The distance fee is a single per-booking line, never per visit.
 */
final readonly class BookingQuote
{
    public function __construct(
        public float $base,
        public float $additionalAnimals,
        public float $distanceFee,
        public int $headcount,
        public bool $visitMinimumApplied,
    ) {}

    public function total(): float
    {
        return round($this->base + $this->additionalAnimals + $this->distanceFee, 2);
    }

    public function toArray(): array
    {
        return [
            'base' => $this->base,
            'additional_animals' => $this->additionalAnimals,
            'distance_fee' => $this->distanceFee,
            'headcount' => $this->headcount,
            'visit_minimum_applied' => $this->visitMinimumApplied,
            'total' => $this->total(),
        ];
    }
}
