<?php

namespace App\Models;

use Database\Factories\SupplyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Supply — Jeff's own working stock. Low-stock thresholds drive reorder alerts;
 * unit_cost feeds COGS reporting (§5.6b, §5.6c).
 */
class Supply extends Model
{
    /** @use HasFactory<SupplyFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'supplies';

    protected $fillable = [
        'item', 'category', 'unit', 'on_hand', 'low_stock_threshold',
        'unit_cost', 'is_prescription', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'on_hand' => 'decimal:2',
            'low_stock_threshold' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'is_prescription' => 'boolean',
        ];
    }

    /** At or below the reorder threshold (DB-driven per item). */
    public function isLowStock(): bool
    {
        return (float) $this->on_hand <= (float) $this->low_stock_threshold;
    }

    /** Scope to supplies at or below their reorder threshold. */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('on_hand', '<=', 'low_stock_threshold');
    }
}
