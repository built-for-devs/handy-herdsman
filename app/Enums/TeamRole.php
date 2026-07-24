<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The two roles an owner can invite into their team (spec §4).
 *
 *  member → spouse; full access to the team's records (Spatie `client_member`)
 *  vet    → read-only, client-selected scope (Spatie `vet`)
 */
enum TeamRole: string
{
    case Member = 'member';
    case Vet = 'vet';

    /** The Spatie permission role this invitation grants on acceptance. */
    public function permissionRole(): string
    {
        return match ($this) {
            self::Member => 'client_member',
            self::Vet => 'vet',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Member',
            self::Vet => 'Veterinarian (read-only)',
        };
    }
}
