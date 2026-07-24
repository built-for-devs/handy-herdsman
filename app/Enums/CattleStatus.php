<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cattle lifecycle status (spec §10b — Cattle status). `inactive` covers sold,
 * deceased, culled, or simply out of the program — no separate states needed.
 * Marking a cow `inactive` STOPS all pending reminders for her immediately;
 * she stays in records and history (nothing hard-deletes).
 */
enum CattleStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }
}
