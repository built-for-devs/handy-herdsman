<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Helpers for the Spatie teams-mode role setup used across M3 tests (§4).
 */
trait InteractsWithRoles
{
    protected function seedRoles(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        foreach (['staff', 'client_owner', 'client_member', 'vet'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Flag the user as global staff (§4). */
    protected function makeStaff(User $user): User
    {
        $user->forceFill(['is_staff' => true])->save();

        return $user->fresh();
    }
}
