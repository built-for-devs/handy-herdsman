<?php

namespace Tests\Feature\Semen;

use App\Models\SemenInventory;
use App\Models\SemenLedgerEntry;
use App\Services\SemenCustodyService;
use Carbon\CarbonImmutable;
use Database\Seeders\RateConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SemenCustodyLedgerTest extends TestCase
{
    use RefreshDatabase;

    private SemenCustodyService $custody;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RateConfigSeeder::class);
        $this->custody = app(SemenCustodyService::class);
    }

    public function test_owned_by_is_always_the_client_even_when_set_otherwise(): void
    {
        $lot = SemenInventory::factory()->create(['owned_by' => 'jeff']);

        $this->assertSame('client', $lot->fresh()->owned_by);

        $lot->update(['owned_by' => 'someone_else']);

        $this->assertSame('client', $lot->fresh()->owned_by);
    }

    public function test_client_sourced_lot_captures_intake_info(): void
    {
        $lot = SemenInventory::factory()->clientSourced()->create();

        $this->assertSame(SemenInventory::SOURCE_CLIENT, $lot->source);
        $this->assertNotNull($lot->intake_shipping_address);
        $this->assertNotNull($lot->tank_details);
        $this->assertNotNull($lot->expected_arrival);
    }

    public function test_jeff_sourced_lot_captures_farm_bull_contact_and_pay_to(): void
    {
        $lot = SemenInventory::factory()->jeffSourced()->create();

        $this->assertSame(SemenInventory::SOURCE_JEFF, $lot->source);
        $this->assertNotNull($lot->source_farm);
        $this->assertNotNull($lot->bull_info);
        $this->assertNotNull($lot->source_contact);
        $this->assertNotNull($lot->pay_to);
    }

    public function test_receiving_a_shipment_appends_a_received_entry_with_the_receipt_fee(): void
    {
        $lot = SemenInventory::factory()->create(['straws_count' => 0]);

        $entry = $this->custody->receiveShipment($lot, 8);

        $this->assertSame(SemenLedgerEntry::TYPE_RECEIVED, $entry->type);
        $this->assertSame(8, $entry->straws_delta);
        $this->assertSame(SemenLedgerEntry::FEE_RECEIPT, $entry->fee_type);
        $this->assertSame('15.00', $entry->fee_amount);
        $this->assertSame(8, $lot->fresh()->straws_count);
        $this->assertTrue($lot->fresh()->receipt_fee_charged);
        $this->assertNotNull($lot->fresh()->arrived_at);
    }

    public function test_each_shipment_charges_its_own_receipt_fee(): void
    {
        $lot = SemenInventory::factory()->create(['straws_count' => 0]);

        $this->custody->receiveShipment($lot, 5);
        $this->custody->receiveShipment($lot, 3);

        $this->assertSame(8, $lot->fresh()->straws_count);
        $this->assertSame(2, $lot->ledgerEntries()->where('fee_type', 'receipt')->count());
        $this->assertEquals(30.0, (float) $lot->ledgerEntries()->where('fee_type', 'receipt')->sum('fee_amount'));
    }

    public function test_receiving_zero_or_negative_straws_is_rejected(): void
    {
        $lot = SemenInventory::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->custody->receiveShipment($lot, 0);
    }

    public function test_using_straws_appends_a_used_entry_and_decrements_the_count(): void
    {
        $lot = SemenInventory::factory()->create(['straws_count' => 10]);

        $entry = $this->custody->useStraws($lot, 3);

        $this->assertSame(SemenLedgerEntry::TYPE_USED, $entry->type);
        $this->assertSame(-3, $entry->straws_delta);
        $this->assertSame(7, $lot->fresh()->straws_count);
    }

    public function test_cannot_use_more_straws_than_are_in_custody(): void
    {
        $lot = SemenInventory::factory()->create(['straws_count' => 2]);

        $this->expectException(InvalidArgumentException::class);
        $this->custody->useStraws($lot, 3);
    }

    public function test_transferring_out_decrements_and_records_destination(): void
    {
        $lot = SemenInventory::factory()->create(['straws_count' => 6, 'location' => 'Tank A']);

        $entry = $this->custody->transferOut($lot, 2, 'Neighbor farm');

        $this->assertSame(SemenLedgerEntry::TYPE_TRANSFERRED, $entry->type);
        $this->assertSame(-2, $entry->straws_delta);
        $this->assertSame('Tank A', $entry->location_from);
        $this->assertSame('Neighbor farm', $entry->location_to);
        $this->assertSame(4, $lot->fresh()->straws_count);
    }

    public function test_relocating_updates_current_location_without_changing_count(): void
    {
        $lot = SemenInventory::factory()->create(['straws_count' => 5, 'location' => 'Tank A']);

        $entry = $this->custody->storeAt($lot, 'Tank B / Canister 3');

        $this->assertSame(SemenLedgerEntry::TYPE_STORED, $entry->type);
        $this->assertSame(0, $entry->straws_delta);
        $this->assertSame('Tank A', $entry->location_from);
        $this->assertSame('Tank B / Canister 3', $entry->location_to);
        $this->assertSame('Tank B / Canister 3', $lot->fresh()->location);
        $this->assertSame(5, $lot->fresh()->straws_count);
    }

    public function test_ledger_is_append_only_history(): void
    {
        $lot = SemenInventory::factory()->create(['straws_count' => 0]);

        $this->custody->receiveShipment($lot, 10, CarbonImmutable::parse('2026-01-01'));
        $this->custody->useStraws($lot, 4, CarbonImmutable::parse('2026-02-01'));
        $this->custody->transferOut($lot, 1, 'Elsewhere', CarbonImmutable::parse('2026-03-01'));

        $types = $lot->ledgerEntries()->orderBy('occurred_at')->pluck('type')->all();

        $this->assertSame(['received', 'used', 'transferred'], $types);
        $this->assertSame(5, $lot->fresh()->straws_count);
    }

    public function test_lots_and_ledger_entries_soft_delete(): void
    {
        $lot = SemenInventory::factory()->create();
        $entry = $this->custody->receiveShipment($lot, 2);

        $lot->delete();
        $entry->delete();

        $this->assertSoftDeleted($lot);
        $this->assertSoftDeleted($entry);
    }
}
