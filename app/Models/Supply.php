<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Supply — Jeff's own working stock. Low-stock alerts; unit_cost feeds COGS
 * (§5.6b).
 */
class Supply extends Model
{
    use SoftDeletes;

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
}
