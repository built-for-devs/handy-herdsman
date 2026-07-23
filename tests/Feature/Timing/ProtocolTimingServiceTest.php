<?php

declare(strict_types=1);

namespace Tests\Feature\Timing;

use App\Enums\AnimalType;
use App\Services\Protocol\ProtocolTimingService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Spec §3, §10b (Timing math). The highest-consequence code in the system — a
 * wrong offset causes a FAILED BREEDING. These tests pin the window math and,
 * critically, prove that DST boundaries never shift a window.
 */
class ProtocolTimingServiceTest extends TestCase
{
    private ProtocolTimingService $timing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->timing = new ProtocolTimingService;
    }

    public function test_visit2_is_exactly_seven_times_24h_after_visit1(): void
    {
        $v1 = CarbonImmutable::parse('2026-04-01 09:00:00', 'America/Chicago');

        $schedule = $this->timing->scheduleFromVisit1($v1, AnimalType::Cow);

        $this->assertSame(168.0, $schedule->visit1At->diffInHours($schedule->visit2At));
    }

    public function test_cow_window_is_60_to_66h_with_63h_midpoint(): void
    {
        $v1 = CarbonImmutable::parse('2026-06-01 08:00:00', 'UTC');
        $schedule = $this->timing->scheduleFromVisit1($v1, AnimalType::Cow);

        $this->assertSame(60.0, $schedule->visit2At->diffInHours($schedule->visit3WindowStart));
        $this->assertSame(66.0, $schedule->visit2At->diffInHours($schedule->visit3WindowEnd));
        $this->assertSame(63.0, $schedule->visit2At->diffInHours($schedule->visit3RecommendedAt));
    }

    public function test_heifer_window_is_52_to_56h_with_54h_midpoint(): void
    {
        $v1 = CarbonImmutable::parse('2026-06-01 08:00:00', 'UTC');
        $schedule = $this->timing->scheduleFromVisit1($v1, AnimalType::Heifer);

        $this->assertSame(52.0, $schedule->visit2At->diffInHours($schedule->visit3WindowStart));
        $this->assertSame(56.0, $schedule->visit2At->diffInHours($schedule->visit3WindowEnd));
        $this->assertSame(54.0, $schedule->visit2At->diffInHours($schedule->visit3RecommendedAt));
    }

    public function test_heifer_window_is_earlier_than_cow_for_same_visit1(): void
    {
        $v1 = CarbonImmutable::parse('2026-06-01 08:00:00', 'UTC');

        $cow = $this->timing->scheduleFromVisit1($v1, AnimalType::Cow);
        $heifer = $this->timing->scheduleFromVisit1($v1, AnimalType::Heifer);

        $this->assertTrue($heifer->visit3RecommendedAt->lt($cow->visit3RecommendedAt));
        // ~9h apart at the midpoints (63h vs 54h) — why mixed groups are blocked.
        $this->assertSame(9.0, $heifer->visit3RecommendedAt->diffInHours($cow->visit3RecommendedAt));
    }

    public function test_timestamps_are_stored_utc_and_displayed_in_chicago(): void
    {
        $v1 = CarbonImmutable::parse('2026-06-01 08:00:00', 'America/Chicago');
        $schedule = $this->timing->scheduleFromVisit1($v1, AnimalType::Cow);

        $this->assertSame('UTC', $schedule->visit1At->timezoneName);

        $display = $schedule->displayTimezone();
        $this->assertSame('America/Chicago', $display['visit1_at']->timezoneName);
        $this->assertSame('2026-06-01 08:00', $display['visit1_at']->format('Y-m-d H:i'));
    }

    public function test_dst_spring_forward_does_not_shift_the_v1_to_v2_leg(): void
    {
        // US spring-forward 2026: 2026-03-08 02:00 CST -> 03:00 CDT.
        $v1 = CarbonImmutable::parse('2026-03-07 12:00:00', 'America/Chicago');
        $schedule = $this->timing->scheduleFromVisit1($v1, AnimalType::Cow);

        // Absolute duration preserved: exactly 168h across the lost hour.
        $this->assertSame(168.0, $schedule->visit1At->diffInHours($schedule->visit2At));

        // Wall-clock in Chicago is one hour LATER (13:00) — an hour was lost.
        $display = $schedule->displayTimezone();
        $this->assertSame('2026-03-14 13:00', $display['visit2_at']->format('Y-m-d H:i'));
    }

    public function test_dst_fall_back_does_not_shift_the_v2_to_v3_leg(): void
    {
        // US fall-back 2026: 2026-11-01 02:00 CDT -> 01:00 CST (hour repeated).
        $v1 = CarbonImmutable::parse('2026-10-24 20:00:00', 'America/Chicago');
        $schedule = $this->timing->scheduleFromVisit1($v1, AnimalType::Cow);

        $this->assertSame(60.0, $schedule->visit2At->diffInHours($schedule->visit3WindowStart));
        $this->assertSame(66.0, $schedule->visit2At->diffInHours($schedule->visit3WindowEnd));
        $this->assertSame(63.0, $schedule->visit2At->diffInHours($schedule->visit3RecommendedAt));

        // Wall-clock is one hour EARLIER after fall-back (gained hour).
        $display = $schedule->displayTimezone();
        $this->assertSame('2026-11-03 10:00', $display['visit3_recommended_at']->format('Y-m-d H:i'));
    }

    public function test_v2_near_midnight_produces_correctly_dated_chicago_window(): void
    {
        $v1 = CarbonImmutable::parse('2026-06-01 23:00:00', 'America/Chicago');
        $schedule = $this->timing->scheduleFromVisit1($v1, AnimalType::Cow);

        $display = $schedule->displayTimezone();
        $this->assertSame('2026-06-08 23:00', $display['visit2_at']->format('Y-m-d H:i'));
        $this->assertSame('2026-06-11 14:00', $display['visit3_recommended_at']->format('Y-m-d H:i'));
    }

    public function test_recompute_window_from_actual_visit2_timestamp(): void
    {
        $v2 = CarbonImmutable::parse('2026-06-08 09:00:00', 'UTC');

        $window = $this->timing->recomputeVisit3($v2, AnimalType::Cow);

        $this->assertSame(60.0, $v2->diffInHours($window['start']));
        $this->assertSame(63.0, $v2->diffInHours($window['recommended']));
    }

    public function test_refuses_to_compute_a_window_for_a_bull(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $v1 = CarbonImmutable::parse('2026-06-01 08:00:00', 'UTC');
        $this->timing->scheduleFromVisit1($v1, AnimalType::Bull);
    }
}
