<?php

namespace App\Models;

use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * TeamInvitation — owner invites a member (spouse, full access) or vet
 * (read-only). Vet read scope is client-controlled per invitation (§4, §10b).
 */
class TeamInvitation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id', 'invited_by', 'email', 'role', 'token', 'status',
        'vet_scope', 'accepted_by', 'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => TeamRole::class,
            'vet_scope' => 'array',
            'accepted_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    /** Generate a cryptographically-random, unguessable invitation token. */
    public static function generateToken(): string
    {
        return Str::random(64);
    }
}
