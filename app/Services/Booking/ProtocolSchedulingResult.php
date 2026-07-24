<?php

declare(strict_types=1);

namespace App\Services\Booking;

/**
 * The outcome of a protocol date search (spec §6.2, §10b). Always carries an
 * explicit message and — critically — NEVER represents a silent empty picker:
 * when nothing is bookable inside the horizon it still returns the next viable
 * dates beyond it (or says none exist at all), so the client is never left
 * staring at an empty calendar with no explanation.
 */
final readonly class ProtocolSchedulingResult
{
    /**
     * @param  list<ProtocolCandidate>  $candidates
     */
    public function __construct(
        public array $candidates,
        public bool $hasWithinHorizon,
        public int $horizonDays,
        public string $message,
    ) {}

    public function isEmpty(): bool
    {
        return $this->candidates === [];
    }

    public function toArray(): array
    {
        return [
            'candidates' => array_map(fn (ProtocolCandidate $c) => $c->toArray(), $this->candidates),
            'has_within_horizon' => $this->hasWithinHorizon,
            'horizon_days' => $this->horizonDays,
            'message' => $this->message,
        ];
    }
}
