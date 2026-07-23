<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * HealthRecord — per-animal log. Clients cannot edit/delete staff-added
 * records; Jeff can edit client-added records, attributed. BCS 1-9 captured on
 * breeding visits (§5.5, §5.6c, §10b).
 */
class HealthRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'team_id', 'cattle_id', 'visit_id', 'type', 'payload',
        'bcs_score', 'recorded_at', 'added_by', 'added_role',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function cattle(): BelongsTo
    {
        return $this->belongsTo(Cattle::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
