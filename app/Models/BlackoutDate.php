<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * BlackoutDate — days/ranges Jeff is unavailable. Scheduler must not book into
 * them; emergencies bypass (§10b).
 */
class BlackoutDate extends Model
{
    protected $fillable = [
        'start_date', 'end_date', 'reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
