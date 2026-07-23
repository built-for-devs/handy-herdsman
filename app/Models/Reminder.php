<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Reminder — polymorphic (protocol/visit/cattle/client). category 1-4 sets
 * channel + quiet-hours behaviour; emergencies bypass (§5.7, §10b).
 */
class Reminder extends Model
{
    protected $fillable = [
        'team_id', 'remindable_type', 'remindable_id', 'fire_at', 'category',
        'channel', 'template', 'recipient_role', 'quiet_hours_deferred_to', 'status', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'fire_at' => 'datetime',
            'quiet_hours_deferred_to' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
