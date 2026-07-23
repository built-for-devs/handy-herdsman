<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AvailabilityRule — per-weekday working hours the scheduler reads. Sundays off
 * by default; no Sunday mornings; emergencies bypass (§7, §10b).
 */
class AvailabilityRule extends Model
{
    protected $fillable = [
        'day_of_week', 'is_working_day', 'start_time', 'end_time',
        'max_visits_per_day', 'buffer_minutes',
    ];

    protected function casts(): array
    {
        return [
            'is_working_day' => 'boolean',
        ];
    }
}
