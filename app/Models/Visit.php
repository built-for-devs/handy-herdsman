<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Visit — a single scheduled farm call (V1/V2/V3 within a protocol, or
 * standalone). Mileage + fee tracked per visit for COGS (§5.5, §5.6c).
 */
class Visit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id', 'cattle_id', 'protocol_id', 'booking_id', 'type', 'status',
        'scheduled_at', 'completed_at', 'staff_notes', 'mileage', 'fee_applied', 'billed_amount',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'mileage' => 'decimal:2',
            'fee_applied' => 'boolean',
            'billed_amount' => 'decimal:2',
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

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function cattle(): BelongsTo
    {
        return $this->belongsTo(Cattle::class);
    }

    public function completion(): HasOne
    {
        return $this->hasOne(VisitCompletion::class);
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
