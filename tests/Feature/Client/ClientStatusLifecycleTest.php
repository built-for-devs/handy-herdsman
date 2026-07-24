<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\ClientStatus;
use App\Models\Cattle;
use App\Models\Client;
use App\Models\Team;
use App\Models\Visit;
use App\Models\VisitCompletion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Client status lifecycle (spec §10b — Client status):
 * new → active (after one completed visit) → inactive (after 1yr idle) →
 * review again on the next booking.
 */
class ClientStatusLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_clients_start_requiring_review(): void
    {
        $client = Client::factory()->create();

        $this->assertSame(ClientStatus::New, $client->status);
        $this->assertTrue($client->requiresBookingReview());
    }

    /**
     * @return array<string, array{ClientStatus, bool}>
     */
    public static function statusReviewProvider(): array
    {
        return [
            'new requires review' => [ClientStatus::New, true],
            'active self-confirms' => [ClientStatus::Active, false],
            'inactive requires review' => [ClientStatus::Inactive, true],
        ];
    }

    #[DataProvider('statusReviewProvider')]
    public function test_booking_review_is_gated_by_status(ClientStatus $status, bool $requiresReview): void
    {
        $client = Client::factory()->create(['status' => $status]);

        $this->assertSame($requiresReview, $client->requiresBookingReview());
    }

    public function test_a_completed_visit_promotes_a_new_client_to_active(): void
    {
        $client = Client::factory()->create(['status' => ClientStatus::New]);

        $client->recordCompletedVisit();

        $this->assertSame(ClientStatus::Active, $client->fresh()->status);
        $this->assertFalse($client->fresh()->requiresBookingReview());
    }

    public function test_visit_completion_observer_auto_promotes_the_client(): void
    {
        $team = Team::factory()->create();
        $client = Client::factory()->for($team)->create(['status' => ClientStatus::New]);
        $cattle = Cattle::factory()->for($team)->create();

        $visit = Visit::create([
            'team_id' => $team->id,
            'cattle_id' => $cattle->id,
            'type' => 'standard',
            'scheduled_at' => now(),
        ]);

        VisitCompletion::create([
            'visit_id' => $visit->id,
            'completed_at' => now(),
            'procedure_confirmed' => true,
        ]);

        $this->assertSame(ClientStatus::Active, $client->fresh()->status);
    }

    public function test_active_client_reverts_to_inactive_after_the_idle_threshold(): void
    {
        config(['clients.idle_threshold_days' => 365]);

        $client = Client::factory()->create([
            'status' => ClientStatus::Active,
            'last_activity_at' => now()->subDays(366),
        ]);

        $this->assertTrue($client->markInactiveIfIdle());
        $this->assertSame(ClientStatus::Inactive, $client->fresh()->status);
        $this->assertTrue($client->fresh()->requiresBookingReview());
    }

    public function test_active_client_within_the_idle_window_stays_active(): void
    {
        config(['clients.idle_threshold_days' => 365]);

        $client = Client::factory()->create([
            'status' => ClientStatus::Active,
            'last_activity_at' => now()->subDays(200),
        ]);

        $this->assertFalse($client->markInactiveIfIdle());
        $this->assertSame(ClientStatus::Active, $client->fresh()->status);
    }

    public function test_idle_threshold_is_config_driven(): void
    {
        config(['clients.idle_threshold_days' => 30]);

        $client = Client::factory()->create([
            'status' => ClientStatus::Active,
            'last_activity_at' => now()->subDays(45),
        ]);

        $this->assertTrue($client->markInactiveIfIdle());
        $this->assertSame(ClientStatus::Inactive, $client->fresh()->status);
    }

    public function test_reactivated_client_returns_to_active_on_next_completed_visit(): void
    {
        $client = Client::factory()->create(['status' => ClientStatus::Inactive]);

        // The inactive client's next booking requires review again (§10b).
        $this->assertTrue($client->requiresBookingReview());

        // A completed visit re-promotes them to active.
        $client->recordCompletedVisit();

        $this->assertSame(ClientStatus::Active, $client->fresh()->status);
        $this->assertFalse($client->fresh()->requiresBookingReview());
    }

    public function test_scheduled_command_marks_idle_clients_inactive(): void
    {
        config(['clients.idle_threshold_days' => 365]);

        $idle = Client::factory()->create([
            'status' => ClientStatus::Active,
            'last_activity_at' => now()->subDays(400),
        ]);
        $recent = Client::factory()->create([
            'status' => ClientStatus::Active,
            'last_activity_at' => now()->subDays(10),
        ]);

        $this->artisan('clients:mark-idle-inactive')->assertSuccessful();

        $this->assertSame(ClientStatus::Inactive, $idle->fresh()->status);
        $this->assertSame(ClientStatus::Active, $recent->fresh()->status);
    }
}
