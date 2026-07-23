<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Payment — Cashier-managed. Charged when the booking reaches `confirmed`;
 * cash option skips the charge and marks owed. No tax (§5.6, §10b — Money).
 */
class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'team_id', 'booking_id', 'line_items', 'total', 'method',
        'stripe_payment_method_id', 'charged_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'line_items' => 'array',
            'total' => 'decimal:2',
            'charged_at' => 'datetime',
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
}
