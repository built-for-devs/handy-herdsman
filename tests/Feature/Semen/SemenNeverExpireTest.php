<?php

namespace Tests\Feature\Semen;

use App\Models\SemenInventory;
use App\Services\SemenCustodyService;
use Carbon\CarbonImmutable;
use Database\Seeders\RateConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SemenNeverExpireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RateConfigSeeder::class);
    }

    public function test_there_is_no_expiry_column_on_the_custody_lot(): void
    {
        $columns = Schema::getColumnListing('semen_inventory');

        foreach ($columns as $column) {
            $this->assertStringNotContainsString('expir', $column);
            $this->assertStringNotContainsString('expiry', $column);
        }
    }

    public function test_decades_old_straws_are_still_usable(): void
    {
        $custody = app(SemenCustodyService::class);
        $lot = SemenInventory::factory()->create([
            'straws_count' => 5,
            'storage_start' => '2005-01-01',
        ]);

        // Straws stored 20+ years ago remain fully usable — no shelf-life gate.
        $entry = $custody->useStraws($lot, 5, CarbonImmutable::parse('2026-01-01'));

        $this->assertSame(-5, $entry->straws_delta);
        $this->assertSame(0, $lot->fresh()->straws_count);
    }
}
