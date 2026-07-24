<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cattle — one animal. animal_type (heifer|cow|bull|steer) drives protocol
 * timing + breeding eligibility. "calf"/"weanling" are computed from dob, never
 * stored (§10b — Animal type). A heifer auto-promotes to cow on first calving.
 */
class Cattle extends Model
{
    use HasFactory, SoftDeletes;

    /** Non-standard plural — the table is `cattle`. */
    protected $table = 'cattle';

    protected $fillable = [
        'team_id', 'reg_name', 'herd_number', 'dob', 'breed',
        'animal_type', 'has_calved', 'status', 'a2a2',
        'for_sale', 'for_sale_shared_fields', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'has_calved' => 'boolean',
            'a2a2' => 'boolean',
            'for_sale' => 'boolean',
            'for_sale_shared_fields' => 'array',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function healthRecords(): HasMany
    {
        return $this->hasMany(HealthRecord::class);
    }

    public function pregChecks(): HasMany
    {
        return $this->hasMany(PregCheck::class);
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
