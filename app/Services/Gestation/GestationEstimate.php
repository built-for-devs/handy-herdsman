<?php

declare(strict_types=1);

namespace App\Services\Gestation;

use Carbon\CarbonImmutable;

/**
 * Immutable due-date estimate (§5.4b). ALWAYS a range, never a hard date —
 * individual variation of ±5 days is normal, so copy should say "expected
 * around X". `milestones` are the calving-prep countdown dates.
 */
final readonly class GestationEstimate
{
    /**
     * @param  array<string, CarbonImmutable>  $milestones  keyed by milestone name
     */
    public function __construct(
        public CarbonImmutable $breedingDate,
        public int $gestationDays,
        public CarbonImmutable $estimatedDueDate,
        public CarbonImmutable $windowStart,
        public CarbonImmutable $windowEnd,
        public array $milestones,
    ) {}

    /**
     * Bull calves tend to gestate ~1–1.5 days longer, but calf sex is unknown
     * in advance — this is surfaced as UI copy, NOT folded into the math (§5.4b).
     */
    public function caveat(): string
    {
        return 'Expected around '.$this->estimatedDueDate->format('M j, Y')
            .' (between '.$this->windowStart->format('M j').' and '
            .$this->windowEnd->format('M j').'). This is an estimate — individual '
            .'variation of ±5 days is normal, and bull calves tend to arrive slightly '
            .'later. Watch for calving signs as the window approaches.';
    }
}
