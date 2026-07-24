<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\AnimalType;
use App\Enums\BookingStatus;
use App\Enums\OnCallKind;
use App\Models\Service;
use App\Services\Booking\BookingRequest;
use App\Services\Booking\BookingService;
use App\Services\Booking\ProtocolScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsBookings;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * #232 6.1 — Booking engine core. Book immediately; route by service type;
 * first-timers provisional + requires_review, active clients self-confirm.
 */
class BookingEngineTest extends TestCase
{
    use BuildsBookings, InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedBookingConfig();
        Notification::fake();
    }

    public function test_new_client_booking_is_provisional_and_requires_review(): void
    {
        [$team, $owner] = $this->clientTeam('new');
        $cattle = $this->cattleFor($team, 'cow', 1);
        $service = Service::factory()->standard()->create();

        $booking = app(BookingService::class)->book(BookingRequest::make(
            team: $team,
            service: $service,
            cattleIds: $cattle->pluck('id')->all(),
            actor: $owner,
            proposedStart: $this->nextWorkingV1(10),
        ));

        $this->assertSame(BookingStatus::Provisional->value, $booking->status);
        $this->assertTrue($booking->requires_review);
        $this->assertNotNull($booking->review_reason);
    }

    public function test_active_client_standard_booking_self_confirms_after_validation(): void
    {
        [$team, $owner] = $this->clientTeam('active');
        $cattle = $this->cattleFor($team, 'cow', 1);
        $service = Service::factory()->standard()->create();

        $booking = app(BookingService::class)->book(BookingRequest::make(
            team: $team,
            service: $service,
            cattleIds: $cattle->pluck('id')->all(),
            actor: $owner,
            proposedStart: $this->nextWorkingV1(10),
        ));

        $this->assertSame(BookingStatus::Confirmed->value, $booking->status);
        $this->assertFalse($booking->requires_review);
    }

    public function test_protocol_type_routes_to_the_three_visit_scheduler(): void
    {
        [$team, $owner] = $this->clientTeam('active');
        $cattle = $this->cattleFor($team, 'cow', 1);
        $service = Service::factory()->syncPlan()->create();

        $v1 = app(ProtocolScheduler::class)
            ->findCandidates(AnimalType::Cow)
            ->candidates[0]->schedule->visit1At;

        $booking = app(BookingService::class)->book(BookingRequest::make(
            team: $team,
            service: $service,
            cattleIds: $cattle->pluck('id')->all(),
            actor: $owner,
            proposedStart: $v1,
        ));

        $this->assertNotNull($booking->protocol);
        $this->assertCount(3, $booking->visits);
        $this->assertEqualsCanonicalizing(
            ['v1', 'v2', 'v3'],
            $booking->visits->pluck('type')->all(),
        );
    }

    public function test_standard_type_creates_a_single_visit_and_no_protocol(): void
    {
        [$team, $owner] = $this->clientTeam('active');
        $cattle = $this->cattleFor($team, 'cow', 1);
        $service = Service::factory()->standard()->create();

        $booking = app(BookingService::class)->book(BookingRequest::make(
            team: $team,
            service: $service,
            cattleIds: $cattle->pluck('id')->all(),
            actor: $owner,
            proposedStart: $this->nextWorkingV1(10),
        ));

        $this->assertNull($booking->protocol);
        $this->assertCount(1, $booking->visits);
        $this->assertSame('standard', $booking->visits->first()->type);
    }

    public function test_oncall_type_creates_an_oncall_booking_awaiting_review(): void
    {
        [$team, $owner] = $this->clientTeam('active');
        $cattle = $this->cattleFor($team, 'cow', 1);
        $service = Service::factory()->oncall()->create();

        $booking = app(BookingService::class)->book(BookingRequest::make(
            team: $team,
            service: $service,
            cattleIds: $cattle->pluck('id')->all(),
            actor: $owner,
            onCallKind: OnCallKind::StandingHeat,
        ));

        $this->assertTrue($booking->is_oncall);
        $this->assertTrue($booking->requires_review);
        $this->assertSame('oncall', $booking->visits->first()->type);
    }
}
