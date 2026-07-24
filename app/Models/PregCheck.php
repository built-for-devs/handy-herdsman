<?php

namespace App\Models;

use App\Enums\PregCheckMethod;
use App\Enums\PregCheckState;
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
            'method' => PregCheckMethod::class,
            'state' => PregCheckState::class,
            'lab_requested' => 'boolean',
            'result_recorded_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function cattle(): BelongsTo
    {
        return $this->belongsTo(Cattle::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** Awaiting a lab result — nurture must NOT fire yet (§10b). */
    public function isPending(): bool
    {
        return $this->state === PregCheckState::Pending;
    }
}
