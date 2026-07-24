<?php

declare(strict_types=1);

namespace Tests\Feature\Team;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\TeamNotifiables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Automated notification recipients (spec §10b): the owner receives all
 * categories, members opt in per category, and vets NEVER receive automated
 * notifications.
 */
class NotificationRecipientsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_always_receives(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);

        $recipients = TeamNotifiables::recipientsForCategory($team, 1);

        $this->assertTrue($recipients->contains(fn (User $u) => $u->id === $owner->id));
    }

    public function test_vet_never_receives_automated_notifications(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);

        $vet = User::factory()->create(['email' => 'vet@example.com']);
        TeamInvitation::factory()->for($team)->accepted()->vet()->create([
            'email' => 'vet@example.com',
            'accepted_by' => $vet->id,
        ]);

        foreach ([1, 2, 3, 4] as $category) {
            $recipients = TeamNotifiables::recipientsForCategory($team, $category);
            $this->assertFalse(
                $recipients->contains(fn (User $u) => $u->id === $vet->id),
                "Vet must not receive category {$category}."
            );
        }
    }

    public function test_member_receives_only_categories_they_opted_into(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);

        $member = User::factory()->create([
            'email' => 'spouse@example.com',
            'notification_opt_ins' => [1 => true, 2 => false],
        ]);
        TeamInvitation::factory()->for($team)->accepted()->member()->create([
            'email' => 'spouse@example.com',
            'accepted_by' => $member->id,
        ]);

        $this->assertTrue(
            TeamNotifiables::recipientsForCategory($team, 1)
                ->contains(fn (User $u) => $u->id === $member->id)
        );
        $this->assertFalse(
            TeamNotifiables::recipientsForCategory($team, 2)
                ->contains(fn (User $u) => $u->id === $member->id)
        );
    }
}
