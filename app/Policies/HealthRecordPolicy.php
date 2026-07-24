<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HealthRecord;
use App\Models\User;
use App\Support\TeamAccess;

/**
 * Health-record authorization (spec §4, §10b). Staff see everything. Owners
 * and members have full access. An invited vet is READ-ONLY and only sees a
 * record whose `type` is in the client-selected scope — vets can never write.
 */
class HealthRecordPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isStaff() ? true : null;
    }

    public function view(User $user, HealthRecord $record): bool
    {
        if (TeamAccess::hasFullAccess($user, $record->team)) {
            return true;
        }

        return TeamAccess::isVet($user, $record->team)
            && TeamAccess::vetCanViewRecordType($user, $record->team, $record->type);
    }

    public function create(User $user, HealthRecord $record): bool
    {
        return TeamAccess::hasFullAccess($user, $record->team);
    }

    /**
     * Owners/members may edit their own team's records EXCEPT staff-added ones —
     * Jeff's farm records are his professional record, audit-protected (§10b).
     * Staff bypass this via before(), so they can edit client-added records.
     */
    public function update(User $user, HealthRecord $record): bool
    {
        return TeamAccess::hasFullAccess($user, $record->team)
            && ! $record->isStaffAuthored();
    }

    public function delete(User $user, HealthRecord $record): bool
    {
        return TeamAccess::hasFullAccess($user, $record->team)
            && ! $record->isStaffAuthored();
    }
}
