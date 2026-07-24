<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Reminder — polymorphic (protocol/visit/cattle/client). category 1-4 sets
 * channel + quiet-hours behaviour; emergencies bypass (§5.7, §10b).
 *
 * Lifecycle status (string): `pending` → `queued` (claimed by the dispatcher)
 * → `sent`, or `cancelled` (e.g. the animal was set inactive). `dedupe_key`
 * makes scheduling idempotent so re-fired triggers never double-schedule.
 */
class Reminder extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'team_id', 'remindable_type', 'remindable_id', 'fire_at', 'category',
        'channel', 'template', 'recipient_role', 'payload', 'dedupe_key',
        'quiet_hours_deferred_to', 'status', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'fire_at' => 'datetime',
            'quiet_hours_deferred_to' => 'datetime',
            'sent_at' => 'datetime',
            'payload' => 'array',
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

    /** The moment this reminder is actually due to send (deferral wins). */
    public function effectiveSendAt(): Carbon
    {
        return $this->quiet_hours_deferred_to ?? $this->fire_at;
    }

    /**
     * Reminders due to send at or before the given moment: still pending and
     * whose effective send time (deferral, else fire_at) has arrived.
     *
     * @param  Builder<Reminder>  $query
     */
    public function scopeDue(Builder $query, \DateTimeInterface $now): Builder
    {
        return $query
            ->where('status', self::STATUS_PENDING)
            ->where(function (Builder $q) use ($now): void {
                $q->whereNotNull('quiet_hours_deferred_to')
                    ->where('quiet_hours_deferred_to', '<=', $now)
                    ->orWhere(function (Builder $inner) use ($now): void {
                        $inner->whereNull('quiet_hours_deferred_to')
                            ->where('fire_at', '<=', $now);
                    });
            });
    }
}
