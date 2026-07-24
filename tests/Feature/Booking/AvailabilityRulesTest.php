<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\VisitStatus;
use App\Enums\VisitType;
use App\Models\BlackoutDate;
use App\Models\Booking;
use App\Models\Protocol;
use App\Models\Team;
use App\Models\Visit;
use App\Notifications\BookingRescheduleNeededNotification;
use App\Services\Booking\BlackoutConflictResolver;
use App\Services\Booking\ScheduleValidator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsBookings;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * #234 6.3 — Availability, Sunday rule & blackouts. Emergencies bypass; blackout
 * over existing bookings notifies + proposes; post-V1 → manual conflict.
 */
class AvailabilityRulesTest extends TestCase
{
    use BuildsBookings, InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedBookingConfig();
    }

    private function tz(): string
    {
        return (string) config('protocol.timezone');
    }

    public function test_no_sunday_morning_slot_is_bookable(): void
    {
        $sundayMorning = CarbonImmutable::now($this->tz())->next(CarbonImmutable::SUNDAY)->setTime(9, 0);

        $reason = app(ScheduleValidator::class)->slotConflict($sundayMorning);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('Sunday', $reason);
    }

    public function test_emergency_bypasses_availability_and_blackouts(): void
    {
        $sundayMorning = CarbonImmutable::now($this->tz())->next(CarbonImmutable::SUNDAY)->setTime(9, 0);

        BlackoutDate::create([
            'start_date' => $sundayMorning->toDateString(),
            'end_date' => $sundayMorning->toDateString(),
            'reason' => 'Vacation',
        ]);

        $this->assertNull(app(ScheduleValidator::class)->slotConflict($sundayMorning, emergency: true));
    }

    public function test_blackout_dates_block_ordinary_scheduling(): void
    {
        $day = CarbonImmutable::now($this->tz())->next(CarbonImmutable::TUESDAY)->setTime(10, 0);

        BlackoutDate::create([
            'start_date' => $day->toDateString(),
            'end_date' => $day->toDateString(),
            'reason' => 'Big job',
        ]);

        $reason = app(ScheduleValidator::class)->slotConflict($day);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('blacked out', $reason);
    }

    public function test_blackout_over_existing_bookings_notifies_client_and_proposes_alternatives(): void
    {
        Notification::fake();

        [$team, $owner] = $this->clientTeam('active');
        $day = CarbonImmutable::now($this->tz())->addWeek()->next(CarbonImmutable::TUESDAY)->setTime(10, 0);

        $booking = $this->protocolBookingOn($team, $day, visit1Completed: false);

        $blackout = BlackoutDate::create([
            'start_date' => $day->toDateString(),
            'end_date' => $day->toDateString(),
            'reason' => 'Travel',
        ]);

        $report = app(BlackoutConflictResolver::class)->resolve($blackout);

        $this->assertCount(1, $report->rescheduled);
        $this->assertEmpty($report->manualConflicts);
        Notification::assertSentTo($owner, BookingRescheduleNeededNotification::class);
    }

    public function test_blackout_over_a_protocol_past_visit_one_is_a_manual_conflict(): void
    {
        Notification::fake();

        [$team, $owner] = $this->clientTeam('active');
        $day = CarbonImmutable::now($this->tz())->addWeek()->next(CarbonImmutable::TUESDAY)->setTime(10, 0);

        $booking = $this->protocolBookingOn($team, $day, visit1Completed: true);

        $blackout = BlackoutDate::create([
            'start_date' => $day->toDateString(),
            'end_date' => $day->toDateString(),
            'reason' => 'Travel',
        ]);

        $report = app(BlackoutConflictResolver::class)->resolve($blackout);

        $this->assertEmpty($report->rescheduled);
        $this->assertCount(1, $report->manualConflicts);

        $booking->refresh();
        $this->assertTrue($booking->requires_review);
        $this->assertStringContainsString('past Visit 1', (string) $booking->review_reason);

        Notification::assertNothingSent();
    }

    /**
     * A confirmed cow sync booking whose V3 (the scheduled visit) lands on $day.
     */
    private function protocolBookingOn(Team $team, CarbonImmutable $day, bool $visit1Completed): Booking
    {
        $booking = Booking::factory()->confirmed()->create(['team_id' => $team->id]);

        $protocol = Protocol::factory()->create([
            'team_id' => $team->id,
            'booking_id' => $booking->id,
            'animal_type' => 'cow',
        ]);

        Visit::create([
            'team_id' => $team->id,
            'booking_id' => $booking->id,
            'protocol_id' => $protocol->id,
            'type' => VisitType::Visit1->value,
            'status' => $visit1Completed ? VisitStatus::Completed->value : VisitStatus::Scheduled->value,
            'scheduled_at' => $day->subDays(10)->setTimezone('UTC'),
        ]);

        Visit::create([
            'team_id' => $team->id,
            'booking_id' => $booking->id,
            'protocol_id' => $protocol->id,
            'type' => VisitType::Visit3->value,
            'status' => VisitStatus::Scheduled->value,
            'scheduled_at' => $day->setTimezone('UTC'),
        ]);

        return $booking;
    }
}
