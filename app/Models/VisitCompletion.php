<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * VisitCompletion — the keystone record. Its completed_at is authoritative and
 * is what V3 recomputes from. Drives inventory, records, and COGS (§5.5, §10b).
 */
class VisitCompletion extends Model
{
    protected $fillable = [
        'visit_id', 'completed_at', 'procedure_confirmed',
        'semen_inventory_id', 'straws_used', 'straws_wasted',
        'supplies_used', 'mileage', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'procedure_confirmed' => 'boolean',
            'supplies_used' => 'array',
            'mileage' => 'decimal:2',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function semenInventory(): BelongsTo
    {
        return $this->belongsTo(SemenInventory::class);
    }
}
