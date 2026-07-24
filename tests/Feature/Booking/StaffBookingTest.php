<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\AnimalType;
use App\Enums\BookingStatus;
use App\Enums\VisitStatus;
use App\Enums\VisitType;
use App\Exceptions\BookingValidationException;
use App\Models\Service;
use App\Models\Team;
use App\Models\Visit;
use App\Services\Booking\BookingRequest;
use App\Services\Booking\BookingService;
use App\Services\Booking\ProtocolScheduler;
use App\Services\Booking\StaffVisitManager;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsBookings;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * #238 6.7 — Staff booking & manual override. Staff-created protocol bookings
 * run full validation; failed visits still bill; staff edits adjust freely.
 */
class StaffBookingTest extends TestCase
{
    use BuildsBookings, InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedBookingConfig();
        Notification::fake();
    }

    public function test_staff_created_protocol_booking_confirms_and_runs_full_validation(): void
    {
        $jeff = $this->staffUser();
        [$team] = $this->clientTeam('new'); // even a brand-new client
        $cattle = $this->cattleFor($team, 'cow', 1);
        $service = Service::factory()->syncPlan()->create();

        $v1 = app(ProtocolScheduler::class)->findCandidates(AnimalType::Cow)->candidates[0]->schedule->visit1At;

        $booking = app(BookingService::class)->book(BookingRequest::make(
            team: $team,
            service: $service,
            cattleIds: $cattle->pluck('id')->all(),
            actor: $jeff,
            byStaff: true,
            proposedStart: $v1,
        ));

        // Staff booking confirms outright (no provisional review), but still
        // produced a fully-validated 3-visit protocol.
        $this->assertSame(BookingStatus::Confirmed->value, $booking->status);
        $this->assertNotNull($booking->reviewed_by);
        $this->assertCount(3, $booking->visits);
    }

    public function test_staff_created_protocol_still_rejects_an_invalid_slot(): void
    {
        $jeff = $this->staffUser();
        [$team] = $this->clientTeam('active');
        $cattle = $this->cattleFor($team, 'cow', 1);
        $service = Service::factory()->syncPlan()->create();

        // A cow V1 at 08:00 → V3 out of hours: invalid even for staff.
        $badV1 = CarbonImmutable::now((string) config('protocol.timezone'))
            ->addDays(3)->next(CarbonImmutable::MONDAY)->setTime(8, 0);

        $this->expectException(BookingValidationException::class);

        app(BookingService::class)->book(BookingRequest::make(
            team: $team,
            service: $service,
            cattleIds: $cattle->pluck('id')->all(),
            actor: $jeff,
            byStaff: true,
            proposedStart: $badV1,
        ));
    }

    public function test_failed_visit_is_still_billed_at_the_normal_rate(): void
    {
        $visit = Visit::create([
            'team_id' => Team::factory()->create()->id,
            'type' => VisitType::Standard->value,
            'status' => VisitStatus::Scheduled->value,
            'scheduled_at' => now()->addDay(),
        ]);

        app(StaffVisitManager::class)->markFailed($visit);

        $visit->refresh();
        $this->assertSame(VisitStatus::Failed->value, $visit->status);
        $this->assertTrue($visit->fee_applied);
        $this->assertSame('100.00', (string) $visit->billed_amount);
    }

    public function test_staff_can_adjust_a_visit_charge_freely(): void
    {
        $visit = Visit::create([
            'team_id' => Team::factory()->create()->id,
            'type' => VisitType::Standard->value,
            'status' => VisitStatus::Scheduled->value,
            'scheduled_at' => now()->addDay(),
        ]);

        app(StaffVisitManager::class)->adjust($visit, [
            'billed_amount' => 42.50,
            'staff_notes' => 'Comped half — long-standing client.',
        ]);

        $visit->refresh();
        $this->assertSame('42.50', (string) $visit->billed_amount);
        $this->assertSame('Comped half — long-standing client.', $visit->staff_notes);
    }
}
