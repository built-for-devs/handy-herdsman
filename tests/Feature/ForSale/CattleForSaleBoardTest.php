<?php

declare(strict_types=1);

namespace Tests\Feature\ForSale;

use App\Models\Cattle;
use App\Models\Team;
use App\Models\User;
use App\Services\ForSale\ForSaleListingPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * Cattle-for-sale board (§5.9). A clients-only bulletin board — a client lists
 * one of their herd animals and picks which profile fields are shared; the
 * listing exposes ONLY those fields. Staff can post too. No public listings,
 * no transaction flow.
 */
class CattleForSaleBoardTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function ownerWithTeam(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        return [$owner, $team];
    }

    public function test_toggling_for_sale_creates_a_listing_with_only_the_chosen_fields(): void
    {
        [$owner, $team] = $this->ownerWithTeam();
        $cattle = Cattle::factory()->for($team)->create([
            'reg_name' => 'Bessie',
            'herd_number' => '42',
            'breed' => 'Angus',
            'notes' => 'private note',
            'for_sale' => false,
        ]);

        $this->actingAs($owner)
            ->put(route('cattle.for-sale.update', $cattle), [
                'for_sale' => true,
                'shared_fields' => ['reg_name', 'breed'],
            ])
            ->assertRedirect(route('cattle.show', $cattle));

        $cattle->refresh();
        $this->assertTrue($cattle->for_sale);
        $this->assertSame(['reg_name', 'breed'], $cattle->for_sale_shared_fields);
        $this->assertSame('client', $cattle->for_sale_listed_by_role);

        // The listing exposes ONLY the chosen fields — herd_number/notes stay private.
        $fields = app(ForSaleListingPresenter::class)->sharedFields($cattle);
        $keys = array_column($fields, 'key');
        $this->assertEqualsCanonicalizing(['reg_name', 'breed'], $keys);
        $this->assertNotContains('herd_number', $keys);
        $this->assertNotContains('notes', $keys);
    }

    public function test_unknown_shared_fields_are_rejected(): void
    {
        [$owner, $team] = $this->ownerWithTeam();
        $cattle = Cattle::factory()->for($team)->create();

        $this->actingAs($owner)
            ->put(route('cattle.for-sale.update', $cattle), [
                'for_sale' => true,
                'shared_fields' => ['reg_name', 'team_id'],
            ])
            ->assertSessionHasErrors('shared_fields.1');
    }

    public function test_removing_a_listing_clears_the_shared_fields(): void
    {
        [$owner, $team] = $this->ownerWithTeam();
        $cattle = Cattle::factory()->for($team)->create([
            'for_sale' => true,
            'for_sale_shared_fields' => ['reg_name'],
            'for_sale_listed_by_role' => 'client',
        ]);

        $this->actingAs($owner)
            ->put(route('cattle.for-sale.update', $cattle), ['for_sale' => false])
            ->assertRedirect();

        $cattle->refresh();
        $this->assertFalse($cattle->for_sale);
        $this->assertNull($cattle->for_sale_shared_fields);
    }

    public function test_board_is_visible_across_authenticated_clients(): void
    {
        [, $sellerTeam] = $this->ownerWithTeam();
        Cattle::factory()->for($sellerTeam)->create([
            'reg_name' => 'Daisy',
            'for_sale' => true,
            'for_sale_shared_fields' => ['reg_name'],
        ]);

        // A DIFFERENT client can see the listing on the shared board (§5.9).
        [$viewer] = $this->ownerWithTeam();

        $this->actingAs($viewer)
            ->get(route('for-sale.index'))
            ->assertInertia(fn ($page) => $page
                ->component('for-sale/Index')
                ->has('listings', 1)
                ->where('listings.0.fields.0.value', 'Daisy'));
    }

    public function test_inactive_animals_drop_off_the_board(): void
    {
        [, $team] = $this->ownerWithTeam();
        Cattle::factory()->for($team)->create([
            'for_sale' => true,
            'for_sale_shared_fields' => ['reg_name'],
            'status' => 'inactive',
        ]);

        [$viewer] = $this->ownerWithTeam();

        $this->actingAs($viewer)
            ->get(route('for-sale.index'))
            ->assertInertia(fn ($page) => $page->has('listings', 0));
    }

    public function test_board_show_exposes_only_shared_fields(): void
    {
        [$owner, $team] = $this->ownerWithTeam();
        $cattle = Cattle::factory()->for($team)->create([
            'reg_name' => 'Bessie',
            'herd_number' => '42',
            'for_sale' => true,
            'for_sale_shared_fields' => ['reg_name'],
        ]);

        $this->actingAs($owner)
            ->get(route('for-sale.show', $cattle))
            ->assertInertia(fn ($page) => $page
                ->component('for-sale/Show')
                ->has('listing.fields', 1)
                ->where('listing.fields.0.key', 'reg_name'));
    }

    public function test_staff_can_post_a_listing_for_any_animal(): void
    {
        [, $team] = $this->ownerWithTeam();
        $cattle = Cattle::factory()->for($team)->create(['reg_name' => 'Rex', 'for_sale' => false]);

        $staff = $this->makeStaff(User::factory()->create(['email_verified_at' => now()]));

        $this->actingAs($staff)
            ->put(route('cattle.for-sale.update', $cattle), [
                'for_sale' => true,
                'shared_fields' => ['reg_name'],
            ])
            ->assertRedirect();

        $cattle->refresh();
        $this->assertTrue($cattle->for_sale);
        $this->assertSame('staff', $cattle->for_sale_listed_by_role);
    }

    public function test_a_client_cannot_manage_another_teams_listing(): void
    {
        [$owner] = $this->ownerWithTeam();
        $otherCattle = Cattle::factory()->create(); // different team

        $this->actingAs($owner)
            ->put(route('cattle.for-sale.update', $otherCattle), ['for_sale' => true])
            ->assertForbidden();
    }

    public function test_board_is_not_public(): void
    {
        [, $team] = $this->ownerWithTeam();
        $cattle = Cattle::factory()->for($team)->create(['for_sale' => true, 'for_sale_shared_fields' => ['reg_name']]);

        $this->get(route('for-sale.index'))->assertRedirect(route('login'));
        $this->get(route('for-sale.show', $cattle))->assertRedirect(route('login'));
    }

    public function test_no_transaction_flow_exists(): void
    {
        // A bulletin board, not a marketplace (§5.9): no purchase/checkout route.
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName() ?? '';
            $uri = $route->uri();
            $this->assertStringNotContainsStringIgnoringCase('purchase', $name.' '.$uri);
            $this->assertStringNotContainsStringIgnoringCase('checkout', $name.' '.$uri);
        }
    }
}
