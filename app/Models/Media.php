<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Media — a photo/attachment. It always anchors to the animal (the `mediable`
 * morph) and OPTIONALLY references the visit it was taken at (§228, §10b).
 * Clients upload to profiles (for-sale); Jeff uploads at appointments (healing
 * progress, condition comparison). Ordered chronologically by taken_at.
 */
class Media extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'media';

    protected $fillable = [
        'mediable_type', 'mediable_id', 'visit_id', 'uploaded_by', 'uploaded_role',
        'path', 'caption', 'taken_at',
    ];

    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
        ];
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Staff-uploaded media is Jeff's professional record — clients may not delete it (§10b). */
    public function isStaffUploaded(): bool
    {
        return $this->uploaded_role === 'staff';
    }

    /** The owning tenant, resolved from whatever this media is attached to (§4). */
    public function resolveTeam(): ?Team
    {
        $mediable = $this->mediable;

        if ($mediable instanceof Cattle || $mediable instanceof Visit) {
            return $mediable->team;
        }

        return $this->visit?->team;
    }
}
