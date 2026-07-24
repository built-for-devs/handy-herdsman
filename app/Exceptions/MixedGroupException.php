<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\AnimalType;
use App\Models\Cattle;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Thrown when a mixed cow + heifer group is booked onto ONE AI/protocol booking
 * (spec §6.4, §10b). Their Visit 3 windows are ~8 hours apart (cow 60–66h vs
 * heifer 52–56h after V2), so a single farm call cannot serve both. Carries the
 * per-type split so the UI can offer to split into separate bookings.
 */
class MixedGroupException extends RuntimeException
{
    /**
     * @param  array<string, list<int>>  $splitGroups  window key => cattle ids
     */
    public function __construct(
        string $message,
        public readonly array $splitGroups,
    ) {
        parent::__construct($message);
    }

    /**
     * @param  Collection<int, Cattle>  $cattle
     */
    public static function from(Collection $cattle): self
    {
        $groups = [];

        foreach ($cattle as $animal) {
            $type = $animal->animal_type;

            if ($type instanceof AnimalType && $type->canBeBred()) {
                $groups[$type->windowKey()][] = $animal->id;
            }
        }

        return new self(
            'This booking mixes cows and heifers. Their timed-AI windows fall about '
            .'8 hours apart, so they cannot share one protocol visit. Split them into '
            .'separate bookings — one for the cows and one for the heifers.',
            $groups,
        );
    }
}
