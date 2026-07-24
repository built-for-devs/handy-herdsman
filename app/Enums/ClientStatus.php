<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Client lifecycle status — gates self-booking (spec §10b — Client status).
 *
 *  new      → first booking requires staff review (24h SLA)
 *  active   → promoted automatically after ONE completed visit; books freely
 *  inactive → after the idle threshold; reverts to review on the next booking
 */
enum ClientStatus: string
{
    case New = 'new';
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * Whether a booking made in this status must be held for staff review.
     * `new` (never worked with) and `inactive` (idle >1yr) both require it;
     * an `active` client self-confirms.
     */
    public function requiresBookingReview(): bool
    {
        return $this !== self::Active;
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }
}
