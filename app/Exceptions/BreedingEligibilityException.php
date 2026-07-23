<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Cattle;
use App\Models\Service;
use RuntimeException;

/**
 * Thrown when a breeding/AI service is attempted against an ineligible animal
 * (a bull or steer) — §10b — Animal type. Carries a client-facing message.
 */
class BreedingEligibilityException extends RuntimeException
{
    public static function for(Cattle $cattle, Service $service): self
    {
        $type = $cattle->animal_type->label();
        $name = $cattle->reg_name ?: 'This animal';

        return new self(
            "{$name} is a {$type} and cannot be booked for “{$service->name}”. "
            .'Breeding services are only available for heifers and cows.'
        );
    }
}
