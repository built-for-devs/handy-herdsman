<?php

declare(strict_types=1);

namespace App\Services\Protocol;

use Carbon\CarbonImmutable;

/**
 * Immutable result of the CIDR 10-day sync timing computation (§3, §10b).
 *
 * All timestamps are held in UTC (arithmetic is done in UTC so a DST boundary
 * can never shift a window). Use {@see displayTimezone()} to render in the
 * operational zone (America/Chicago).
 */
final readonly class ProtocolSchedule
{
    public function __construct(
        public CarbonImmutable $visit1At,
        public CarbonImmutable $visit2At,
        public CarbonImmutable $visit3WindowStart,
        public CarbonImmutable $visit3WindowEnd,
        public CarbonImmutable $visit3RecommendedAt,
    ) {}

    /**
     * Copies of every timestamp converted to the given display timezone
     * (defaults to the configured operational zone). Storage stays UTC.
     *
     * @return array{
     *     visit1_at: CarbonImmutable, visit2_at: CarbonImmutable,
     *     visit3_window_start: CarbonImmutable, visit3_window_end: CarbonImmutable,
     *     visit3_recommended_at: CarbonImmutable
     * }
     */
    public function displayTimezone(?string $timezone = null): array
    {
        $tz = $timezone ?? (string) config('protocol.timezone');

        return [
            'visit1_at' => $this->visit1At->setTimezone($tz),
            'visit2_at' => $this->visit2At->setTimezone($tz),
            'visit3_window_start' => $this->visit3WindowStart->setTimezone($tz),
            'visit3_window_end' => $this->visit3WindowEnd->setTimezone($tz),
            'visit3_recommended_at' => $this->visit3RecommendedAt->setTimezone($tz),
        ];
    }
}
