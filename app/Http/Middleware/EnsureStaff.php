<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates staff-only admin screens (inventory & custody). Checks the staff role
 * straight off the role pivot so tenant (team) scoping never locks Jeff/Tessa
 * out of their own back office.
 */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->isStaff($user->getKey())) {
            abort(403);
        }

        return $next($request);
    }

    private function isStaff(int|string $userId): bool
    {
        $modelHasRoles = config('permission.table_names.model_has_roles');
        $roles = config('permission.table_names.roles');

        return DB::table($modelHasRoles)
            ->join($roles, "$roles.id", '=', "$modelHasRoles.role_id")
            ->where("$roles.name", 'staff')
            ->where("$modelHasRoles.model_id", $userId)
            ->exists();
    }
}
