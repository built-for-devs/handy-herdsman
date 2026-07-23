<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Protocol — a CIDR 10-day sync instance. V3 window is computed by animal type
 * and ALWAYS recomputed from Visit 2's actual completed timestamp (§3, §10b).
 */
class Protocol extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'team_id', 'booking_id', 'service_id', 'plan_type', 'animal_type',
        'visit1_at', 'visit2_at', 'visit3_window_start', 'visit3_window_end',
        'visit3_recommended_at', 'visit3_conflict', 'visit3_conflict_reason', 'status',
    ];

    protected function casts(): array
    {
        return [
            'visit1_at' => 'datetime',
            'visit2_at' => 'datetime',
            'visit3_window_start' => 'datetime',
            'visit3_window_end' => 'datetime',
            'visit3_recommended_at' => 'datetime',
            'visit3_conflict' => 'boolean',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function cattle(): BelongsToMany
    {
        return $this->belongsToMany(Cattle::class, 'protocol_cattle');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }
}
