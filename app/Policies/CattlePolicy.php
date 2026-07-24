<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cattle;
use App\Models\User;
use App\Support\TeamAccess;

/**
 * Cattle authorization (spec §4, §10b). Staff see everything. Owners and
 * members have full access to their team's animals. An invited vet is
 * READ-ONLY and only sees a profile when the client granted profile scope —
 * vets can never create, update, or delete.
 */
class CattlePolicy
{
    /** Staff (Jeff / Tessa) bypass tenant scoping entirely. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isStaff() ? true : null;
    }

    public function view(User $user, Cattle $cattle): bool
    {
        if (TeamAccess::hasFullAccess($user, $cattle->team)) {
            return true;
        }

        return TeamAccess::isVet($user, $cattle->team)
            && TeamAccess::vetCanViewProfile($user, $cattle->team);
    }

    public function create(User $user, Cattle $cattle): bool
    {
        return TeamAccess::hasFullAccess($user, $cattle->team);
    }

    public function update(User $user, Cattle $cattle): bool
    {
        return TeamAccess::hasFullAccess($user, $cattle->team);
    }

    public function delete(User $user, Cattle $cattle): bool
    {
        return TeamAccess::hasFullAccess($user, $cattle->team);
    }
}
