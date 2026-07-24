<?php

namespace Tests\Feature\Supplies;

use App\Models\Service;
use App\Models\Supply;
use App\Models\SupplyUsageProfile;
use App\Services\SupplyUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Tests\TestCase;

class SupplyUsageServiceTest extends TestCase
{
    use RefreshDatabase;

    private SupplyUsageService $usage;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->usage = app(SupplyUsageService::class);
    }

    private function serviceWithProfile(array $consumables): Service
    {
        $service = Service::factory()->create();
        SupplyUsageProfile::factory()->create([
            'service_id' => $service->id,
            'consumables' => $consumables,
        ]);

        return $service;
    }

    public function test_applying_a_profile_auto_decrements_each_supply(): void
    {
        $cidr = Supply::factory()->create(['on_hand' => 20]);
        $gnrh = Supply::factory()->create(['on_hand' => 20]);
        $service = $this->serviceWithProfile([$cidr->id => 1, $gnrh->id => 2]);

        $result = $this->usage->applyForService($service);

        $this->assertEquals(19, (float) $cidr->fresh()->on_hand);
        $this->assertEquals(18, (float) $gnrh->fresh()->on_hand);
        $this->assertSame([$cidr->id => 1.0, $gnrh->id => 2.0], $result['consumption']);
    }

    public function test_manual_adjustment_overrides_the_default_quantity(): void
    {
        $gnrh = Supply::factory()->create(['on_hand' => 20]);
        $service = $this->serviceWithProfile([$gnrh->id => 2]);

        // Jeff actually used 3 doses, not the default 2.
        $this->usage->applyForService($service, [$gnrh->id => 3]);

        $this->assertEquals(17, (float) $gnrh->fresh()->on_hand);
    }

    public function test_manual_adjustment_can_add_a_supply_not_in_the_profile(): void
    {
        $cidr = Supply::factory()->create(['on_hand' => 20]);
        $extraTag = Supply::factory()->create(['on_hand' => 50]);
        $service = $this->serviceWithProfile([$cidr->id => 1]);

        $this->usage->applyForService($service, [$extraTag->id => 4]);

        $this->assertEquals(19, (float) $cidr->fresh()->on_hand);
        $this->assertEquals(46, (float) $extraTag->fresh()->on_hand);
    }

    public function test_manual_adjustment_of_zero_removes_a_default_supply(): void
    {
        $cidr = Supply::factory()->create(['on_hand' => 20]);
        $service = $this->serviceWithProfile([$cidr->id => 1]);

        $result = $this->usage->applyForService($service, [$cidr->id => 0]);

        $this->assertEquals(20, (float) $cidr->fresh()->on_hand);
        $this->assertSame([], $result['consumption']);
    }

    public function test_total_cost_is_computed_from_unit_costs(): void
    {
        $cidr = Supply::factory()->create(['on_hand' => 20, 'unit_cost' => 12.50]);
        $gnrh = Supply::factory()->create(['on_hand' => 20, 'unit_cost' => 3.00]);
        $service = $this->serviceWithProfile([$cidr->id => 1, $gnrh->id => 2]);

        $result = $this->usage->applyForService($service);

        // 1 * 12.50 + 2 * 3.00 = 18.50
        $this->assertSame(18.5, $result['total_cost']);
    }

    public function test_service_without_a_profile_consumes_nothing(): void
    {
        $service = Service::factory()->create();

        $result = $this->usage->applyForService($service);

        $this->assertSame([], $result['consumption']);
        $this->assertSame(0.0, $result['total_cost']);
    }

    public function test_unknown_supply_id_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->usage->decrement([999999 => 1]);
    }
}
