<?php

declare(strict_types=1);

namespace App\Services\Gestation;

use App\Enums\AnimalType;
use App\Models\GestationConfig;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Config-driven due-date estimator (§5.4b). Powers the public Due Date
 * Calculator (2.6) and the stored due date that drives calving reminders
 * (§9.3). The breed table and heifer offset are EDITABLE DATA in the
 * `gestation_config` table — no deploy to change them.
 *
 * Output is ALWAYS a range/estimate, never a hard date.
 */
class GestationService
{
    /**
     * Estimate the due date and calving-prep milestones for a breeding.
     *
     * @param  AnimalType|null  $animalType  heifer applies the (earlier) offset;
     *                                       cow/null does not.
     */
    public function estimate(
        CarbonInterface $breedingDate,
        ?string $breed = null,
        ?AnimalType $animalType = null,
    ): GestationEstimate {
        $bred = CarbonImmutable::instance($breedingDate)->startOfDay();
        $days = $this->gestationDaysFor($breed, $animalType);

        // Whole-day arithmetic; a due date is a calendar day, not an instant.
        $dueDate = $bred->addDays($days);

        $spread = (int) config('gestation.estimate_spread_days');

        return new GestationEstimate(
            breedingDate: $bred,
            gestationDays: $days,
            estimatedDueDate: $dueDate,
            windowStart: $dueDate->subDays($spread),
            windowEnd: $dueDate->addDays($spread),
            milestones: $this->milestones($dueDate),
        );
    }

    /**
     * The stored due date used to drive countdown reminders once a pregnancy
     * is confirmed (§5.4b / §9.3) — the point estimate, not the range.
     */
    public function storedDueDate(
        CarbonInterface $breedingDate,
        ?string $breed = null,
        ?AnimalType $animalType = null,
    ): CarbonImmutable {
        return $this->estimate($breedingDate, $breed, $animalType)->estimatedDueDate;
    }

    /**
     * Resolve gestation length: breed lookup with a fall-back to the configured
     * default (283) for unknown/crossbreed, plus the heifer offset if applicable.
     */
    public function gestationDaysFor(?string $breed, ?AnimalType $animalType = null): int
    {
        $days = $this->breedDays($breed) ?? $this->defaultDays();

        if ($animalType === AnimalType::Heifer) {
            $days += $this->heiferOffset();
        }

        return $days;
    }

    private function breedDays(?string $breed): ?int
    {
        if ($breed === null || $breed === '') {
            return null;
        }

        $row = GestationConfig::query()->where('breed', $breed)->first();

        return $row?->gestation_days;
    }

    private function defaultDays(): int
    {
        return GestationConfig::query()
            ->where('breed', GestationConfig::DEFAULT_KEY)
            ->value('gestation_days') ?? 283;
    }

    private function heiferOffset(): int
    {
        return GestationConfig::query()
            ->where('breed', GestationConfig::HEIFER_OFFSET_KEY)
            ->value('gestation_days') ?? 0;
    }

    /**
     * @return array<string, CarbonImmutable>
     */
    private function milestones(CarbonImmutable $dueDate): array
    {
        $milestones = [];

        foreach ((array) config('gestation.milestones') as $name => $daysBefore) {
            $milestones[$name] = $dueDate->subDays((int) $daysBefore);
        }

        return $milestones;
    }
}
