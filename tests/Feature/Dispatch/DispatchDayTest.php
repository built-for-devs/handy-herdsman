<?php

declare(strict_types=1);

namespace Tests\Feature\Dispatch;

use App\Models\Cattle;
use App\Models\Team;
use App\Models\Visit;
use App\Services\Dispatch\DispatchDayService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBookings;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * Dispatch / route view (§5.10). A day list of scheduled visits ordered by
 * time, each flagged with the client's cached distance / out-of-range status /
 * distance fee. Reuses the §6.6 service-area logic and cached distances — never
 * a new geocoding call — and is explicitly NOT a route-optimization engine.
 */
class DispatchDayTest extends TestCase
{
    use BuildsBookings, InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedBookingConfig();
    }

    private function scheduledVisit(Team $team, string $at): Visit
    {
        $cattle = Cattle::factory()->for($team)->create();

        return Visit::factory()->create([
            'team_id' => $team->id,
            'cattle_id' => $cattle->id,
            'type' => 'v1',
            'status' => 'scheduled',
            'scheduled_at' => $at,
        ]);
    }

    public function test_day_view_lists_scheduled_visits_with_distance_flags(): void
    {
        [$team] = $this->clientTeam('active', ['cached_distance_miles' => 5]);
        $this->scheduledVisit($team, '2026-08-10 09:00:00');

        $stops = app(DispatchDayService::class)->forDate(CarbonImmutable::parse('2026-08-10'));

        $this->assertCount(1, $stops);
        $this->assertEqualsWithDelta(5.0, $stops[0]->distanceMiles, 0.01);
        $this->assertTrue($stops[0]->inRange);
        $this->assertFalse($stops[0]->feeApplies);
    }

    public function test_out_of_range_visit_is_flagged_with_distance_fee(): void
    {
        [$team] = $this->clientTeam('active', ['cached_distance_miles' => 22]);
        $this->scheduledVisit($team, '2026-08-10 09:00:00');

        $stops = app(DispatchDayService::class)->forDate(CarbonImmutable::parse('2026-08-10'));

        $this->assertCount(1, $stops);
        $this->assertTrue($stops[0]->feeApplies);
        $this->assertEqualsWithDelta(30.0, $stops[0]->feeAmount, 0.01);
        // Beyond the 15mi standard range → flagged out of range and the $30
        // distance fee is surfaced (§5.10).
        $this->assertTrue($stops[0]->outOfRange());
        $this->assertTrue($stops[0]->toArray()['out_of_range']);
    }

    public function test_only_scheduled_visits_for_the_given_day_appear(): void
    {
        [$team] = $this->clientTeam('active', ['cached_distance_miles' => 5]);
        $this->scheduledVisit($team, '2026-08-10 09:00:00');
        $this->scheduledVisit($team, '2026-08-11 09:00:00'); // different day
        Visit::factory()->create([
            'team_id' => $team->id,
            'cattle_id' => Cattle::factory()->for($team)->create()->id,
            'status' => 'cancelled',
            'scheduled_at' => '2026-08-10 10:00:00',
        ]);

        $stops = app(DispatchDayService::class)->forDate(CarbonImmutable::parse('2026-08-10'));

        $this->assertCount(1, $stops);
    }

    public function test_stops_are_ordered_by_scheduled_time_not_reordered(): void
    {
        [$team] = $this->clientTeam('active', ['cached_distance_miles' => 5]);
        // Insert out of chronological order; the view must NOT optimize/reorder
        // beyond simple time ordering (§5.10).
        $late = $this->scheduledVisit($team, '2026-08-10 15:00:00');
        $early = $this->scheduledVisit($team, '2026-08-10 08:00:00');

        $stops = app(DispatchDayService::class)->forDate(CarbonImmutable::parse('2026-08-10'));

        $this->assertSame([$early->id, $late->id], $stops->map(fn ($s) => $s->visitId)->all());
    }

    public function test_dispatch_page_renders_for_staff(): void
    {
        [$team] = $this->clientTeam('active', ['cached_distance_miles' => 22]);
        $this->scheduledVisit($team, '2026-08-10 09:00:00');
        $staff = $this->staffUser();

        $this->actingAs($staff)
            ->get(route('admin.dispatch.index', ['date' => '2026-08-10']))
            ->assertInertia(fn ($page) => $page
                ->component('admin/dispatch/Index')
                ->where('date', '2026-08-10')
                ->has('stops', 1)
                ->where('stops.0.out_of_range', true)
                ->where('stops.0.fee_applies', true)
                ->where('outOfRangeCount', 1));
    }

    public function test_dispatch_page_is_staff_only(): void
    {
        [, $owner] = $this->clientTeam('active');

        $this->actingAs($owner)
            ->get(route('admin.dispatch.index'))
            ->assertForbidden();
    }

    public function test_dispatch_page_is_not_public(): void
    {
        $this->get(route('admin.dispatch.index'))->assertRedirect(route('login'));
    }
}
