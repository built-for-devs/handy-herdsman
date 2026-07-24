<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Visit — a single scheduled farm call (V1/V2/V3 within a protocol, or
 * standalone). Mileage + fee tracked per visit for COGS (§5.5, §5.6c).
 */
class Visit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id', 'cattle_id', 'protocol_id', 'booking_id', 'type', 'status',
        'scheduled_at', 'completed_at', 'staff_notes', 'mileage', 'fee_applied', 'billed_amount',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'mileage' => 'decimal:2',
            'fee_applied' => 'boolean',
            'billed_amount' => 'decimal:2',
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

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function cattle(): BelongsTo
    {
        return $this->belongsTo(Cattle::class);
    }

    public function completion(): HasOne
    {
        return $this->hasOne(VisitCompletion::class);
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /** The service this visit performs, resolved via its booking (§5.5). */
    public function service(): ?Service
    {
        return $this->booking?->service;
    }

    /**
     * Whether this is a breeding-related visit — BCS is REQUIRED per animal on
     * these (§5.5). Any protocol visit (V1/V2/V3) breeds; other services breed
     * when their service is a breeding service (config-driven `breedsAnimal()`).
     */
    public function isBreedingRelated(): bool
    {
        if ($this->protocol_id !== null) {
            return true;
        }

        return (bool) $this->service()?->breedsAnimal();
    }

    /**
     * The animals covered by this visit for the completion form. A visit may
     * pin a single animal (`cattle_id`); otherwise it covers every animal on
     * the booking (multi-head visits, §10b).
     *
     * @return Collection<int, Cattle>
     */
    public function animals(): Collection
    {
        if ($this->cattle_id !== null && $this->cattle !== null) {
            return new Collection([$this->cattle]);
        }

        return $this->booking?->cattle()->get() ?? new Collection;
    }
}
