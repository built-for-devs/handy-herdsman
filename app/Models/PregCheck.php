<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PregCheck — blood is async (pending -> open|bred|recheck); palpation is
 * immediate. Downstream nurture fires ONLY on a final state (§10b).
 */
class PregCheck extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'team_id', 'cattle_id', 'visit_id', 'method', 'lab_requested',
        'state', 'result_recorded_at', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'lab_requested' => 'boolean',
            'result_recorded_at' => 'datetime',
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
}
