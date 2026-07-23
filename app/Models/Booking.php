<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Booking — book immediately; first-timers provisional pending staff review;
 * established clients self-confirm. Distance fee is per booking (§5.5, §10b).
 */
class Booking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'team_id', 'service_id', 'created_by', 'proposed_start', 'computed_windows',
        'status', 'requires_review', 'is_oncall', 'distance_fee_flag', 'is_cash', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'proposed_start' => 'datetime',
            'computed_windows' => 'array',
            'requires_review' => 'boolean',
            'is_oncall' => 'boolean',
            'distance_fee_flag' => 'boolean',
            'is_cash' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function cattle(): BelongsToMany
    {
        return $this->belongsToMany(Cattle::class, 'booking_cattle');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
