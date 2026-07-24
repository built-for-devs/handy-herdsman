<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\OnCallKind;
use App\Models\Service;
use App\Models\User;
use App\Notifications\OnCallPagerNotification;
use App\Services\Booking\BookingRequest;
use App\Services\Booking\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsBookings;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * #236 6.5 — On-call requests. Immediately SMS-alert Jeff (pager); bypass
 * availability + quiet hours; AM/PM breeding rule.
 */
class OnCallTest extends TestCase
{
    use BuildsBookings, InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedBookingConfig();
    }

    public function test_oncall_submission_immediately_pages_jeff_by_sms(): void
    {
        Notification::fake();

        $jeff = $this->staffUser();
        [$team, $owner] = $this->clientTeam('active');
        $cattle = $this->cattleFor($team, 'cow', 1);
        $service = Service::factory()->oncall()->create();

        app(BookingService::class)->book(BookingRequest::make(
            team: $team,
            service: $service,
            cattleIds: $cattle->pluck('id')->all(),
            actor: $owner,
            onCallKind: OnCallKind::StandingHeat,
        ));

        Notification::assertSentTo(
            $jeff,
            OnCallPagerNotification::class,
            fn (OnCallPagerNotification $n, array $channels) => in_array('sentdm', $channels, true),
        );
    }

    public function test_the_pager_notification_is_not_queued_so_it_fires_immediately(): void
    {
        $this->assertFalse(
            is_subclass_of(OnCallPagerNotification::class, ShouldQueue::class),
            'The on-call pager must be sent synchronously, not queued.'
        );
    }

    public function test_oncall_bypasses_quiet_hours_and_availability(): void
    {
        Notification::fake();

        $this->staffUser();
        [$team, $owner] = $this->clientTeam('active');
        $cattle = $this->cattleFor($team, 'cow', 1);
        $service = Service::factory()->oncall()->create();

        // A Sunday morning — normally never bookable. On-call bypasses it.
        CarbonImmutable::setTestNow(CarbonImmutable::now((string) config('protocol.timezone'))->next(CarbonImmutable::SUNDAY)->setTime(6, 0));

        $booking = app(BookingService::class)->book(BookingRequest::make(
            team: $team,
            service: $service,
            cattleIds: $cattle->pluck('id')->all(),
            actor: $owner,
            onCallKind: OnCallKind::CalvingEmergency,
        ));

        $this->assertTrue($booking->is_oncall);
        $this->assertTrue($booking->exists);

        CarbonImmutable::setTestNow();
    }

    public function test_standing_heat_am_pm_breeding_guidance(): void
    {
        $am = CarbonImmutable::create(2026, 7, 20, 8, 0, 0);
        $pm = CarbonImmutable::create(2026, 7, 20, 18, 0, 0);

        $this->assertStringContainsString('AM → PM', OnCallKind::StandingHeat->breedingGuidance($am));
        $this->assertStringContainsString('PM → next AM', OnCallKind::StandingHeat->breedingGuidance($pm));
    }

    public function test_pager_targets_only_staff_users(): void
    {
        Notification::fake();

        $jeff = $this->staffUser();
        $client = User::factory()->create(['is_staff' => false]);

        [$team, $owner] = $this->clientTeam('active');
        $cattle = $this->cattleFor($team, 'cow', 1);
        $service = Service::factory()->oncall()->create();

        app(BookingService::class)->book(BookingRequest::make(
            team: $team,
            service: $service,
            cattleIds: $cattle->pluck('id')->all(),
            actor: $owner,
            onCallKind: OnCallKind::StandingHeat,
        ));

        Notification::assertSentTo($jeff, OnCallPagerNotification::class);
        Notification::assertNotSentTo($client, OnCallPagerNotification::class);
    }
}
