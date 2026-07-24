<?php

declare(strict_types=1);

namespace App\Http\Controllers\Team;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreTeamInvitationRequest;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Team invitations — owner invites a member (spouse) or vet (spec §4, §10b).
 * Vets are read-only with a client-selected scope and never receive automated
 * notifications. The invite email is a queued notification.
 */
class TeamInvitationController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);
        $this->authorizeOwner($request, $team);

        return Inertia::render('team/Invitations', [
            'invitations' => $team->invitations()
                ->latest()
                ->get()
                ->map(fn (TeamInvitation $invitation) => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'role_label' => $invitation->role->label(),
                    'status' => $invitation->status,
                    'vet_scope' => $invitation->vet_scope,
                    'created_at' => $invitation->created_at,
                ]),
            'recordTypes' => config('clients.vet_scopeable_record_types'),
        ]);
    }

    public function store(StoreTeamInvitationRequest $request): RedirectResponse
    {
        $team = $this->currentTeam($request);
        $this->authorizeOwner($request, $team);

        $role = TeamRole::from($request->string('role')->value());

        $invitation = $team->invitations()->create([
            'invited_by' => $request->user()->id,
            'email' => $request->string('email'),
            'role' => $role,
            'token' => TeamInvitation::generateToken(),
            'status' => 'pending',
            'vet_scope' => $role === TeamRole::Vet ? $request->input('vet_scope') : null,
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitationNotification($invitation));

        return back();
    }

    public function destroy(Request $request, TeamInvitation $invitation): RedirectResponse
    {
        $this->authorizeOwner($request, $invitation->team);

        $invitation->update(['status' => 'revoked']);
        $invitation->delete(); // soft delete — history preserved (§rules)

        return back();
    }

    public function show(Request $request, string $token): Response
    {
        $invitation = TeamInvitation::where('token', $token)->firstOrFail();

        return Inertia::render('team/AcceptInvitation', [
            'invitation' => [
                'team_name' => $invitation->team->name,
                'role' => $invitation->role->value,
                'role_label' => $invitation->role->label(),
                'email' => $invitation->email,
                'status' => $invitation->status,
                'vet_scope' => $invitation->vet_scope,
                'token' => $invitation->token,
            ],
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = TeamInvitation::where('token', $token)->firstOrFail();
        $user = $request->user();

        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'invitation' => 'This invitation is no longer valid.',
            ]);
        }

        if (mb_strtolower($invitation->email) !== mb_strtolower($user->email)) {
            throw ValidationException::withMessages([
                'invitation' => 'This invitation was sent to a different email address.',
            ]);
        }

        // Grant the Spatie role within the team's context (§4).
        app(PermissionRegistrar::class)->setPermissionsTeamId($invitation->team_id);
        $user->assignRole($invitation->role->permissionRole());

        $invitation->update([
            'status' => 'accepted',
            'accepted_by' => $user->id,
            'accepted_at' => now(),
        ]);

        return to_route('dashboard');
    }

    private function currentTeam(Request $request): Team
    {
        $team = $request->user()->currentTeam;

        abort_if($team === null, HttpResponse::HTTP_FORBIDDEN);

        return $team;
    }

    private function authorizeOwner(Request $request, Team $team): void
    {
        $user = $request->user();

        abort_unless(
            $team->owner_id === $user->id || $user->isStaff(),
            HttpResponse::HTTP_FORBIDDEN,
        );
    }
}
