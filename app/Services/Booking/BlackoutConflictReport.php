<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Models\Booking;

/**
 * The result of dropping a blackout over existing bookings (spec §6.3):
 *
 *  - rescheduled: bookings not yet past Visit 1 — the client is notified with
 *    proposed alternative slots that still satisfy protocol timing.
 *  - manualConflicts: protocols already PAST Visit 1 (CIDR in) — physiologically
 *    fixed, cannot be auto-moved, surfaced to Jeff as a manual decision.
 */
final readonly class BlackoutConflictReport
{
    /**
     * @param  list<Booking>  $rescheduled
     * @param  list<Booking>  $manualConflicts
     */
    public function __construct(
        public array $rescheduled,
        public array $manualConflicts,
    ) {}

    public function hasConflicts(): bool
    {
        return $this->rescheduled !== [] || $this->manualConflicts !== [];
    }
}
