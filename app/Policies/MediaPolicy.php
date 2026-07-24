<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Media;
use App\Models\User;
use App\Support\TeamAccess;

/**
 * Media authorization (spec §4, §10b). Staff see and manage everything. Owners
 * and members manage their own team's media, but — mirroring health records —
 * they CANNOT delete staff-uploaded media (Jeff's appointment photos are part
 * of his professional record). Invited vets are read-only, gated on profile
 * scope, and can never upload or delete.
 */
class MediaPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isStaff() ? true : null;
    }

    public function view(User $user, Media $media): bool
    {
        $team = $media->resolveTeam();

        if ($team === null) {
            return false;
        }

        if (TeamAccess::hasFullAccess($user, $team)) {
            return true;
        }

        return TeamAccess::isVet($user, $team)
            && TeamAccess::vetCanViewProfile($user, $team);
    }

    public function delete(User $user, Media $media): bool
    {
        $team = $media->resolveTeam();

        return $team !== null
            && TeamAccess::hasFullAccess($user, $team)
            && ! $media->isStaffUploaded();
    }
}
