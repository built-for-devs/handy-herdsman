<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\AnimalType;
use App\Exceptions\BookingValidationException;
use App\Models\Service;
use App\Models\ServiceAreaRule;
use App\Services\Booking\BookingPricing;
use App\Services\Booking\BookingRequest;
use App\Services\Booking\BookingService;
use App\Services\Booking\ProtocolScheduler;
use App\Services\Booking\ServiceAreaResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsBookings;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * #237 6.6 — Distance fee & service area. One $30 fee per booking/protocol
 * (never per visit); declined zones blocked/flagged.
 */
class DistanceFeeTest extends TestCase
{
    use BuildsBookings, InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedBookingConfig();
        Notification::fake();
    }

    public function test_twenty_mile_client_pays_one_distance_fee_on_a_three_visit_protocol(): void
    {
        [$team, $owner] = $this->clientTeam('active', ['cached_distance_miles' => 20, 'in_range' => true]);
        $client = $team->client;

        $area = app(ServiceAreaResolver::class)->resolve($client);
        $this->assertTrue($area->feeApplies);
        $this->assertSame(30.0, $area->feeAmount);

        $service = Service::factory()->syncPlan()->create();
        $quote = app(BookingPricing::class)->quote($service, 1, $area);

        // Base $300 + ONE $30 distance fee — NOT $90 across three visits.
        $this->assertSame(30.0, $quote->distanceFee);
        $this->assertSame(330.0, $quote->total());

        // And the booking carries a single per-booking distance flag.
        $v1 = app(ProtocolScheduler::class)->findCandidates(AnimalType::Cow)->candidates[0]->schedule->visit1At;

        $booking = app(BookingService::class)->book(BookingRequest::make(
            team: $team,
            service: $service,
            cattleIds: $this->cattleFor($team, 'cow', 1)->pluck('id')->all(),
            actor: $owner,
            proposedStart: $v1,
        ));

        $this->assertTrue($booking->distance_fee_flag);
        $this->assertCount(3, $booking->visits);
    }

    public function test_client_within_fifteen_miles_pays_no_distance_fee(): void
    {
        [$team] = $this->clientTeam('active', ['cached_distance_miles' => 8, 'in_range' => true]);

        $area = app(ServiceAreaResolver::class)->resolve($team->client);

        $this->assertFalse($area->feeApplies);
        $this->assertSame(0.0, $area->feeAmount);
    }

    public function test_declined_zone_blocks_a_client_booking(): void
    {
        ServiceAreaRule::create([
            'type' => 'declined_zone',
            'min_miles' => 100,
            'max_miles' => null,
            'fee' => 0,
            'zone_label' => 'far',
            'declined' => true,
            'sort_order' => 5,
        ]);

        [$team, $owner] = $this->clientTeam('active', ['cached_distance_miles' => 120, 'in_range' => false]);

        $area = app(ServiceAreaResolver::class)->resolve($team->client);
        $this->assertTrue($area->declined);

        $service = Service::factory()->standard()->create();

        $this->expectException(BookingValidationException::class);

        app(BookingService::class)->book(BookingRequest::make(
            team: $team,
            service: $service,
            cattleIds: $this->cattleFor($team, 'cow', 1)->pluck('id')->all(),
            actor: $owner,
            proposedStart: $this->nextWorkingV1(10),
        ));
    }
}
