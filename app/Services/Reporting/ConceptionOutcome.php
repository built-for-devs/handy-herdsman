<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\BcsBucket;
use App\Enums\PregCheckState;
use App\Models\PregCheck;
use Carbon\CarbonImmutable;

/**
 * One conception data point: a final preg-check result matched to the breeding
 * that produced it (spec §5.6c, issue #249). Derived entirely from records
 * already captured — the final {@see PregCheck} state, the sire /
 * breed from the breeding visit's semen lot, the animal type at breeding, the
 * protocol flavour, the season, and the BCS recorded on that breeding visit.
 *
 * `settled` (state = bred) is a pregnancy; `open` is a miss. `recheck` is
 * inconclusive and is excluded from the conception-rate denominator.
 */
final class ConceptionOutcome
{
    public function __construct(
        public readonly int $cattleId,
        public readonly int $teamId,
        public readonly ?string $clientName,
        public readonly int $breedingVisitId,
        public readonly ?string $sire,
        public readonly ?string $breed,
        public readonly ?string $animalType,
        public readonly string $protocolType,
        public readonly string $season,
        public readonly ?int $bcs,
        public readonly PregCheckState $state,
        public readonly ?CarbonImmutable $bredAt,
    ) {}

    /** A pregnancy — the animal settled. */
    public function settled(): bool
    {
        return $this->state === PregCheckState::Bred;
    }

    /** Counts toward a conception rate (a definitive bred/open result). */
    public function evaluated(): bool
    {
        return $this->state === PregCheckState::Bred || $this->state === PregCheckState::Open;
    }

    public function bcsBucket(): ?BcsBucket
    {
        return $this->bcs !== null ? BcsBucket::forScore($this->bcs) : null;
    }

    /** Cow vs. heifer (§5.6c) — normalised, never null for a breeding female. */
    public function cowOrHeifer(): string
    {
        return $this->animalType ?? 'unknown';
    }
}
