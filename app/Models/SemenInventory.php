<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SemenInventory — custody/storage ledger. We do NOT sell straws; owned_by is
 * always the client. Straws never expire (§5.6, §10b).
 */
class SemenInventory extends Model
{
    use SoftDeletes;

    protected $table = 'semen_inventory';

    protected $fillable = [
        'team_id', 'sire', 'breed', 'straws_count', 'source', 'source_contact',
        'location', 'owned_by', 'storage_start', 'storage_free_until', 'receipt_fee_charged',
    ];

    protected function casts(): array
    {
        return [
            'storage_start' => 'date',
            'storage_free_until' => 'date',
            'receipt_fee_charged' => 'boolean',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
