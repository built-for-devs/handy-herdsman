<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Booking lifecycle (spec §5.5, §10b). A booking is created immediately:
 *
 *  provisional → held for staff review (first-timers, 24h SLA), visible to
 *                the client as provisional until reviewed.
 *  confirmed   → active clients self-confirm after validation; or staff
 *                approves a provisional booking. The charge fires on confirm.
 *  declined    → staff declined a provisional booking (too far / no chute).
 *  cancelled   → cancelled before Visit 1 (protocols lock once V1 occurs).
 */
enum BookingStatus: string
{
    case Provisional = 'provisional';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Provisional => 'Provisional — pending review',
            self::Confirmed => 'Confirmed',
            self::Declined => 'Declined',
            self::Cancelled => 'Cancelled',
        };
    }
}
