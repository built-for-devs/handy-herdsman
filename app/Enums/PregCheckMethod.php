<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a pregnancy check is performed (spec §5.5, §10b). Blood is a two-stage
 * async event (draw now → lab result later); palpation (after ~4 months) is
 * immediate and definitive at the visit — no pending state, no lab fee.
 */
enum PregCheckMethod: string
{
    case Blood = 'blood';
    case Palpation = 'palpation';

    /** Only blood draws can be sent for the optional (+fee) lab confirmation. */
    public function supportsLab(): bool
    {
        return $this === self::Blood;
    }

    /** Palpation is immediate/definitive; blood starts as `pending`. */
    public function isImmediate(): bool
    {
        return $this === self::Palpation;
    }
}
