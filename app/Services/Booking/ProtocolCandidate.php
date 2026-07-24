<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Services\Protocol\ProtocolSchedule;

/**
 * A single viable Visit-1 date the protocol scheduler is offering, together
 * with its fully-computed downstream V2/V3 schedule and whether it falls beyond
 * the normal search horizon (spec §10b — never fail silently).
 */
final readonly class ProtocolCandidate
{
    public function __construct(
        public ProtocolSchedule $schedule,
        public bool $beyondHorizon,
    ) {}

    /** Presentation payload in the operational timezone for the date picker. */
    public function toArray(): array
    {
        $display = $this->schedule->displayTimezone();

        return [
            'visit1_at' => $this->schedule->visit1At->toIso8601String(),
            'visit1_local' => $display['visit1_at']->format('D, M j, Y g:i A'),
            'visit2_local' => $display['visit2_at']->format('D, M j, Y g:i A'),
            'visit3_recommended_local' => $display['visit3_recommended_at']->format('D, M j, Y g:i A'),
            'visit3_window_local' => sprintf(
                '%s – %s',
                $display['visit3_window_start']->format('D g:i A'),
                $display['visit3_window_end']->format('g:i A'),
            ),
            'beyond_horizon' => $this->beyondHorizon,
        ];
    }
}
