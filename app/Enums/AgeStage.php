<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Computed age-stage label derived from `cattle.dob` (§10b — Animal type).
 * NEVER stored: deriving it from `dob` keeps it correct as the animal ages
 * with zero maintenance. Thresholds are config (`protocol.age_stages`).
 */
enum AgeStage: string
{
    case Calf = 'calf';
    case Weanling = 'weanling';
    case Adult = 'adult';

    /**
     * Derive the stage from a date of birth, as of a given moment (default now).
     * A null dob yields Adult — we can't compute a stage, so fall back to the
     * plain animal type rather than mislabel a mature animal as a calf.
     */
    public static function fromDob(?CarbonInterface $dob, ?CarbonInterface $asOf = null): self
    {
        if ($dob === null) {
            return self::Adult;
        }

        $asOf ??= Carbon::now();
        $months = $dob->diffInMonths($asOf);

        $calfMax = (int) config('protocol.age_stages.calf_max_months');
        $weanlingMax = (int) config('protocol.age_stages.weanling_max_months');

        return match (true) {
            $months < $calfMax => self::Calf,
            $months < $weanlingMax => self::Weanling,
            default => self::Adult,
        };
    }

    /**
     * Human label combining the age stage with the underlying animal type,
     * e.g. "heifer calf", "bull calf", "weanling", or the plain type when adult.
     */
    public function label(AnimalType $type): string
    {
        return match ($this) {
            self::Calf => "{$type->value} calf",
            self::Weanling => 'weanling',
            self::Adult => $type->value,
        };
    }
}
