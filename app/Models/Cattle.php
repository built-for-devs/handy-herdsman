<?php

namespace App\Models;

use App\Enums\CattleStatus;
use App\Events\CattleDeactivated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
            'status' => CattleStatus::class,
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

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /** Reminders tied directly to this animal (polymorphic remindable, §5.7). */
    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    public function isActive(): bool
    {
        return $this->status === CattleStatus::Active;
    }

    /**
     * Mark the animal inactive (sold/deceased/culled/out of program). The
     * status change fires {@see CattleDeactivated} via the observer,
     * which cancels all pending reminders immediately (§10b). Nothing is
     * hard-deleted — the animal stays in records and history.
     */
    public function deactivate(): void
    {
        if ($this->status !== CattleStatus::Inactive) {
            $this->status = CattleStatus::Inactive;
            $this->save();
        }
    }

    public function activate(): void
    {
        if ($this->status !== CattleStatus::Active) {
            $this->status = CattleStatus::Active;
            $this->save();
        }
    }
}
