<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\AnimalType;
use App\Enums\VisitStatus;
use App\Enums\VisitType;
use App\Models\Booking;
use App\Models\Team;
use App\Models\Visit;
use App\Services\Booking\ProtocolScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBookings;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * #233 6.2 — Protocol scheduling & window validation. Only offer V1 dates whose
 * downstream V2/V3 all work; never a silent empty picker.
 */
class ProtocolSchedulingTest extends TestCase
{
    use BuildsBookings, InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedBookingConfig();
    }

    public function test_it_offers_viable_visit_one_dates_within_the_horizon(): void
    {
        $result = app(ProtocolScheduler::class)->findCandidates(AnimalType::Cow);

        $this->assertTrue($result->hasWithinHorizon);
        $this->assertNotEmpty($result->candidates);

        foreach ($result->candidates as $candidate) {
            $this->assertFalse($candidate->beyondHorizon);
        }
    }

    public function test_a_candidate_v1_whose_v3_is_out_of_hours_is_rejected(): void
    {
        $tz = (string) config('protocol.timezone');
        // A cow V1 at 08:00 pushes V3 (≈+231h) to roughly 23:00 — out of hours.
        $badV1 = CarbonImmutable::now($tz)->addDays(3)->next(CarbonImmutable::MONDAY)->setTime(8, 0);

        $reason = app(ProtocolScheduler::class)->validateChain($badV1, AnimalType::Cow);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('Visit 3', $reason);
    }

    public function test_no_dates_in_horizon_returns_next_viable_dates_with_explicit_message(): void
    {
        // Shrink the horizon and fill every day inside it to capacity so nothing
        // is bookable within it — the scheduler must still return LATER dates.
        config()->set('protocol.search_horizon_days', 5);

        $this->fillCalendarToCapacity(days: 6);

        $result = app(ProtocolScheduler::class)->findCandidates(AnimalType::Cow);

        $this->assertFalse($result->hasWithinHorizon);
        $this->assertNotEmpty($result->candidates);
        $this->assertStringContainsString('No start dates are available', $result->message);

        foreach ($result->candidates as $candidate) {
            $this->assertTrue($candidate->beyondHorizon);
        }
    }

    /** Book Jeff solid (max visits/day) for the first N days from today. */
    private function fillCalendarToCapacity(int $days): void
    {
        $tz = (string) config('protocol.timezone');
        $team = Team::factory()->create();
        $booking = Booking::factory()->confirmed()->create(['team_id' => $team->id]);

        for ($d = 0; $d < $days; $d++) {
            $day = CarbonImmutable::now($tz)->addDays($d + 1)->setTime(7, 0);

            if ($day->dayOfWeek === CarbonImmutable::SUNDAY) {
                continue;
            }

            for ($i = 0; $i < 6; $i++) {
                Visit::create([
                    'team_id' => $team->id,
                    'booking_id' => $booking->id,
                    'type' => VisitType::Standard->value,
                    'status' => VisitStatus::Scheduled->value,
                    'scheduled_at' => $day->addHours($i)->setTimezone('UTC'),
                ]);
            }
        }
    }
}
