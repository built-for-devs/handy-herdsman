<?php

declare(strict_types=1);

namespace App\Services\Protocol;

use App\Exceptions\BreedingEligibilityException;
use App\Models\Cattle;
use App\Models\Service;

/**
 * Gate for breeding/AI service eligibility (§10b — Animal type). bull/steer are
 * blocked from any breeding service with a clear validation message; heifers
 * and cows are allowed.
 */
class BreedingEligibility
{
    /**
     * True when the animal may receive the given service. Non-breeding services
     * are always allowed (a bull can still be weighed or vaccinated).
     */
    public function isEligible(Cattle $cattle, Service $service): bool
    {
        if (! $service->breedsAnimal()) {
            return true;
        }

        return $cattle->animal_type->canBeBred();
    }

    /**
     * @throws BreedingEligibilityException when the animal cannot receive the service.
     */
    public function assertEligible(Cattle $cattle, Service $service): void
    {
        if (! $this->isEligible($cattle, $service)) {
            throw BreedingEligibilityException::for($cattle, $service);
        }
    }
}
