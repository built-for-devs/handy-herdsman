<?php

declare(strict_types=1);

namespace App\Services\Protocol;

use App\Models\AvailabilityRule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Detects when a (re)computed Visit 3 time falls outside Jeff's bookable hours
 * (§10b — Timing math / Scheduler). Because V3's window is physiologically
 * fixed to V2's actual timestamp, a late V2 can push V3 outside working hours;
 * that must be FLAGGED for a manual decision, never silently accepted.
 *
 * This detector checks the single most important constraint — the recommended
 * time lands inside a working day/window. The full scheduler (M6) layers on
 * blackout dates, buffers, and collisions with other bookings.
 */
class Visit3ConflictDetector
{
    /**
     * @return string|null Human-readable conflict reason, or null if the
     *                     recommended time sits inside working hours.
     */
    public function conflictReason(CarbonInterface $visit3RecommendedAt): ?string
    {
        // Evaluate against the operational timezone — working hours are local.
        $local = CarbonImmutable::instance($visit3RecommendedAt)
            ->setTimezone((string) config('protocol.timezone'));

        $rule = AvailabilityRule::query()
            ->where('day_of_week', $local->dayOfWeek)
            ->first();

        if ($rule === null || ! $rule->is_working_day) {
            return sprintf(
                'Visit 3 lands on %s, a non-working day.',
                $local->format('l')
            );
        }

        if ($rule->start_time === null || $rule->end_time === null) {
            return sprintf('No working hours are configured for %s.', $local->format('l'));
        }

        $start = $local->setTimeFromTimeString($rule->start_time);
        $end = $local->setTimeFromTimeString($rule->end_time);

        if ($local->lt($start) || $local->gt($end)) {
            return sprintf(
                'Visit 3 recommended time %s is outside working hours (%s–%s).',
                $local->format('D g:i A'),
                $start->format('g:i A'),
                $end->format('g:i A'),
            );
        }

        return null;
    }
}
