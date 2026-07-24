<?php

declare(strict_types=1);

namespace Tests\Feature\Completion;

use App\Models\Booking;
use App\Models\Cattle;
use App\Models\HealthRecord;
use App\Models\Protocol;
use App\Models\SemenInventory;
use App\Models\SemenLedgerEntry;
use App\Models\Service;
use App\Models\Supply;
use App\Models\SupplyUsageProfile;
use App\Models\Team;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCompletion;
use App\Services\VisitCompletionService;
use Carbon\CarbonImmutable;
use Database\Seeders\AvailabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBookings;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * Appointment completion form (#239, §5.5) — the keystone. Verifies the four
 * mandatory rules: a straw used decrements inventory by one; a wasted straw
 * decrements + is flagged as waste separately; the completion writes the
 * authoritative timestamp V3 recomputes from; and BCS is required on breeding
 * visits (submit blocked without it). Also covers supply decrement + records.
 */
class VisitCompletionTest extends TestCase
{
    use BuildsBookings, InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(AvailabilitySeeder::class);
    }

    private function service(): VisitCompletionService
    {
        return app(VisitCompletionService::class);
    }

    /** @return array{0: Team, 1: Cattle, 2: Visit} */
    private function breedingVisit(): array
    {
        $team = Team::factory()->create();
        $cattle = Cattle::factory()->for($team)->create(['animal_type' => 'cow']);
        $service = Service::factory()->syncPlan()->create();
        $booking = Booking::factory()->for($team)->create(['service_id' => $service->id]);
        $booking->cattle()->attach($cattle);

        $visit = Visit::factory()->for($team)->create([
            'cattle_id' => $cattle->id,
            'booking_id' => $booking->id,
            'type' => 'standard',
        ]);

        return [$team, $cattle, $visit];
    }

    public function test_straw_used_decrements_inventory_by_one(): void
    {
        [$team, , $visit] = $this->breedingVisit();
        $lot = SemenInventory::factory()->for($team)->create(['straws_count' => 5]);
        $staff = $this->staffUser();

        $this->service()->complete($visit, [
            'completed_at' => now()->toIso8601String(),
            'procedure_confirmed' => true,
            'semen_inventory_id' => $lot->id,
            'straws_used' => 1,
        ], $staff);

        $this->assertSame(4, $lot->fresh()->straws_count);
        $this->assertSame(1, SemenLedgerEntry::where('type', SemenLedgerEntry::TYPE_USED)
            ->where('semen_inventory_id', $lot->id)->count());
        $this->assertSame(-1, (int) SemenLedgerEntry::where('type', SemenLedgerEntry::TYPE_USED)->sole()->straws_delta);
    }

    public function test_wasted_straw_decrements_and_is_flagged_as_waste_separately(): void
    {
        [$team, , $visit] = $this->breedingVisit();
        $lot = SemenInventory::factory()->for($team)->create(['straws_count' => 5]);
        $staff = $this->staffUser();

        $this->service()->complete($visit, [
            'completed_at' => now()->toIso8601String(),
            'procedure_confirmed' => true,
            'semen_inventory_id' => $lot->id,
            'straws_used' => 1,
            'straws_wasted' => 1,
        ], $staff);

        // Both the used AND the wasted straw leave custody.
        $this->assertSame(3, $lot->fresh()->straws_count);

        // Waste is a SEPARATE, flagged ledger movement — never folded into "used".
        $this->assertSame(1, SemenLedgerEntry::where('type', SemenLedgerEntry::TYPE_USED)->count());
        $wasted = SemenLedgerEntry::where('type', SemenLedgerEntry::TYPE_WASTED)->sole();
        $this->assertSame(-1, (int) $wasted->straws_delta);
    }

    public function test_completion_writes_authoritative_timestamp_that_v3_recomputes_from(): void
    {
        $team = Team::factory()->create();
        $cattle = Cattle::factory()->for($team)->create(['animal_type' => 'cow']);
        $protocol = Protocol::factory()->for($team)->create(['animal_type' => 'cow']);
        $visit = Visit::factory()->for($team)->create([
            'cattle_id' => $cattle->id,
            'protocol_id' => $protocol->id,
            'type' => 'v2',
        ]);

        // A Wednesday midday-Chicago recommended time keeps V3 inside working hours.
        $completedAt = CarbonImmutable::parse('2026-06-10 12:00:00', 'America/Chicago')
            ->setTimezone('UTC')->subHours(63);

        $this->service()->complete($visit, [
            'completed_at' => $completedAt->toIso8601String(),
            'procedure_confirmed' => true,
        ], $this->staffUser());

        $completion = VisitCompletion::where('visit_id', $visit->id)->sole();
        $this->assertTrue($completedAt->equalTo($completion->completed_at));
        $this->assertTrue($completedAt->equalTo($visit->fresh()->completed_at));
        $this->assertSame('completed', $visit->fresh()->status);

        // The observer recomputed V3 from the authoritative completed_at: the
        // recommended AI time is the cow midpoint (~63h) after V2.
        $protocol->refresh();
        $this->assertNotNull($protocol->visit3_recommended_at);
        $this->assertSame(
            $completedAt->addHours(63)->timestamp,
            CarbonImmutable::parse($protocol->visit3_recommended_at)->timestamp,
        );
    }

    public function test_supplies_decrement_from_profile_and_store_the_consumption_map(): void
    {
        $team = Team::factory()->create();
        $cattle = Cattle::factory()->for($team)->create();
        $service = Service::factory()->syncPlan()->create();
        $supply = Supply::factory()->create(['on_hand' => 100]);
        SupplyUsageProfile::factory()->create([
            'service_id' => $service->id,
            'consumables' => [$supply->id => 2],
        ]);
        $booking = Booking::factory()->for($team)->create(['service_id' => $service->id]);
        $visit = Visit::factory()->for($team)->create(['cattle_id' => $cattle->id, 'booking_id' => $booking->id]);

        $completion = $this->service()->complete($visit, [
            'completed_at' => now()->toIso8601String(),
            'procedure_confirmed' => true,
        ], $this->staffUser());

        $this->assertEqualsWithDelta(98.0, (float) $supply->fresh()->on_hand, 0.001);
        $this->assertSame(2.0, (float) ($completion->supplies_used[$supply->id] ?? 0));
    }

    public function test_manual_supply_adjustment_overrides_the_profile_default(): void
    {
        $team = Team::factory()->create();
        $cattle = Cattle::factory()->for($team)->create();
        $service = Service::factory()->syncPlan()->create();
        $supply = Supply::factory()->create(['on_hand' => 100]);
        SupplyUsageProfile::factory()->create([
            'service_id' => $service->id,
            'consumables' => [$supply->id => 2],
        ]);
        $booking = Booking::factory()->for($team)->create(['service_id' => $service->id]);
        $visit = Visit::factory()->for($team)->create(['cattle_id' => $cattle->id, 'booking_id' => $booking->id]);

        $this->service()->complete($visit, [
            'completed_at' => now()->toIso8601String(),
            'procedure_confirmed' => true,
            'supplies_used' => [$supply->id => 5],
        ], $this->staffUser());

        $this->assertEqualsWithDelta(95.0, (float) $supply->fresh()->on_hand, 0.001);
    }

    public function test_bcs_is_written_to_the_animals_health_record(): void
    {
        [$team, $cattle, $visit] = $this->breedingVisit();

        $this->service()->complete($visit, [
            'completed_at' => now()->toIso8601String(),
            'procedure_confirmed' => true,
            'notes' => 'Calm, good condition.',
            'animals' => [['cattle_id' => $cattle->id, 'bcs_score' => 6, 'note' => 'Slight limp']],
        ], $this->staffUser());

        $bcs = HealthRecord::where('cattle_id', $cattle->id)->where('type', 'body_condition')->sole();
        $this->assertSame(6, $bcs->bcs_score);
        $this->assertSame($visit->id, $bcs->visit_id);
        $this->assertSame('staff', $bcs->added_role);

        // Notes (per-animal + visit-level) are auto-written to the animal record.
        $this->assertDatabaseHas('health_records', [
            'cattle_id' => $cattle->id,
            'type' => 'general',
        ]);
    }

    public function test_breeding_visit_submit_is_blocked_without_bcs(): void
    {
        [$team, , $visit] = $this->breedingVisit();
        $staff = $this->staffUser();
        $staff->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($staff)
            ->from(route('admin.visits.completion.create', $visit))
            ->post(route('admin.visits.completion.store', $visit), [
                'completed_at' => now()->toIso8601String(),
                'procedure_confirmed' => true,
                'animals' => [['cattle_id' => $visit->cattle_id]], // no bcs_score
            ])
            ->assertSessionHasErrors('animals');

        $this->assertDatabaseCount('visit_completions', 0);
    }

    public function test_non_breeding_visit_does_not_require_bcs(): void
    {
        $team = Team::factory()->create();
        $cattle = Cattle::factory()->for($team)->create();
        $service = Service::factory()->standard()->create(['slug' => 'wellness-consult']);
        $booking = Booking::factory()->for($team)->create(['service_id' => $service->id]);
        $booking->cattle()->attach($cattle);
        $visit = Visit::factory()->for($team)->create([
            'cattle_id' => $cattle->id,
            'booking_id' => $booking->id,
            'type' => 'standard',
        ]);

        $this->actingAs($this->staffUser())
            ->post(route('admin.visits.completion.store', $visit), [
                'completed_at' => now()->toIso8601String(),
                'procedure_confirmed' => true,
            ])
            ->assertRedirect(route('admin.bookings.index'));

        $this->assertDatabaseCount('visit_completions', 1);
    }

    public function test_a_non_staff_user_cannot_complete_a_visit(): void
    {
        [, , $visit] = $this->breedingVisit();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.visits.completion.store', $visit), [
                'completed_at' => now()->toIso8601String(),
                'procedure_confirmed' => true,
            ])
            ->assertForbidden();
    }
}
