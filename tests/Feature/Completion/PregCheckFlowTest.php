<?php

declare(strict_types=1);

namespace Tests\Feature\Completion;

use App\Enums\PregCheckMethod;
use App\Enums\PregCheckState;
use App\Events\PregCheckResulted;
use App\Models\Cattle;
use App\Models\Reminder;
use App\Models\Team;
use App\Models\User;
use App\Services\PregCheckService;
use Database\Seeders\RateConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\BuildsBookings;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * Preg-check async result flow (#241, §10b). Blood is a two-stage event
 * (pending → final); palpation is immediate. Downstream nurture fires ONLY on a
 * FINAL result: bred → due date + calving countdown, open → rebreed prompt,
 * recheck → schedule another check. The optional +$15 lab fee is charged ONLY
 * when the client opts in; palpation has no pending state and no lab fee.
 */
class PregCheckFlowTest extends TestCase
{
    use BuildsBookings, InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(RateConfigSeeder::class);
    }

    private function service(): PregCheckService
    {
        return app(PregCheckService::class);
    }

    /** @return array{0: Team, 1: Cattle} */
    private function animal(string $breed = 'Angus', string $type = 'cow'): array
    {
        $team = Team::factory()->create();
        $cattle = Cattle::factory()->for($team)->create(['breed' => $breed, 'animal_type' => $type]);

        return [$team, $cattle];
    }

    public function test_blood_draw_starts_pending_and_fires_no_nurture(): void
    {
        Event::fake([PregCheckResulted::class]);
        [, $cattle] = $this->animal();

        $check = $this->service()->record(
            cattle: $cattle,
            method: PregCheckMethod::Blood,
            recordedBy: $this->staffUser(),
            labRequested: true,
        );

        $this->assertSame(PregCheckState::Pending, $check->state);
        $this->assertSame(0, Reminder::count());
        Event::assertNotDispatched(PregCheckResulted::class);
    }

    public function test_bred_result_stores_due_date_and_schedules_calving_countdown(): void
    {
        Event::fake([PregCheckResulted::class]);
        [, $cattle] = $this->animal();

        $check = $this->service()->record($cattle, PregCheckMethod::Blood, $this->staffUser(), labRequested: true);
        $this->service()->recordResult($check, PregCheckState::Bred, $this->staffUser());

        $this->assertNotNull($cattle->fresh()->due_date);
        $countdown = Reminder::where('template', 'calving_countdown')->get();
        $this->assertGreaterThan(0, $countdown->count());
        $this->assertTrue($countdown->every(fn (Reminder $r) => $r->category === 1
            && $r->remindable_type === Cattle::class
            && $r->remindable_id === $cattle->id));
        Event::assertDispatched(PregCheckResulted::class);
    }

    public function test_open_result_fires_a_rebreed_prompt(): void
    {
        [, $cattle] = $this->animal();

        $check = $this->service()->record($cattle, PregCheckMethod::Blood, $this->staffUser());
        $this->service()->recordResult($check, PregCheckState::Open, $this->staffUser());

        $this->assertSame(1, Reminder::where('template', 'rebreed_prompt')->count());
        $this->assertNull($cattle->fresh()->due_date);
    }

    public function test_recheck_result_schedules_another_check(): void
    {
        [, $cattle] = $this->animal();

        $check = $this->service()->record($cattle, PregCheckMethod::Blood, $this->staffUser());
        $this->service()->recordResult($check, PregCheckState::Recheck, $this->staffUser());

        $recheck = Reminder::where('template', 'preg_recheck')->sole();
        $this->assertSame(3, $recheck->category);
        $this->assertTrue($recheck->fire_at->greaterThan(now()->addDays(29)));
    }

    public function test_lab_fee_is_charged_only_when_the_client_opts_in(): void
    {
        [, $cattle] = $this->animal();
        $staff = $this->staffUser();

        $optedIn = $this->service()->record($cattle, PregCheckMethod::Blood, $staff, labRequested: true);
        $notOpted = $this->service()->record($cattle, PregCheckMethod::Blood, $staff, labRequested: false);

        $this->assertSame(15.0, $this->service()->feeFor($optedIn));
        $this->assertSame(0.0, $this->service()->feeFor($notOpted));
    }

    public function test_palpation_is_immediate_with_no_pending_and_no_lab_fee(): void
    {
        Event::fake([PregCheckResulted::class]);
        [, $cattle] = $this->animal();

        $check = $this->service()->record(
            cattle: $cattle,
            method: PregCheckMethod::Palpation,
            recordedBy: $this->staffUser(),
            labRequested: true, // ignored for palpation
            immediateState: PregCheckState::Bred,
        );

        $this->assertSame(PregCheckState::Bred, $check->state);
        $this->assertFalse($check->lab_requested);
        $this->assertSame(0.0, $this->service()->feeFor($check));
        $this->assertNotNull($check->result_recorded_at);
        Event::assertDispatched(PregCheckResulted::class);
    }

    public function test_palpation_requires_a_final_result(): void
    {
        [, $cattle] = $this->animal();

        $this->expectException(\InvalidArgumentException::class);

        $this->service()->record($cattle, PregCheckMethod::Palpation, $this->staffUser());
    }

    public function test_staff_records_a_pending_result_over_http(): void
    {
        [$team, $cattle] = $this->animal();
        $check = $this->service()->record($cattle, PregCheckMethod::Blood, $this->staffUser());

        $this->actingAs($this->staffUser())
            ->post(route('admin.preg-checks.result', $check), ['state' => 'bred'])
            ->assertRedirect();

        $this->assertSame(PregCheckState::Bred, $check->fresh()->state);
    }

    public function test_a_client_cannot_record_a_result(): void
    {
        [, $cattle] = $this->animal();
        $check = $this->service()->record($cattle, PregCheckMethod::Blood, $this->staffUser());

        $this->actingAs(User::factory()->create())
            ->post(route('admin.preg-checks.result', $check), ['state' => 'bred'])
            ->assertForbidden();
    }
}
