<?php

namespace App\Models;

use App\Enums\AgeStage;
use App\Enums\AnimalType;
use App\Enums\CattleStatus;
use App\Events\CattleDeactivated;
use Illuminate\Database\Eloquent\Builder;
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
        'animal_type', 'has_calved', 'due_date', 'status', 'a2a2',
        'for_sale', 'for_sale_shared_fields', 'for_sale_listed_by_role', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'animal_type' => AnimalType::class,
            'status' => CattleStatus::class,
            'has_calved' => 'boolean',
            'due_date' => 'date',
            'a2a2' => 'boolean',
            'for_sale' => 'boolean',
            'for_sale_shared_fields' => 'array',
        ];
    }

    /**
     * Computed age-stage label from `dob` (§10b) — never stored. E.g.
     * "heifer calf", "weanling", or the plain animal type once mature.
     */
    public function ageStage(): AgeStage
    {
        return AgeStage::fromDob($this->dob);
    }

    /**
     * Display label combining the computed age stage with the animal type,
     * e.g. "heifer calf" for a 4-month-old heifer.
     */
    public function displayLabel(): string
    {
        return $this->ageStage()->label($this->animal_type);
    }

    /**
     * Record a calving event. A heifer becomes a cow on her FIRST calving —
     * by physiology, not age (§10b). Idempotent: a cow stays a cow.
     */
    public function recordCalving(): void
    {
        $this->has_calved = true;

        if ($this->animal_type === AnimalType::Heifer) {
            $this->animal_type = AnimalType::Cow;
        }

        $this->save();
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

    /*
    |--------------------------------------------------------------------------
    | Cattle-for-sale board (§5.9)
    |--------------------------------------------------------------------------
    */

    /**
     * List the animal on the clients-only for-sale board, sharing only the
     * chosen profile fields. `$fields` is filtered to the config shareable
     * catalogue so an unknown/private field can never be exposed. `$role`
     * records whether the client or staff posted the listing (§5.9).
     *
     * @param  list<string>  $fields
     */
    public function listForSale(array $fields, string $role): void
    {
        $allowed = array_keys(config('for_sale.shareable_fields'));

        $this->for_sale = true;
        $this->for_sale_shared_fields = array_values(array_intersect($fields, $allowed));
        $this->for_sale_listed_by_role = $role;
        $this->save();
    }

    /** Remove the animal from the board without touching its profile (§5.9). */
    public function unlistFromSale(): void
    {
        $this->for_sale = false;
        $this->for_sale_shared_fields = null;
        $this->for_sale_listed_by_role = null;
        $this->save();
    }

    /**
     * Animals currently listed on the for-sale board: for sale AND active
     * (a sold/inactive animal drops off the board).
     *
     * @param  Builder<Cattle>  $query
     */
    public function scopeListedForSale($query): void
    {
        $query->where('for_sale', true)->where('status', CattleStatus::Active->value);
    }
}
