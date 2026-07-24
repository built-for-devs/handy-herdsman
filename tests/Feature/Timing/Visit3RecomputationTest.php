<?php

declare(strict_types=1);

namespace Tests\Feature\Timing;

use App\Events\Visit3Recomputed;
use App\Models\Protocol;
use App\Models\Service;
use App\Models\Team;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCompletion;
use Carbon\CarbonImmutable;
use Database\Seeders\AvailabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Spec §10b (Timing math), issue 1.3. Completing Visit 2 must automatically
 * recompute Visit 3 from the ACTUAL completed timestamp and flag conflicts.
 */
class Visit3RecomputationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AvailabilitySeeder::class);
    }

    /**
     * Create a protocol with a scheduled V2 visit, then complete it at
     * $completedAt. The VisitCompletionObserver fires the recompute automatically.
     */
    private function completeVisit2At(CarbonImmutable $completedAt): Protocol
    {
        $team = Team::create(['owner_id' => User::factory()->create()->id, 'name' => 'Ranch']);

        $service = Service::create([
            'name' => 'AI — Basic/Sync Plan',
            'slug' => 'ai-basicsync-plan-'.uniqid(),
            'category' => 'breeding',
            'type' => 'protocol',
            'price_rule' => [],
        ]);

        $protocol = Protocol::create([
            'team_id' => $team->id,
            'service_id' => $service->id,
            'plan_type' => 'sync',
            'animal_type' => 'cow',
            'status' => 'v1_done',
        ]);

        $visit = Visit::create([
            'team_id' => $team->id,
            'protocol_id' => $protocol->id,
            'type' => 'v2',
            'scheduled_at' => $completedAt,
        ]);

        VisitCompletion::create([
            'visit_id' => $visit->id,
            'completed_at' => $completedAt,
            'procedure_confirmed' => true,
        ]);

        return $protocol->fresh();
    }

    public function test_recomputes_visit3_to_60_63_66h_after_actual_visit2(): void
    {
        // Recommended lands Wed midday Chicago (well within 07:00–18:00).
        $completedAt = CarbonImmutable::parse('2026-06-10 12:00:00', 'America/Chicago')
            ->setTimezone('UTC')->subHours(63);

        $protocol = $this->completeVisit2At($completedAt);

        $this->assertTrue($protocol->visit3_window_start->equalTo($completedAt->addHours(60)));
        $this->assertTrue($protocol->visit3_recommended_at->equalTo($completedAt->addHours(63)));
        $this->assertTrue($protocol->visit3_window_end->equalTo($completedAt->addHours(66)));
        $this->assertTrue($protocol->visit2_at->equalTo($completedAt));
        $this->assertFalse($protocol->visit3_conflict);
    }

    public function test_visit3_shifts_by_exactly_the_amount_visit2_runs_late(): void
    {
        $base = CarbonImmutable::parse('2026-06-10 12:00:00', 'America/Chicago')
            ->setTimezone('UTC')->subHours(63);

        $onTime = $this->completeVisit2At($base);
        $twoLate = $this->completeVisit2At($base->addHours(2));
        $oneEarly = $this->completeVisit2At($base->subHour());

        $this->assertSame(2.0, $onTime->visit3_recommended_at->diffInHours($twoLate->visit3_recommended_at));
        $this->assertSame(1.0, $oneEarly->visit3_recommended_at->diffInHours($onTime->visit3_recommended_at));
    }

    public function test_flags_a_conflict_when_visit3_lands_outside_working_hours(): void
    {
        Event::fake([Visit3Recomputed::class]);

        // Recommended lands at 04:00 Chicago on a weekday — outside 07:00–18:00.
        $completedAt = CarbonImmutable::parse('2026-06-10 04:00:00', 'America/Chicago')
            ->setTimezone('UTC')->subHours(63);

        $protocol = $this->completeVisit2At($completedAt);

        $this->assertTrue($protocol->visit3_conflict);
        $this->assertStringContainsString('outside working hours', $protocol->visit3_conflict_reason);

        Event::assertDispatched(Visit3Recomputed::class, fn ($e) => $e->hasConflict());
    }

    public function test_flags_a_conflict_when_visit3_lands_on_a_sunday(): void
    {
        Event::fake([Visit3Recomputed::class]);

        // 2026-06-14 is a Sunday; noon still conflicts because Sundays are off.
        $completedAt = CarbonImmutable::parse('2026-06-14 12:00:00', 'America/Chicago')
            ->setTimezone('UTC')->subHours(63);

        $protocol = $this->completeVisit2At($completedAt);

        $this->assertTrue($protocol->visit3_conflict);
        $this->assertStringContainsString('non-working day', $protocol->visit3_conflict_reason);

        Event::assertDispatched(Visit3Recomputed::class, fn ($e) => $e->hasConflict());
    }
}
