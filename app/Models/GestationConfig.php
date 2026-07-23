<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * GestationConfig — breed -> average gestation days (default 283). The heifer
 * offset is stored in the special row `__heifer_offset__` (§5.4b, §7).
 */
class GestationConfig extends Model
{
    protected $table = 'gestation_config';

    protected $fillable = [
        'breed', 'gestation_days',
    ];

    public const DEFAULT_KEY = '__default__';
    public const HEIFER_OFFSET_KEY = '__heifer_offset__';
}
