<?php

namespace Tests\Feature\Admin;

use App\Models\SemenInventory;
use App\Models\Supply;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RateConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InventoryAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RateConfigSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        Role::findOrCreate('staff', 'web');
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        // model_has_roles.team_id is NOT NULL in teams mode; assign under a
        // team context. Staff gating resolves off the role pivot regardless of
        // team, so any team id works here.
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);
        $user->assignRole('staff');

        return $user;
    }

    public function test_guests_are_redirected_from_admin_inventory(): void
    {
        $this->get('/admin/supplies')->assertRedirect('/login');
        $this->get('/admin/semen')->assertRedirect('/login');
    }

    public function test_non_staff_users_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/supplies')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/semen')->assertForbidden();
    }

    public function test_staff_can_view_inventory_screens(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->get('/admin/supplies')->assertOk();
        $this->actingAs($staff)->get('/admin/semen')->assertOk();
    }

    public function test_staff_can_create_a_supply(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->post('/admin/supplies', [
            'item' => 'CIDR',
            'category' => 'drug',
            'unit' => 'each',
            'on_hand' => 25,
            'low_stock_threshold' => 5,
            'unit_cost' => 12.5,
            'is_prescription' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('supplies', ['item' => 'CIDR', 'is_prescription' => true]);
    }

    public function test_staff_can_create_a_custody_lot_and_log_a_shipment(): void
    {
        $staff = $this->staff();
        $team = Team::factory()->create();

        $this->actingAs($staff)->post('/admin/semen', [
            'team_id' => $team->id,
            'source' => 'jeff',
            'sire' => 'Test Bull',
            'breed' => 'Angus',
            'source_farm' => 'Genetics Co',
            'owned_by' => 'jeff', // must be ignored — custody only
        ])->assertRedirect();

        $lot = SemenInventory::firstOrFail();
        $this->assertSame('client', $lot->owned_by);

        $this->actingAs($staff)->post("/admin/semen/{$lot->id}/receive", [
            'straws' => 6,
        ])->assertRedirect();

        $this->assertSame(6, $lot->fresh()->straws_count);
        $this->assertDatabaseHas('semen_ledger_entries', [
            'semen_inventory_id' => $lot->id,
            'type' => 'received',
            'fee_type' => 'receipt',
        ]);
    }

    public function test_low_stock_supply_appears_in_index_payload(): void
    {
        $staff = $this->staff();
        Supply::factory()->create(['item' => 'AI sleeve', 'on_hand' => 1, 'low_stock_threshold' => 10]);

        $this->actingAs($staff)
            ->get('/admin/supplies')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/supplies/Index')
                ->has('lowStock', 1));
    }
}
