<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RateConfig — editable pricing + fee rules (key/value). No deploy to change a
 * price (§2, §7).
 */
class RateConfig extends Model
{
    protected $table = 'rate_config';

    protected $fillable = [
        'key', 'value', 'label', 'group',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /** Convenience lookup by key. */
    public static function value(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->first()?->value ?? $default;
    }
}
