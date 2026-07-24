<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Booking;
use App\Models\RateConfig;
use App\Models\Service;
use App\Services\Billing\FeeCalculator;
use App\Services\Billing\ServiceCharge;
use Database\Seeders\RateConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * #243 8.2 — Fee calculation. Config-driven booking totals (§2, §5.2b, §6.6,
 * §10b): plan + additional-cow + distance + receipt + storage, with a single
 * distance fee per booking, a single visit minimum per trip, and NO sales tax.
 */
class FeeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RateConfigSeeder::class);
    }

    private function calculator(): FeeCalculator
    {
        return app(FeeCalculator::class);
    }

    public function test_three_visit_protocol_at_twenty_miles_charges_one_distance_fee(): void
    {
        $plan = ['model' => 'flat', 'price' => 300, 'farm_calls' => 3, 'included_cows' => 2, 'per_additional_cow' => 100];

        $breakdown = $this->calculator()->calculate(
            [new ServiceCharge($plan, headcount: 1, label: 'AI — Sync')],
            distanceFeeApplies: true,
        );

        // $300 base + ONE $30 distance fee — never $30 × 3 visits.
        $this->assertSame(30.0, $breakdown->distanceFee());
        $this->assertSame(330.0, $breakdown->total());
        $this->assertCount(1, array_filter($breakdown->lines, fn ($l) => $l->code === 'distance_fee'));
    }

    public function test_additional_cows_beyond_the_included_count_are_billed_per_head(): void
    {
        $plan = ['model' => 'flat', 'price' => 300, 'included_cows' => 2, 'per_additional_cow' => 100];

        // 4 cows: 2 included, 2 additional × $100.
        $breakdown = $this->calculator()->calculate([new ServiceCharge($plan, headcount: 4)]);

        $this->assertSame(200.0, $breakdown->amountFor('additional_cow'));
        $this->assertSame(500.0, $breakdown->total());
    }

    public function test_two_per_head_tasks_on_one_visit_share_a_single_visit_minimum(): void
    {
        $vaccinate = ['model' => 'per_head', 'per_head' => 10, 'visit_minimum' => 50];
        $deworm = ['model' => 'per_head', 'per_head' => 8, 'visit_minimum' => 50];

        // One head: $10 + $8 = $18, floored ONCE to the $50 visit minimum — not
        // $50 + $50 = $100 (one minimum per service).
        $breakdown = $this->calculator()->calculate([
            new ServiceCharge($vaccinate, headcount: 1, label: 'Vaccinations'),
            new ServiceCharge($deworm, headcount: 1, label: 'Deworming'),
        ]);

        $this->assertTrue($breakdown->visitMinimumApplied);
        $this->assertSame(50.0, $breakdown->total());
        $this->assertCount(1, array_filter($breakdown->lines, fn ($l) => $l->code === 'visit_minimum_adjustment'));
    }

    public function test_bundled_per_head_tasks_above_the_minimum_apply_no_minimum(): void
    {
        $vaccinate = ['model' => 'per_head', 'per_head' => 10, 'visit_minimum' => 50];
        $deworm = ['model' => 'per_head', 'per_head' => 8, 'visit_minimum' => 50];

        // 4 head: (10 + 8) × 4 = $72 > $50, so no minimum top-up.
        $breakdown = $this->calculator()->calculate([
            new ServiceCharge($vaccinate, headcount: 4),
            new ServiceCharge($deworm, headcount: 4),
        ]);

        $this->assertFalse($breakdown->visitMinimumApplied);
        $this->assertSame(72.0, $breakdown->total());
    }

    public function test_editing_a_rate_config_value_changes_totals_with_no_code_change(): void
    {
        $plan = ['model' => 'flat', 'price' => 300];

        $before = $this->calculator()->calculate([new ServiceCharge($plan)], distanceFeeApplies: true);
        $this->assertSame(330.0, $before->total());

        // Jeff edits the distance fee in rate_config — no deploy (§7).
        RateConfig::where('key', 'distance_fee')->update(['value' => ['price' => 45, 'threshold_miles' => 15, 'per' => 'booking']]);

        $after = $this->calculator()->calculate([new ServiceCharge($plan)], distanceFeeApplies: true);
        $this->assertSame(45.0, $after->distanceFee());
        $this->assertSame(345.0, $after->total());
    }

    public function test_visit_minimum_falls_back_to_rate_config_when_not_on_the_service(): void
    {
        // A per-head rule with no baked-in minimum reads the shared rate_config
        // visit_minimum (§7).
        $rule = ['model' => 'per_head', 'per_head' => 5];

        $breakdown = $this->calculator()->calculate([new ServiceCharge($rule, headcount: 1)]);

        $this->assertSame(50.0, $breakdown->total());

        RateConfig::where('key', 'visit_minimum')->update(['value' => ['price' => 65, 'per' => 'visit']]);

        $breakdown = $this->calculator()->calculate([new ServiceCharge($rule, headcount: 1)]);
        $this->assertSame(65.0, $breakdown->total());
    }

    public function test_receipt_and_storage_fees_come_from_rate_config(): void
    {
        $breakdown = $this->calculator()->calculate(
            [new ServiceCharge(['model' => 'flat', 'price' => 100])],
            semenReceipts: 2,
            semenStorageCharges: 1,
        );

        // $100 service + 2 × $15 receipt + 1 × $50 storage.
        $this->assertSame(30.0, $breakdown->amountFor('semen_receipt'));
        $this->assertSame(50.0, $breakdown->amountFor('semen_storage'));
        $this->assertSame(180.0, $breakdown->total());
    }

    #[DataProvider('taxFreeScenarios')]
    public function test_no_sales_tax_is_ever_added(array $charge, bool $distance, float $expected): void
    {
        $breakdown = $this->calculator()->calculate([new ServiceCharge($charge)], distanceFeeApplies: $distance);

        // The total is exactly the sum of its lines — no tax line, no markup.
        $this->assertSame($expected, $breakdown->total());
        $this->assertSame(round(array_sum(array_map(fn ($l) => $l->amount, $breakdown->lines)), 2), $breakdown->total());
        $this->assertCount(0, array_filter($breakdown->lines, fn ($l) => str_contains(strtolower($l->code), 'tax')));
    }

    public static function taxFreeScenarios(): array
    {
        return [
            'flat no distance' => [['model' => 'flat', 'price' => 100], false, 100.0],
            'flat with distance' => [['model' => 'flat', 'price' => 100], true, 130.0],
            'per head floored' => [['model' => 'per_head', 'per_head' => 10, 'visit_minimum' => 50], false, 50.0],
        ];
    }

    public function test_for_booking_pulls_the_service_rule_and_per_booking_distance_flag(): void
    {
        $service = Service::factory()->syncPlan()->create();

        $booking = new Booking([
            'service_id' => $service->id,
            'distance_fee_flag' => true,
        ]);
        $booking->setRelation('service', $service);
        // No cattle attached → headcount floors to 1.
        $breakdown = $this->calculator()->forBooking($booking);

        $this->assertSame(330.0, $breakdown->total());
    }
}
