<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Enums\ClientStatus;
use App\Jobs\GeocodeClient;
use App\Models\Client;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * Client onboarding + agreement/waiver (spec §4, §5.6). Captures timestamped
 * agreement + waiver acceptance, requires email + phone, and geocodes the
 * address once.
 */
class ClientOnboardingTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'contact_name' => 'Jane Rancher',
            'email' => 'jane@example.com',
            'phone' => '254-555-0100',
            'address_line1' => '1351 High Prairie Road',
            'city' => 'Valley Mills',
            'state' => 'TX',
            'postal_code' => '76689',
            'accept_agreement' => true,
            'accept_waiver' => true,
        ], $overrides);
    }

    public function test_onboarding_timestamps_agreement_and_waiver(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('onboarding.store'), $this->payload())
            ->assertRedirect(route('dashboard'));

        $client = Client::firstOrFail();

        $this->assertNotNull($client->agreement_signed_at);
        $this->assertNotNull($client->waiver_signed_at);
        $this->assertTrue($client->hasAcceptedAgreement());
        $this->assertSame(ClientStatus::New, $client->status);
    }

    public function test_onboarding_creates_the_team_and_assigns_the_owner_role(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('onboarding.store'), $this->payload());

        $team = Team::firstOrFail();
        $this->assertSame($user->id, $team->owner_id);
        $this->assertSame($team->id, $user->fresh()->current_team_id);
        $this->assertTrue($user->hasRoleInTeam($team->id, 'client_owner'));
    }

    public function test_onboarding_queues_a_geocode_of_the_address(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('onboarding.store'), $this->payload());

        Queue::assertPushed(GeocodeClient::class);
    }

    public function test_email_and_phone_are_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('onboarding.store'), $this->payload(['email' => '', 'phone' => '']))
            ->assertSessionHasErrors(['email', 'phone']);
    }

    public function test_agreement_and_waiver_must_be_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('onboarding.store'), $this->payload([
                'accept_agreement' => false,
                'accept_waiver' => false,
            ]))
            ->assertSessionHasErrors(['accept_agreement', 'accept_waiver']);

        $this->assertDatabaseCount('clients', 0);
    }
}
