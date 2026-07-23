<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles (spec §4). Created as GLOBAL roles (team_id null) so they exist once
 * and are assigned per-team at runtime via Spatie teams mode.
 *
 *  - staff        : Jeff / Tessa — global, see everything, manage config.
 *  - client_owner : owns their team; full access; invites members.
 *  - client_member: invited (spouse); full access to that team's records.
 *  - vet          : read-only on cattle profiles; client-controlled scope.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles in the global (null) team context.
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        foreach (['staff', 'client_owner', 'client_member', 'vet'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
