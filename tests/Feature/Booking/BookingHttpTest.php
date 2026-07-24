<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsBookings;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * #232/#238 — HTTP surface for the booking flow: client store, protocol
 * candidates endpoint, and the staff review actions.
 */
class BookingHttpTest extends TestCase
{
    use BuildsBookings, InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedBookingConfig();
        Notification::fake();
    }

    public function test_client_can_submit_a_standard_booking(): void
    {
        [$team, $owner] = $this->clientTeam('active');
        $cattle = $this->cattleFor($team, 'cow', 1);
        $service = Service::factory()->standard()->create();

        $response = $this->actingAs($owner)->post(route('bookings.store'), [
            'service_id' => $service->id,
            'cattle_ids' => $cattle->pluck('id')->all(),
            'proposed_start' => $this->nextWorkingV1(10)->toIso8601String(),
        ]);

        $response->assertRedirect(route('bookings.index'));
        $this->assertDatabaseHas('bookings', ['team_id' => $team->id, 'service_id' => $service->id]);
    }

    public function test_candidates_endpoint_returns_viable_dates(): void
    {
        [$team, $owner] = $this->clientTeam('active');

        $response = $this->actingAs($owner)->getJson(route('bookings.candidates', ['animal_type' => 'cow']));

        $response->assertOk()
            ->assertJsonStructure(['candidates', 'has_within_horizon', 'horizon_days', 'message']);
    }

    public function test_staff_can_confirm_a_provisional_booking(): void
    {
        $jeff = $this->staffUser();
        [$team] = $this->clientTeam('new');
        $booking = Booking::factory()->create([
            'team_id' => $team->id,
            'status' => BookingStatus::Provisional->value,
            'requires_review' => true,
        ]);

        $this->actingAs($jeff)->post(route('admin.bookings.confirm', $booking))->assertRedirect();

        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed->value, $booking->status);
        $this->assertFalse($booking->requires_review);
        $this->assertSame($jeff->id, $booking->reviewed_by);
    }

    public function test_non_staff_cannot_reach_the_admin_booking_queue(): void
    {
        [, $owner] = $this->clientTeam('active');

        $this->actingAs($owner)->get(route('admin.bookings.index'))->assertForbidden();
    }
}
