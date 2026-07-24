<?php

namespace App\Services;

use App\Models\RateConfig;
use App\Models\SemenInventory;
use App\Models\SemenLedgerEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * SemenCustodyService — the write path for the custody ledger (§5.6, §10b).
 *
 * We only ever hold straws in custody (never sell them), so every movement is
 * appended to the ledger and the lot's straw count is kept in sync. Fees are
 * config-driven (rate_config), never hardcoded. Straws never expire, so nothing
 * here reasons about shelf life.
 */
class SemenCustodyService
{
    /** $15 receipt fee per shipment (config-driven, §5.6). */
    public function receiptFee(): float
    {
        $cfg = RateConfig::value('semen_receipt_fee', []);

        return (float) ($cfg['price'] ?? 0);
    }

    /** Annual storage price (config-driven, §5.6). */
    public function storagePrice(): float
    {
        $cfg = RateConfig::value('semen_storage_annual', []);

        return (float) ($cfg['price'] ?? 0);
    }

    /** Whether year 1 is free when paired with AI (config-driven). */
    public function storageFreeYearOne(): bool
    {
        $cfg = RateConfig::value('semen_storage_annual', []);

        return (bool) ($cfg['free_year_one'] ?? false);
    }

    /** Straw ceiling the flat storage fee covers (config-driven). */
    public function storageMaxStraws(): int
    {
        $cfg = RateConfig::value('semen_storage_annual', []);

        return (int) ($cfg['max_straws'] ?? 0);
    }

    /**
     * Record an incoming shipment: appends a `received` entry, applies the
     * per-shipment receipt fee, and grows the lot's straw count. Marks the lot
     * as arrived and starts the storage clock on first receipt.
     */
    public function receiveShipment(
        SemenInventory $lot,
        int $straws,
        ?CarbonInterface $occurredAt = null,
        ?User $recordedBy = null,
        ?string $notes = null,
    ): SemenLedgerEntry {
        if ($straws <= 0) {
            throw new InvalidArgumentException('A shipment must receive at least one straw.');
        }

        $occurredAt = CarbonImmutable::make($occurredAt) ?? CarbonImmutable::now();

        return DB::transaction(function () use ($lot, $straws, $occurredAt, $recordedBy, $notes) {
            $entry = $lot->ledgerEntries()->create([
                'team_id' => $lot->team_id,
                'type' => SemenLedgerEntry::TYPE_RECEIVED,
                'straws_delta' => $straws,
                'fee_type' => SemenLedgerEntry::FEE_RECEIPT,
                'fee_amount' => $this->receiptFee(),
                'location_to' => $lot->location,
                'recorded_by' => $recordedBy?->id,
                'occurred_at' => $occurredAt,
                'notes' => $notes,
            ]);

            $lot->straws_count += $straws;
            $lot->arrived_at ??= $occurredAt->toDateString();
            $lot->storage_start ??= $occurredAt->toDateString();
            if ($lot->with_ai && $this->storageFreeYearOne() && $lot->storage_free_until === null) {
                $lot->storage_free_until = CarbonImmutable::make($lot->storage_start)->addYear()->toDateString();
            }
            $lot->receipt_fee_charged = true;
            $lot->save();

            return $entry;
        });
    }

    /**
     * Record a storage/relocation movement (no straw-count change), e.g. moving
     * straws between tanks/canisters. Updates the lot's current location.
     */
    public function storeAt(
        SemenInventory $lot,
        string $locationTo,
        ?CarbonInterface $occurredAt = null,
        ?User $recordedBy = null,
        ?string $notes = null,
    ): SemenLedgerEntry {
        $occurredAt = CarbonImmutable::make($occurredAt) ?? CarbonImmutable::now();

        return DB::transaction(function () use ($lot, $locationTo, $occurredAt, $recordedBy, $notes) {
            $entry = $lot->ledgerEntries()->create([
                'team_id' => $lot->team_id,
                'type' => SemenLedgerEntry::TYPE_STORED,
                'straws_delta' => 0,
                'location_from' => $lot->location,
                'location_to' => $locationTo,
                'recorded_by' => $recordedBy?->id,
                'occurred_at' => $occurredAt,
                'notes' => $notes,
            ]);

            $lot->location = $locationTo;
            $lot->save();

            return $entry;
        });
    }

    /**
     * Record straws consumed at an appointment: appends a `used` entry and
     * shrinks the count. Never lets a lot go negative.
     */
    public function useStraws(
        SemenInventory $lot,
        int $straws,
        ?CarbonInterface $occurredAt = null,
        ?User $recordedBy = null,
        ?string $notes = null,
    ): SemenLedgerEntry {
        return $this->removeStraws($lot, $straws, SemenLedgerEntry::TYPE_USED, null, $occurredAt, $recordedBy, $notes);
    }

