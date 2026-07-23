<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Client — the client record for a team. status (new|active|inactive) gates
 * self-booking (§10b — Client status).
 */
class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'team_id', 'contact_name', 'email', 'phone',
        'address_line1', 'address_line2', 'city', 'state', 'postal_code',
        'lat', 'lng', 'cached_distance_miles', 'in_range', 'status',
        'consent_sms', 'channel_prefs', 'staff_channel_override', 'staff_channel_override_note',
        'agreement_signed_at', 'waiver_signed_at', 'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'cached_distance_miles' => 'decimal:2',
            'in_range' => 'boolean',
            'consent_sms' => 'array',
            'channel_prefs' => 'array',
            'staff_channel_override' => 'array',
            'agreement_signed_at' => 'datetime',
            'waiver_signed_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
