<?php

declare(strict_types=1);

namespace App\Services\Protocol;

use App\Enums\AnimalType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The shared, tested CIDR 10-day sync timing engine (spec §3, §10b — Timing math).
 *
 * A wrong assumption here causes FAILED BREEDINGS, not bugs — this is the
 * highest-consequence code in the system. It powers the public AI Timing
 * Calculator (2.5) and the booking scheduler (6.2).
 *
 * Invariants (all enforced here):
 *  - V1 → V2 is exactly 7 days, added as an ABSOLUTE duration.
 *  - V3 window is 60–66h (cow) / 52–56h (heifer) measured from V2's timestamp.
 *  - Recommended AI time is the MIDPOINT of the window (cow ≈63h, heifer ≈54h).
 *  - All arithmetic is performed in UTC (no DST), so a DST boundary can never
 *    shift a window. Callers display in America/Chicago via ProtocolSchedule.
 *  - Every timing constant comes from `config/protocol.php` — no literals.
 */
class ProtocolTimingService
{
    /**
     * Compute the full three-visit schedule from a proposed Visit 1 time.
     */
    public function scheduleFromVisit1(CarbonInterface $visit1At, AnimalType $animalType): ProtocolSchedule
    {
        $v1 = $this->toUtc($visit1At);
        $v2 = $v1->addDays($this->v1ToV2Days());

        $window = $this->visit3Window($v2, $animalType);

        return new ProtocolSchedule(
            visit1At: $v1,
            visit2At: $v2,
            visit3WindowStart: $window['start'],
            visit3WindowEnd: $window['end'],
            visit3RecommendedAt: $window['recommended'],
        );
    }

    /**
     * Recompute the Visit 3 window from Visit 2's ACTUAL completed timestamp
     * (§10b). If V2 ran 2h late, V3 shifts 2h. This is the common real-world
     * case and is invoked automatically on V2 completion (see 1.3).
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable, recommended: CarbonImmutable}
     */
    public function recomputeVisit3(CarbonInterface $visit2CompletedAt, AnimalType $animalType): array
    {
        return $this->visit3Window($this->toUtc($visit2CompletedAt), $animalType);
    }

    /**
     * The V3 window measured as absolute-hour offsets from a V2 timestamp.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable, recommended: CarbonImmutable}
     */
    private function visit3Window(CarbonImmutable $visit2At, AnimalType $animalType): array
    {
        if (! $animalType->canBeBred()) {
            throw new \InvalidArgumentException(
                "Cannot compute a breeding window for a {$animalType->value}."
            );
        }

        $config = (array) config('protocol.windows.'.$animalType->windowKey());
        $startHours = (int) $config['start_hours'];
        $endHours = (int) $config['end_hours'];

        // Offsets are added in whole MINUTES as absolute durations in UTC so
        // the midpoint (e.g. 63h for a cow) stays exact and DST-independent.
        $start = $visit2At->addMinutes($startHours * 60);
        $end = $visit2At->addMinutes($endHours * 60);
        $recommended = $visit2At->addMinutes((int) round(($startHours + $endHours) / 2 * 60));

        return compact('start', 'end', 'recommended');
    }

    private function v1ToV2Days(): int
    {
        return (int) config('protocol.v1_to_v2_days');
    }

    /**
     * Normalise any input to an immutable UTC instant. All window arithmetic
     * happens here — in UTC there is no DST, so day/hour offsets are exact
     * absolute durations (§10b — the exact bug that silently ruins a breeding).
     */
    private function toUtc(CarbonInterface $moment): CarbonImmutable
    {
        return CarbonImmutable::instance($moment)->setTimezone('UTC');
    }
}