    /**
     * Record a wasted/failed straw at an appointment: appends a `wasted` entry
     * and shrinks the count. Kept separate from `used` so waste is visible and
     * counts stay honest (§5.5). Never lets a lot go negative.
     */
    public function wasteStraws(
        SemenInventory $lot,
        int $straws,
        ?CarbonInterface $occurredAt = null,
        ?User $recordedBy = null,
        ?string $notes = null,
    ): SemenLedgerEntry {
        return $this->removeStraws($lot, $straws, SemenLedgerEntry::TYPE_WASTED, null, $occurredAt, $recordedBy, $notes);
    }

    /**
     * Record straws transferred out of our custody (e.g. moved to another
     * facility): appends a `transferred` entry and shrinks the count.
     */
    public function transferOut(
        SemenInventory $lot,
        int $straws,
        ?string $locationTo = null,
        ?CarbonInterface $occurredAt = null,
        ?User $recordedBy = null,
        ?string $notes = null,
    ): SemenLedgerEntry {
        return $this->removeStraws($lot, $straws, SemenLedgerEntry::TYPE_TRANSFERRED, $locationTo, $occurredAt, $recordedBy, $notes);
    }

    /**
     * The 1-based storage year a given date falls into, counting from the
     * lot's storage_start. Straws never expire, so this only ever grows.
     */
    public function storageYearAsOf(SemenInventory $lot, ?CarbonInterface $asOf = null): int
    {
        $start = CarbonImmutable::make($lot->storage_start) ?? CarbonImmutable::now();
        $asOf = CarbonImmutable::make($asOf) ?? CarbonImmutable::now();

        if ($asOf->lessThan($start)) {
            return 1;
        }

        return $start->diffInYears($asOf) + 1;
    }

    /**
     * Storage fee owed for a specific 1-based storage year. Free in year 1 when
     * the lot is paired with AI; the configured flat price otherwise (§5.6).
     */
    public function storageFeeForYear(SemenInventory $lot, int $year): float
    {
        if ($year < 1) {
            throw new InvalidArgumentException('Storage year must be 1 or greater.');
        }

        if ($year === 1 && $lot->with_ai && $this->storageFreeYearOne()) {
            return 0.0;
        }

        return $this->storagePrice();
    }

    /**
     * Charge (record) annual storage for a lot's given storage year. Appends a
     * `stored` entry carrying the storage fee so billing history is auditable.
     */
    public function chargeAnnualStorage(
        SemenInventory $lot,
        int $year,
        ?CarbonInterface $occurredAt = null,
        ?User $recordedBy = null,
    ): SemenLedgerEntry {
        $occurredAt = CarbonImmutable::make($occurredAt) ?? CarbonImmutable::now();

        return $lot->ledgerEntries()->create([
            'team_id' => $lot->team_id,
            'type' => SemenLedgerEntry::TYPE_STORED,
            'straws_delta' => 0,
            'fee_type' => SemenLedgerEntry::FEE_STORAGE,
            'fee_amount' => $this->storageFeeForYear($lot, $year),
            'storage_year' => $year,
            'location_from' => $lot->location,
            'location_to' => $lot->location,
            'recorded_by' => $recordedBy?->id,
            'occurred_at' => $occurredAt,
        ]);
    }

    private function removeStraws(
        SemenInventory $lot,
        int $straws,
        string $type,
        ?string $locationTo,
        ?CarbonInterface $occurredAt,
        ?User $recordedBy,
        ?string $notes,
    ): SemenLedgerEntry {
        if ($straws <= 0) {
            throw new InvalidArgumentException('Straw count must be positive.');
        }

        if ($straws > $lot->straws_count) {
            throw new InvalidArgumentException('Cannot remove more straws than are in custody.');
        }

        $occurredAt = CarbonImmutable::make($occurredAt) ?? CarbonImmutable::now();

        return DB::transaction(function () use ($lot, $straws, $type, $locationTo, $occurredAt, $recordedBy, $notes) {
            $entry = $lot->ledgerEntries()->create([
                'team_id' => $lot->team_id,
                'type' => $type,
                'straws_delta' => -$straws,
                'location_from' => $lot->location,
                'location_to' => $locationTo,
                'recorded_by' => $recordedBy?->id,
                'occurred_at' => $occurredAt,
                'notes' => $notes,
            ]);

            $lot->straws_count -= $straws;
            $lot->save();

            return $entry;
        });
    }
}
