<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Visit lifecycle (spec §10b — Rescheduling, failed visits). A failed/aborted
 * visit (cow won't load, no chute, not contained) is STILL billed at the
 * normal visit rate — Jeff's judgment, no automated trip-fee logic.
 */
enum VisitStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
