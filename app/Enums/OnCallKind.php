<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * The two expedited on-call request types (spec §3, §5.5, §6.5). Standing heat
 * follows the AM/PM breeding rule; a calving emergency is minutes-to-hours.
 */
enum OnCallKind: string
{
    case StandingHeat = 'standing_heat';
    case CalvingEmergency = 'calving_emergency';

    public function label(): string
    {
        return match ($this) {
            self::StandingHeat => 'Standing heat',
            self::CalvingEmergency => 'Calving emergency',
        };
    }

    /**
     * The AM/PM breeding-rule guidance for a standing-heat observation: heat
     * seen in the AM → breed that PM; seen in the PM → breed next AM (§3).
     */
    public function breedingGuidance(CarbonInterface $observedAt): string
    {
        if ($this !== self::StandingHeat) {
            return 'Respond as soon as possible — a calving emergency is time-critical.';
        }

        return $observedAt->format('A') === 'AM'
            ? 'Standing heat observed in the morning — breed this afternoon (AM → PM rule).'
            : 'Standing heat observed in the afternoon/evening — breed tomorrow morning (PM → next AM rule).';
    }
}
