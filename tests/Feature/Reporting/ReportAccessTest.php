<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reporting access scoping (§5.6c): staff (Jeff) see the aggregate across every
 * client; a client sees only their own team's numbers.
 */
class ReportAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/reports/profitability')->assertRedirect('/login');
        $this->get('/reports/breeding')->assertRedirect('/login');
    }

    public function test_client_sees_only_their_own_team_and_no_per_client_breakdown(): void
    {
        [$mine, $other] = [Team::factory()->create(), Team::factory()->create()];
        $this->paidBooking($mine, 100);
        $this->paidBooking($other, 999);

        $user = User::factory()->create(['is_staff' => false, 'current_team_id' => $mine->id]);

        $this->actingAs($user)
            ->get('/reports/profitability')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/Profitability')
                ->where('isStaff', false)
                ->where('totals.revenue', 100)     // only my team's revenue
                ->where('byClient', [])             // clients get no cross-client view
                ->has('appointments', 1));
    }

    public function test_staff_see_the_aggregate_across_clients(): void
    {
        $this->paidBooking(Team::factory()->create(), 100);
        $this->paidBooking(Team::factory()->create(), 200);

        $staff = User::factory()->create(['is_staff' => true]);

        $this->actingAs($staff)
            ->get('/reports/profitability')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('isStaff', true)
                ->where('totals.revenue', 300)
                ->has('byClient', 2));

        $this->actingAs($staff)
            ->get('/reports/breeding')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('reports/AiSuccess')->where('isStaff', true));
    }

    private function paidBooking(Team $team, float $revenue): void
    {
        $booking = Booking::factory()->for($team)->create();
        Payment::create(['team_id' => $team->id, 'booking_id' => $booking->id, 'total' => $revenue, 'method' => 'card', 'status' => 'paid']);
    }
}
