<?php

declare(strict_types=1);

namespace Tests\Feature\Cattle;

use App\Enums\CattleStatus;
use App\Events\CattleDeactivated;
use App\Models\Cattle;
use App\Models\Reminder;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Marking a cow inactive STOPS all her pending reminders immediately (§10b —
 * Cattle status). The reminders subsystem is M9, so this exercises the hook +
 * event and the listener that cancels existing pending reminder rows.
 */
class CattleInactivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivating_cattle_cancels_only_its_pending_reminders(): void
    {
        $team = Team::factory()->create();
        $cattle = Cattle::factory()->for($team)->create(['status' => 'active']);
        $other = Cattle::factory()->for($team)->create(['status' => 'active']);

        $pending = Reminder::factory()->forCattle($cattle)->status('pending')->create();
        $alsoPending = Reminder::factory()->forCattle($cattle)->status('pending')->create();
        $alreadySent = Reminder::factory()->forCattle($cattle)->status('sent')->create();
        $otherAnimal = Reminder::factory()->forCattle($other)->status('pending')->create();

        $cattle->deactivate();

        $this->assertSame(CattleStatus::Inactive, $cattle->fresh()->status);
        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->assertSame('cancelled', $alsoPending->fresh()->status);
        // Sent reminders and other animals' reminders are untouched.
        $this->assertSame('sent', $alreadySent->fresh()->status);
        $this->assertSame('pending', $otherAnimal->fresh()->status);
    }

    public function test_deactivating_dispatches_the_hook_event(): void
    {
        Event::fake([CattleDeactivated::class]);

        $team = Team::factory()->create();
        $cattle = Cattle::factory()->for($team)->create(['status' => 'active']);

        $cattle->deactivate();

        Event::assertDispatched(CattleDeactivated::class, fn (CattleDeactivated $e) => $e->cattle->is($cattle));
    }

    public function test_event_fires_when_status_updated_to_inactive_via_any_path(): void
    {
        Event::fake([CattleDeactivated::class]);

        $team = Team::factory()->create();
        $cattle = Cattle::factory()->for($team)->create(['status' => 'active']);

        // A plain attribute update (as the CRUD controller does) still triggers it.
        $cattle->update(['status' => 'inactive']);

        Event::assertDispatchedTimes(CattleDeactivated::class, 1);
    }

    public function test_no_event_when_status_unchanged(): void
    {
        Event::fake([CattleDeactivated::class]);

        $team = Team::factory()->create();
        $cattle = Cattle::factory()->for($team)->create(['status' => 'active']);

        $cattle->update(['reg_name' => 'Bessie']);
        $cattle->activate();

        Event::assertNotDispatched(CattleDeactivated::class);
    }

    public function test_reactivating_does_not_fire_the_deactivation_event(): void
    {
        Event::fake([CattleDeactivated::class]);

        $team = Team::factory()->create();
        $cattle = Cattle::factory()->for($team)->create(['status' => 'inactive']);

        $cattle->activate();

        Event::assertNotDispatched(CattleDeactivated::class);
    }
}
