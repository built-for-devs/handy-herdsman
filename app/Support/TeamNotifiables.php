<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves who receives an automated client notification for a category
 * (spec §10b — Notification recipients):
 *
 *  - the team OWNER receives all categories by default,
 *  - invited MEMBERS receive nothing until they opt in per category,
 *  - VETS NEVER receive automated notifications.
 */
final class TeamNotifiables
{
    /** @return Collection<int, User> */
    public static function recipientsForCategory(Team $team, int $category): Collection
    {
        $recipients = collect();

        if ($team->owner !== null) {
            $recipients->push($team->owner);
        }

        $memberIds = TeamInvitation::query()
            ->where('team_id', $team->id)
            ->where('role', 'member')
            ->where('status', 'accepted')
            ->whereNotNull('accepted_by')
            ->pluck('accepted_by');

        $optedInMembers = User::query()
            ->whereIn('id', $memberIds)
            ->get()
            ->filter(fn (User $user) => $user->optedIntoCategory($category));

        return $recipients
            ->merge($optedInMembers)
            ->unique(fn (User $user) => $user->id)
            ->values();
    }
}
