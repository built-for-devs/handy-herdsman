<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\AnimalType;
use App\Exceptions\BreedingEligibilityException;
use App\Exceptions\MixedGroupException;
use App\Models\Service;
use App\Models\Team;
use App\Services\Booking\AnimalGroupValidator;
use App\Services\Booking\BookingPricing;
use App\Services\Booking\BookingRequest;
use App\Services\Booking\BookingService;
use App\Services\Booking\ServiceAreaResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsBookings;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * #235 6.4 — Multi-animal & mixed-group blocking. Standard/per-head carries a
 * group on one visit; AI/protocol blocks a cow+heifer mix with a split offer.
 */
class MixedGroupTest extends TestCase
{
    use BuildsBookings, InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedBookingConfig();
        Notification::fake();
    }

    public function test_twelve_head_vaccination_is_one_booking_priced_per_head(): void
    {
        [$team, $owner] = $this->clientTeam('active');
        $cattle = $this->cattleFor($team, 'cow', 12);
        $service = Service::factory()->perHead(perHead: 10, visitMinimum: 50)->create();

        $booking = app(BookingService::class)->book(BookingRequest::make(
            team: $team,
            service: $service,
            cattleIds: $cattle->pluck('id')->all(),
            actor: $owner,
            proposedStart: $this->nextWorkingV1(10),
        ));

        $this->assertCount(1, $booking->visits);
        $this->assertCount(12, $booking->cattle);

        $quote = app(BookingPricing::class)->quote($service, 12, $this->noFee());
        $this->assertSame(120.0, $quote->total());
        $this->assertFalse($quote->visitMinimumApplied);
    }

    public function test_per_head_service_applies_single_visit_minimum_once(): void
    {
        $service = Service::factory()->perHead(perHead: 10, visitMinimum: 50)->create();

        // 2 head × $10 = $20, floored to the $50 single-visit minimum.
        $quote = app(BookingPricing::class)->quote($service, 2, $this->noFee());

        $this->assertSame(50.0, $quote->total());
        $this->assertTrue($quote->visitMinimumApplied);
    }

    public function test_mixed_cow_and_heifer_ai_booking_is_blocked_with_a_split_offer(): void
    {
        [$team, $owner] = $this->clientTeam('active');
        $cow = $this->cattleFor($team, 'cow', 1);
        $heifer = $this->cattleFor($team, 'heifer', 1);
        $service = Service::factory()->syncPlan()->create();

        try {
            app(BookingService::class)->book(BookingRequest::make(
                team: $team,
                service: $service,
                cattleIds: [$cow->first()->id, $heifer->first()->id],
                actor: $owner,
                proposedStart: $this->nextWorkingV1(16),
            ));
            $this->fail('Expected a MixedGroupException.');
        } catch (MixedGroupException $e) {
            $this->assertArrayHasKey('cow', $e->splitGroups);
            $this->assertArrayHasKey('heifer', $e->splitGroups);
            $this->assertStringContainsString('Split', $e->getMessage());
        }
    }

    public function test_all_cows_group_shares_one_protocol_and_one_v3_window(): void
    {
        $team = Team::factory()->create();
        $cattle = $this->cattleFor($team, 'cow', 3);
        $service = Service::factory()->syncPlan()->create();

        $windowType = app(AnimalGroupValidator::class)->validate($service, $cattle);

        $this->assertSame(AnimalType::Cow, $windowType);
    }

    public function test_breeding_service_rejects_a_bull(): void
    {
        $team = Team::factory()->create();
        $bull = $this->cattleFor($team, 'bull', 1);
        $service = Service::factory()->syncPlan()->create();

        $this->expectException(BreedingEligibilityException::class);

        app(AnimalGroupValidator::class)->validate($service, $bull);
    }

    private function noFee(): ServiceAreaResult
    {
        return new ServiceAreaResult(
            distanceMiles: 5.0,
            inRange: true,
            feeApplies: false,
            feeAmount: 0.0,
            declined: false,
            zoneLabel: 'standard',
        );
    }
}
