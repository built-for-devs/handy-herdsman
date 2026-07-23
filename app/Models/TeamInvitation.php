<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TeamInvitation — owner invites a member (spouse) or vet. Vet read scope is
 * client-controlled per invitation (§4, §10b).
 */
class TeamInvitation extends Model
{
    protected $fillable = [
        'team_id', 'email', 'role', 'token', 'status', 'vet_scope', 'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'vet_scope' => 'array',
            'accepted_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
