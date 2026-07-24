<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id', 'cattle_id', 'visit_id', 'type', 'payload',
        'bcs_score', 'recorded_at', 'added_by', 'added_role',
        'edited_by', 'edited_role', 'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'recorded_at' => 'datetime',
            'edited_at' => 'datetime',
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

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    /** Staff-authored records are Jeff's professional record — clients may not touch them (§10b). */
    public function isStaffAuthored(): bool
    {
        return $this->added_role === 'staff';
    }
}
