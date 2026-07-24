<?php

namespace Tests\Feature\Semen;

use App\Models\RateConfig;
use App\Models\SemenInventory;
use App\Models\SemenLedgerEntry;
use App\Services\SemenCustodyService;
use Carbon\CarbonImmutable;
use Database\Seeders\RateConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SemenStorageFeeTest extends TestCase
{
    use RefreshDatabase;

    private SemenCustodyService $custody;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RateConfigSeeder::class);
        $this->custody = app(SemenCustodyService::class);
    }

    /**
     * @return array<string, array{bool, int, float}>
     */
    public static function storageFeeCases(): array
    {
        return [
            // with_ai, storage year, expected fee
            'AI year 1 is free' => [true, 1, 0.0],
            'AI year 2 charges $50' => [true, 2, 50.0],
            'AI year 3 charges $50' => [true, 3, 50.0],
            'no-AI year 1 charges $50' => [false, 1, 50.0],
            'no-AI year 2 charges $50' => [false, 2, 50.0],
        ];
    }

    #[DataProvider('storageFeeCases')]
    public function test_storage_fee_year_one_free_with_ai_then_fifty(bool $withAi, int $year, float $expected): void
    {
        $lot = SemenInventory::factory()->create(['with_ai' => $withAi]);

        $this->assertSame($expected, $this->custody->storageFeeForYear($lot, $year));
    }

    public function test_storage_fee_is_config_driven(): void
    {
        RateConfig::updateOrCreate(
            ['key' => 'semen_storage_annual'],
            ['value' => ['price' => 75, 'free_year_one' => true, 'max_straws' => 10], 'label' => 'x', 'group' => 'semen'],
        );

        $lot = SemenInventory::factory()->create(['with_ai' => true]);

        $this->assertSame(0.0, $this->custody->storageFeeForYear($lot, 1));
        $this->assertSame(75.0, $this->custody->storageFeeForYear($lot, 2));
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function storageYearCases(): array
    {
        return [
            'same day is year 1' => ['2026-01-01', '2026-01-01', 1],
            '11 months in is year 1' => ['2026-01-01', '2026-12-01', 1],
            'exactly one year is year 2' => ['2026-01-01', '2027-01-01', 2],
            'two and a half years is year 3' => ['2026-01-01', '2028-07-01', 3],
        ];
    }

    #[DataProvider('storageYearCases')]
    public function test_storage_year_as_of(string $start, string $asOf, int $expectedYear): void
    {
        $lot = SemenInventory::factory()->create(['storage_start' => $start]);

        $this->assertSame($expectedYear, $this->custody->storageYearAsOf($lot, CarbonImmutable::parse($asOf)));
    }

    public function test_charging_annual_storage_records_a_ledger_entry_with_the_fee(): void
    {
        $lot = SemenInventory::factory()->withAi()->create(['storage_start' => '2026-01-01']);

        $freeEntry = $this->custody->chargeAnnualStorage($lot, 1);
        $paidEntry = $this->custody->chargeAnnualStorage($lot, 2);

        $this->assertSame(SemenLedgerEntry::FEE_STORAGE, $freeEntry->fee_type);
        $this->assertSame('0.00', $freeEntry->fee_amount);
        $this->assertSame(1, $freeEntry->storage_year);
        $this->assertSame('50.00', $paidEntry->fee_amount);
        $this->assertSame(2, $paidEntry->storage_year);
    }

    public function test_receiving_with_ai_sets_storage_free_until_one_year_out(): void
    {
        $lot = SemenInventory::factory()->withAi()->create(['straws_count' => 0]);

        $this->custody->receiveShipment($lot, 4, CarbonImmutable::parse('2026-05-01'));

        $this->assertSame('2027-05-01', $lot->fresh()->storage_free_until->toDateString());
    }
}
