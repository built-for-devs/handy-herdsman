<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SupplyUsageProfile — default consumables per service, auto-decremented on
 * completion, manually adjustable (§5.6b).
 */
class SupplyUsageProfile extends Model
{
    protected $fillable = [
        'service_id', 'consumables',
    ];

    protected function casts(): array
    {
        return [
            'consumables' => 'array',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
