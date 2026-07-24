<?php

declare(strict_types=1);

namespace Tests\Feature\Reminders;

use App\Enums\ClientStatus;
use App\Models\Cattle;
use App\Models\Client;
use App\Models\HealthRecord;
use App\Models\Protocol;
use App\Models\Reminder;
use App\Models\Team;
use App\Models\Visit;
use App\Services\Reminders\NurtureService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Lifecycle & nurture (#247, §5.7). The post-calving rebreed loop is the core
 * recurring-revenue driver; data-driven nudges (BCS out of range, dormant
 * clients, unused straws) come from a client's own logged records. Category 4
 * (promotional) requires opt-in; scheduling is idempotent.
 */
class NurtureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function nurture(): NurtureService
    {
        return app(NurtureService::class);
    }

    public function test_calving_schedules_the_rebreed_loop(): void
    {
        $team = Team::factory()->create();
        $cattle = Cattle::factory()->for($team)->create(['has_calved' => false]);

        // recordCalving() flips has_calved, whose observer fires CattleCalved.
        $cattle->recordCalving();

        $this->assertSame(1, Reminder::where('template', 'rebreed_timeline')->count());
        $this->assertSame(1, Reminder::where('template', 'rebreed_booking')->count());

        $timeline = Reminder::where('template', 'rebreed_timeline')->sole();
        $this->assertSame(3, $timeline->category);
        $this->assertSame(
            CarbonImmutable::now()->addDays(30)->toDateString(),
            $timeline->fire_at->toDateString(),
        );

        // The referral ask is promotional (category 4).
        $this->assertSame(4, Reminder::where('template', 'referral_ask')->sole()->category);
    }

    public function test_bcs_out_of_range_at_last_visit_queues_a_nutrition_followup(): void
    {
        $team = Team::factory()->create();
        $cattle = Cattle::factory()->for($team)->create();
        $protocol = Protocol::factory()->create(['team_id' => $team->id]);
        $visit = Visit::factory()->create([
            'team_id' => $team->id,
            'cattle_id' => $cattle->id,
            'protocol_id' => $protocol->id,
        ]);

        HealthRecord::factory()->create([
            'team_id' => $team->id,
            'cattle_id' => $cattle->id,
            'visit_id' => $visit->id,
            'type' => 'body_condition',
            'bcs_score' => 8, // above the 5-6 target range
            'recorded_at' => now(),
        ]);

        $this->nurture()->onVisitCompleted($visit, CarbonImmutable::now());

        $followup = Reminder::where('template', 'nutrition_followup')->sole();
        $this->assertSame(3, $followup->category);
        $this->assertSame($cattle->id, $followup->remindable_id);
    }

    public function test_in_range_bcs_does_not_queue_a_followup(): void
    {
        $team = Team::factory()->create();
        $cattle = Cattle::factory()->for($team)->create();
        $protocol = Protocol::factory()->create(['team_id' => $team->id]);
        $visit = Visit::factory()->create([
            'team_id' => $team->id,
            'cattle_id' => $cattle->id,
            'protocol_id' => $protocol->id,
        ]);

        HealthRecord::factory()->create([
            'team_id' => $team->id,
            'cattle_id' => $cattle->id,
            'visit_id' => $visit->id,
            'type' => 'body_condition',
            'bcs_score' => 6, // within target
            'recorded_at' => now(),
        ]);

        $this->nurture()->onVisitCompleted($visit, CarbonImmutable::now());

        $this->assertSame(0, Reminder::where('template', 'nutrition_followup')->count());
    }

    public function test_due_date_passed_with_no_calving_queues_a_care_check(): void
    {
        Carbon::setTestNow(CarbonImmutable::create(2026, 7, 24, 9, 0, 0, 'UTC'));
        $team = Team::factory()->create();
        Cattle::factory()->for($team)->create([
            'has_calved' => false,
            'status' => 'active',
            'due_date' => CarbonImmutable::now()->subDays(2)->toDateString(),
        ]);

        $this->nurture()->sweep();

        $this->assertSame(1, Reminder::where('template', 'calving_care_check')->count());
    }

    public function test_dormant_client_sweep_is_idempotent(): void
    {
        Carbon::setTestNow(CarbonImmutable::create(2026, 7, 24, 9, 0, 0, 'UTC'));
        $team = Team::factory()->create();
        Client::factory()->for($team)->create([
            'status' => ClientStatus::Active,
            'last_activity_at' => CarbonImmutable::now()->subMonths(9),
        ]);

        $this->nurture()->sweep();
        $this->nurture()->sweep();

        $this->assertSame(1, Reminder::where('template', 'dormant_reengagement')->count());
        $this->assertSame(4, Reminder::where('template', 'dormant_reengagement')->sole()->category);
    }
}
