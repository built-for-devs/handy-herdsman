<?php

declare(strict_types=1);

namespace Tests\Feature\Reminders;

use App\Enums\PregCheckMethod;
use App\Enums\PregCheckState;
use App\Jobs\SendReminder;
use App\Models\Cattle;
use App\Models\PregCheck;
use App\Models\Protocol;
use App\Models\Reminder;
use App\Models\Team;
use App\Models\User;
use App\Models\Visit;
use App\Services\Reminders\NurtureService;
use App\Support\ReminderChannelResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Transactional reminders around a breeding (#246, §5.7). From the insemination
 * date: +21d return-to-heat, +30d book a preg check, +60d follow-up if none is
 * booked. Offsets are config-driven. Marking the animal inactive stops all its
 * pending reminders immediately (§10b).
 */
class TransactionalReminderTest extends TestCase
{
    use RefreshDatabase;

    private function nurture(): NurtureService
    {
        return app(NurtureService::class);
    }

    /** @return array{0: Team, 1: Cattle, 2: Visit} */
    private function breedingVisit(): array
    {
        $team = Team::factory()->create();
        $cattle = Cattle::factory()->for($team)->create();
        $protocol = Protocol::factory()->create(['team_id' => $team->id]);
        $visit = Visit::factory()->create([
            'team_id' => $team->id,
            'cattle_id' => $cattle->id,
            'protocol_id' => $protocol->id,
        ]);

        return [$team, $cattle, $visit];
    }

    public function test_breeding_schedules_21_30_60_from_insemination_date(): void
    {
        [, $cattle, $visit] = $this->breedingVisit();
        $inseminatedAt = CarbonImmutable::create(2026, 3, 1, 9, 0, 0, 'UTC');

        $this->nurture()->onBreedingVisitCompleted($visit, $inseminatedAt);

        $heat = Reminder::where('template', 'return_to_heat')->sole();
        $this->assertSame(1, $heat->category);
        $this->assertSame($inseminatedAt->addDays(21)->toDateString(), $heat->fire_at->toDateString());

        $book = Reminder::where('template', 'book_preg_check')->sole();
        $this->assertSame(3, $book->category);
        $this->assertSame($inseminatedAt->addDays(30)->toDateString(), $book->fire_at->toDateString());

        $followup = Reminder::where('template', 'book_preg_check_followup')->sole();
        $this->assertSame($inseminatedAt->addDays(60)->toDateString(), $followup->fire_at->toDateString());
        $this->assertNotNull($followup->payload['suppress_if_preg_check_after'] ?? null);

        $this->assertTrue(
            Reminder::whereIn('template', ['return_to_heat', 'book_preg_check', 'book_preg_check_followup'])
                ->get()
                ->every(fn (Reminder $r) => $r->remindable_id === $cattle->id),
        );
    }

    public function test_scheduling_is_idempotent(): void
    {
        [, , $visit] = $this->breedingVisit();
        $inseminatedAt = CarbonImmutable::create(2026, 3, 1, 9, 0, 0, 'UTC');

        $this->nurture()->onBreedingVisitCompleted($visit, $inseminatedAt);
        $this->nurture()->onBreedingVisitCompleted($visit, $inseminatedAt);

        $this->assertSame(3, Reminder::count());
    }

    public function test_inactivating_the_cow_cancels_pending_reminders(): void
    {
        [, $cattle, $visit] = $this->breedingVisit();

        $this->nurture()->onBreedingVisitCompleted($visit, CarbonImmutable::now());
        $this->assertSame(3, Reminder::where('status', Reminder::STATUS_PENDING)->count());

        $cattle->deactivate();

        $this->assertSame(0, Reminder::where('status', Reminder::STATUS_PENDING)->count());
        $this->assertSame(3, Reminder::where('status', Reminder::STATUS_CANCELLED)->count());
    }

    public function test_no_reminders_are_scheduled_for_an_inactive_animal(): void
    {
        [, $cattle, $visit] = $this->breedingVisit();
        $cattle->deactivate();

        $this->nurture()->onBreedingVisitCompleted($visit, CarbonImmutable::now());

        $this->assertSame(0, Reminder::count());
    }

    public function test_60_day_followup_is_suppressed_once_a_preg_check_is_recorded(): void
    {
        [$team, $cattle, $visit] = $this->breedingVisit();
        $inseminatedAt = CarbonImmutable::now()->subDays(60);

        $this->nurture()->onBreedingVisitCompleted($visit, $inseminatedAt);

        // The client books/records a preg check after the insemination.
        PregCheck::create([
            'team_id' => $team->id,
            'cattle_id' => $cattle->id,
            'method' => PregCheckMethod::Palpation,
            'state' => PregCheckState::Bred,
            'recorded_by' => User::factory()->create()->id,
        ]);

        $followup = Reminder::where('template', 'book_preg_check_followup')->sole();
        $followup->update(['status' => Reminder::STATUS_QUEUED]);

        (new SendReminder($followup->id))->handle(app(ReminderChannelResolver::class));

        $this->assertSame(Reminder::STATUS_CANCELLED, $followup->fresh()->status);
    }
}
