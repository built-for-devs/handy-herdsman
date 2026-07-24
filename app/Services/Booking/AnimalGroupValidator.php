<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Enums\AnimalType;
use App\Exceptions\BookingValidationException;
use App\Exceptions\BreedingEligibilityException;
use App\Exceptions\MixedGroupException;
use App\Models\Cattle;
use App\Models\Service;
use Illuminate\Support\Collection;

/**
 * Validates the SET of animals on a booking against the service (spec §6.4).
 *
 *  - Standard/per-head services carry many animals on one booking (one visit,
 *    priced per head with a single-visit minimum) — any mix is fine.
 *  - Breeding services reject bulls/steers (they cannot be bred).
 *  - AI/protocol bookings additionally require ONE window type: all cows OR all
 *    heifers. A cow+heifer mix is BLOCKED with a split suggestion because the
 *    two V3 windows are ~8h apart.
 */
class AnimalGroupValidator
{
    /**
     * Ensure the animal group is valid for the service. For a protocol booking
     * returns the single shared window type; otherwise null.
     *
     * @param  Collection<int, Cattle>  $cattle
     */
    public function validate(Service $service, Collection $cattle): ?AnimalType
    {
        if ($cattle->isEmpty()) {
            throw new BookingValidationException('Select at least one animal for this booking.');
        }

        if ($service->breedsAnimal()) {
            foreach ($cattle as $animal) {
                $type = $animal->animal_type;

                if (! $type instanceof AnimalType || ! $type->canBeBred()) {
                    throw BreedingEligibilityException::for($animal, $service);
                }
            }
        }

        // Only true breeding protocols are window-constrained. Standard/per-head
        // services (vaccinations, preg-checks) can carry any mix on one visit.
        if ($service->type !== 'protocol') {
            return null;
        }

        return $this->uniformWindowType($cattle);
    }

    /**
     * @param  Collection<int, Cattle>  $cattle
     */
    private function uniformWindowType(Collection $cattle): AnimalType
    {
        $windowKeys = $cattle
            ->map(fn (Cattle $c) => $c->animal_type->windowKey())
            ->unique()
            ->values();

        if ($windowKeys->count() > 1) {
            throw MixedGroupException::from($cattle);
        }

        return $cattle->first()->animal_type;
    }
}
