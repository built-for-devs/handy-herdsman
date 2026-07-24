<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Booking — book immediately; first-timers provisional pending staff review;
 * established clients self-confirm. Distance fee is per booking (§5.5, §10b).
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id', 'service_id', 'created_by', 'proposed_start', 'computed_windows',
        'status', 'requires_review', 'review_reason', 'is_oncall', 'distance_fee_flag',
        'is_cash', 'reviewed_at', 'reviewed_by', 'decline_reason',
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

    public function protocol(): HasOne
    {
        return $this->hasOne(Protocol::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function bookingStatus(): BookingStatus
    {
        return BookingStatus::from($this->status);
    }

    public function isProvisional(): bool
    {
        return $this->status === BookingStatus::Provisional->value;
    }
}
