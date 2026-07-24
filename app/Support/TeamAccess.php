<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;

/**
 * Resolves a user's role within a team and the read scope of an invited vet
 * (spec §4, §10b). Ownership and accepted invitations are the source of truth,
 * so authorization stays deterministic and independent of Spatie team context.
 */
final class TeamAccess
{
    /** One of: staff | owner | member | vet | null (no access). */
    public static function role(User $user, Team $team): ?string
    {
        if ($user->isStaff()) {
            return 'staff';
        }

        if ($team->owner_id === $user->id) {
            return 'owner';
        }

        $invitation = self::acceptedInvitation($user, $team);

        if ($invitation === null) {
            return null;
        }

        return $invitation->role === TeamRole::Vet ? 'vet' : 'member';
    }

    /** Full-access members of the team: staff, owner, or invited member. */
    public static function hasFullAccess(User $user, Team $team): bool
    {
        return in_array(self::role($user, $team), ['staff', 'owner', 'member'], true);
    }

    public static function isVet(User $user, Team $team): bool
    {
        return self::role($user, $team) === 'vet';
    }

    /** The client-selected read scope for a vet, or an empty scope. */
    public static function vetScope(User $user, Team $team): array
    {
        $invitation = self::acceptedInvitation($user, $team);

        if ($invitation === null || $invitation->role !== TeamRole::Vet) {
            return [];
        }

        return $invitation->vet_scope ?? [];
    }

    public static function vetCanViewProfile(User $user, Team $team): bool
    {
        return (bool) data_get(self::vetScope($user, $team), 'profile', false);
    }

    public static function vetCanViewRecordType(User $user, Team $team, string $type): bool
    {
        $scope = self::vetScope($user, $team);

        return in_array($type, (array) data_get($scope, 'record_types', []), true);
    }

    private static function acceptedInvitation(User $user, Team $team): ?TeamInvitation
    {
        return TeamInvitation::query()
            ->where('team_id', $team->id)
            ->whereRaw('lower(email) = ?', [mb_strtolower($user->email)])
            ->where('status', 'accepted')
            ->latest('accepted_at')
            ->first();
    }
}
