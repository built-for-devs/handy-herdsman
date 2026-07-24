<?php

namespace App\Models;

use Database\Factories\SemenLedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SemenLedgerEntry — one append-only movement in a custody lot's history
 * (§5.6, §10b). Movements never expire and are never hard-deleted so the
 * custody trail stays auditable.
 */
class SemenLedgerEntry extends Model
{
    /** @use HasFactory<SemenLedgerEntryFactory> */
    use HasFactory, SoftDeletes;

    public const TYPE_RECEIVED = 'received';

    public const TYPE_STORED = 'stored';

    public const TYPE_USED = 'used';

    public const TYPE_TRANSFERRED = 'transferred';

    public const FEE_RECEIPT = 'receipt';

    public const FEE_STORAGE = 'storage';

    protected $fillable = [
        'semen_inventory_id', 'team_id', 'type', 'straws_delta',
        'fee_type', 'fee_amount', 'storage_year',
        'location_from', 'location_to', 'recorded_by', 'occurred_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'straws_delta' => 'integer',
            'fee_amount' => 'decimal:2',
            'storage_year' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(SemenInventory::class, 'semen_inventory_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
