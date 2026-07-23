<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ServiceAreaRule — mileage thresholds, fee tiers, declined zones (§2, §10b).
 */
class ServiceAreaRule extends Model
{
    protected $fillable = [
        'type', 'min_miles', 'max_miles', 'fee', 'zone_label', 'declined', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_miles' => 'decimal:2',
            'max_miles' => 'decimal:2',
            'fee' => 'decimal:2',
            'declined' => 'boolean',
        ];
    }
}
