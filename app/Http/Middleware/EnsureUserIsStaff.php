<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate staff-only admin areas (§4). `staff` is a platform-wide role: staff are
 * Jeff/Tessa, not tenants, so the check is team-agnostic — a user is staff if
 * they hold the `staff` role in ANY team context (the Spatie teams relation is
 * team-scoped, so we look at the pivot directly).
 */
class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $isStaff = DB::table(config('permission.table_names.model_has_roles'))
            ->where(config('permission.column_names.model_morph_key'), $user->getKey())
            ->where('model_type', $user->getMorphClass())
            ->whereIn('role_id', fn ($query) => $query
                ->select('id')
                ->from(config('permission.table_names.roles'))
                ->where('name', 'staff')
            )
            ->exists();

        abort_unless($isStaff, 403);

        return $next($request);
    }
}
