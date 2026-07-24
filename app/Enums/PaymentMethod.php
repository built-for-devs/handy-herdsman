<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a booking is paid (spec §5.5, §5.6). Card is captured at booking and
 * charged on confirm; cash skips the charge and is settled manually by Jeff.
 */
enum PaymentMethod: string
{
    case Card = 'card';
    case Cash = 'cash';
}
