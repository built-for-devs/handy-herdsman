<?php

namespace App\Models;

use Database\Factories\SupplyUsageProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SupplyUsageProfile — the default consumables a service burns, keyed by
 * supply id => quantity. Auto-decremented on appointment completion (M7 calls
 * the decrement service), manually adjustable when actual use differs (§5.6b).
 */
class SupplyUsageProfile extends Model
{
    /** @use HasFactory<SupplyUsageProfileFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'service_id', 'consumables', 'notes',
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
