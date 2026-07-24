<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Team;
use App\Support\TeamAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Shared tenant-context helpers for the herd records portal (§4). Resolves the
 * request's current team and the author role stamped on records/media so
 * attribution and the client-vs-staff permission rules stay consistent (§10b).
 */
trait ResolvesCurrentTeam
{
    protected function currentTeam(Request $request): Team
    {
        $team = $request->user()->currentTeam;

        abort_if($team === null, HttpResponse::HTTP_FORBIDDEN);

        return $team;
    }

    /** The role to stamp on a record/upload authored by this user in this team. */
    protected function authorRole(Request $request, Team $team): string
    {
        return TeamAccess::role($request->user(), $team) ?? 'member';
    }
}
