<?php

namespace App\Models;

use Database\Factories\SemenInventoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SemenInventory — a custody/storage lot. We do NOT sell straws; owned_by is
 * ALWAYS the client (§5.6, §10b). Straws never expire — there is deliberately
 * no expiry field. Movements are tracked on the append-only ledger
 * (SemenLedgerEntry); this row is the current custody snapshot.
 */
class SemenInventory extends Model
{
    /** @use HasFactory<SemenInventoryFactory> */
    use HasFactory, SoftDeletes;

    /** owned_by is always the client — we only ever hold straws in custody. */
    public const OWNER_CLIENT = 'client';

    /** Sourcing paths (§5.6). */
    public const SOURCE_CLIENT = 'client';

    public const SOURCE_JEFF = 'jeff';

    protected $table = 'semen_inventory';

    protected $fillable = [
        'team_id', 'sire', 'breed', 'straws_count',
        'source', 'source_farm', 'bull_info', 'source_contact', 'pay_to',
        'location', 'intake_shipping_address', 'tank_details', 'expected_arrival', 'arrived_at',
        'owned_by', 'storage_start', 'storage_free_until', 'with_ai', 'receipt_fee_charged',
    ];

    protected function casts(): array
    {
        return [
            'straws_count' => 'integer',
            'expected_arrival' => 'date',
            'arrived_at' => 'date',
            'storage_start' => 'date',
            'storage_free_until' => 'date',
            'with_ai' => 'boolean',
            'receipt_fee_charged' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Custody only: owned_by is always the client, no matter what is set.
        static::saving(function (SemenInventory $lot) {
            $lot->owned_by = self::OWNER_CLIENT;
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SemenLedgerEntry::class);
    }

    /** True once the client-sourced straws have physically arrived. */
    public function hasArrived(): bool
    {
        return $this->arrived_at !== null;
    }
}
