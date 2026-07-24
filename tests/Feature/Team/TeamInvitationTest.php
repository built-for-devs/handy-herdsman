<?php

declare(strict_types=1);

namespace Tests\Feature\Team;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * Team invitations — spouse (member) + vet (spec §4, §10b). Owner-only; vet
 * carries a client-selected read scope; invite email is a queued notification.
 */
class TeamInvitationTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function owner(): User
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        return $owner->fresh();
    }

    public function test_owner_can_invite_a_member(): void
    {
        Notification::fake();
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post(route('team-invitations.store'), [
                'email' => 'spouse@example.com',
                'role' => 'member',
            ])
            ->assertRedirect();

        $invitation = TeamInvitation::firstOrFail();
        $this->assertSame(TeamRole::Member, $invitation->role);
        $this->assertSame('pending', $invitation->status);
        $this->assertNull($invitation->vet_scope);

        Notification::assertSentOnDemand(TeamInvitationNotification::class);
    }

    public function test_owner_can_invite_a_vet_with_a_read_scope(): void
    {
        Notification::fake();
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('team-invitations.store'), [
            'email' => 'vet@example.com',
            'role' => 'vet',
            'vet_scope' => [
                'profile' => true,
                'record_types' => ['vaccination', 'preg_check'],
            ],
        ])->assertRedirect();

        $invitation = TeamInvitation::firstOrFail();
        $this->assertSame(TeamRole::Vet, $invitation->role);
        $this->assertTrue($invitation->vet_scope['profile']);
        $this->assertSame(['vaccination', 'preg_check'], $invitation->vet_scope['record_types']);
    }

    public function test_vet_invitation_requires_a_non_empty_scope(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('team-invitations.store'), [
            'email' => 'vet@example.com',
            'role' => 'vet',
            'vet_scope' => ['profile' => false, 'record_types' => []],
        ])->assertSessionHasErrors('vet_scope');
    }

    public function test_non_owner_cannot_invite(): void
    {
        $this->owner();
        $stranger = User::factory()->create();
        $otherTeam = Team::factory()->create(['owner_id' => $stranger->id]);
        $stranger->forceFill(['current_team_id' => $otherTeam->id])->save();

        // A different team's owner has no invitation rights over this team.
        $target = $this->owner();
        $stranger->forceFill(['current_team_id' => $target->currentTeam->id])->save();

        $this->actingAs($stranger->fresh())
            ->post(route('team-invitations.store'), [
                'email' => 'x@example.com',
                'role' => 'member',
            ])
            ->assertForbidden();
    }

    public function test_accepting_an_invitation_assigns_the_role(): void
    {
        $owner = $this->owner();
        $team = $owner->currentTeam;

        $invitee = User::factory()->create(['email' => 'spouse@example.com']);
        $invitation = TeamInvitation::factory()->for($team)->member()->create([
            'email' => 'spouse@example.com',
        ]);

        $this->actingAs($invitee)
            ->post(route('team-invitations.accept', $invitation->token))
            ->assertRedirect(route('dashboard'));

        $invitation->refresh();
        $this->assertSame('accepted', $invitation->status);
        $this->assertSame($invitee->id, $invitation->accepted_by);
        $this->assertTrue($invitee->hasRoleInTeam($team->id, 'client_member'));
    }

    public function test_accepting_with_a_different_email_is_rejected(): void
    {
        $owner = $this->owner();
        $team = $owner->currentTeam;

        $invitation = TeamInvitation::factory()->for($team)->member()->create([
            'email' => 'spouse@example.com',
        ]);

        $wrongUser = User::factory()->create(['email' => 'someone-else@example.com']);

        $this->actingAs($wrongUser)
            ->post(route('team-invitations.accept', $invitation->token))
            ->assertSessionHasErrors('invitation');

        $this->assertSame('pending', $invitation->fresh()->status);
    }

    public function test_revoking_soft_deletes_the_invitation(): void
    {
        $owner = $this->owner();
        $team = $owner->currentTeam;

        $invitation = TeamInvitation::factory()->for($team)->member()->create();

        $this->actingAs($owner)
            ->delete(route('team-invitations.destroy', $invitation))
            ->assertRedirect();

        $this->assertSoftDeleted('team_invitations', ['id' => $invitation->id]);
        $this->assertSame('revoked', TeamInvitation::withTrashed()->find($invitation->id)->status);
    }
}
