<?php

namespace Tests\Feature\Supplies;

use App\Models\Supply;
use App\Models\User;
use App\Notifications\LowSupplyStockNotification;
use App\Services\SupplyStockAlertService;
use App\Services\SupplyUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LowStockAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        Role::findOrCreate('staff', 'web');
    }

    private function makeStaff(): User
    {
        $user = User::factory()->create();
        // model_has_roles.team_id is NOT NULL in teams mode; assign under a
        // team context. Staff alerts are resolved off the role pivot regardless
        // of team, so any team id works here.
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);
        $user->assignRole('staff');

        return $user;
    }

    public function test_low_stock_scope_returns_items_at_or_below_threshold(): void
    {
        $low = Supply::factory()->create(['on_hand' => 5, 'low_stock_threshold' => 10]);
        $atThreshold = Supply::factory()->create(['on_hand' => 10, 'low_stock_threshold' => 10]);
        Supply::factory()->create(['on_hand' => 50, 'low_stock_threshold' => 10]);

        $ids = Supply::query()->lowStock()->pluck('id');

        $this->assertTrue($ids->contains($low->id));
        $this->assertTrue($ids->contains($atThreshold->id));
        $this->assertCount(2, $ids);
    }

    public function test_decrementing_below_threshold_alerts_staff(): void
    {
        $staff = $this->makeStaff();
        $supply = Supply::factory()->create(['on_hand' => 11, 'low_stock_threshold' => 10]);

        app(SupplyUsageService::class)->decrement([$supply->id => 2]);

        Notification::assertSentTo($staff, LowSupplyStockNotification::class);
    }

    public function test_decrement_that_stays_above_threshold_sends_no_alert(): void
    {
        $this->makeStaff();
        $supply = Supply::factory()->create(['on_hand' => 100, 'low_stock_threshold' => 10]);

        app(SupplyUsageService::class)->decrement([$supply->id => 2]);

        Notification::assertNothingSent();
    }

    public function test_scan_and_alert_notifies_staff_about_all_low_items(): void
    {
        $staff = $this->makeStaff();
        Supply::factory()->create(['on_hand' => 1, 'low_stock_threshold' => 10]);
        Supply::factory()->create(['on_hand' => 100, 'low_stock_threshold' => 10]);

        $low = app(SupplyStockAlertService::class)->scanAndAlert();

        $this->assertCount(1, $low);
        Notification::assertSentTo($staff, LowSupplyStockNotification::class);
    }

    public function test_no_alert_is_sent_when_nothing_is_low(): void
    {
        $this->makeStaff();
        Supply::factory()->create(['on_hand' => 100, 'low_stock_threshold' => 10]);

        app(SupplyStockAlertService::class)->scanAndAlert();

        Notification::assertNothingSent();
    }

    public function test_check_stock_command_runs_and_alerts(): void
    {
        $staff = $this->makeStaff();
        Supply::factory()->create(['item' => 'CIDR', 'on_hand' => 2, 'low_stock_threshold' => 10]);

        $this->artisan('supplies:check-stock')
            ->assertSuccessful();

        Notification::assertSentTo($staff, LowSupplyStockNotification::class);
    }
}
