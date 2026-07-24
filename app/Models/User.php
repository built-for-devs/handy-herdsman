<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'current_team_id',
        'notification_opt_ins',
        'is_staff',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_opt_ins' => 'array',
            'is_staff' => 'boolean',
        ];
    }

    /** The team currently in context (Spatie teams-mode tenant, §4). */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /** Teams this user owns. */
    public function ownedTeams()
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    /**
     * Staff (Jeff / Tessa) are global — they see everything and bypass tenant
     * scoping (spec §4). Spatie teams-mode roles are per-team, so global staff
     * is a user flag rather than a null-team role (Postgres cannot store one).
     */
    public function isStaff(): bool
    {
        return (bool) $this->is_staff;
    }

    /**
     * Check a Spatie role within a specific team context (or the global/null
     * context). Restores the previous team context afterward so this is safe
     * to call mid-request.
     */
    public function hasRoleInTeam(?int $teamId, string $role): bool
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($teamId);
        $this->unsetRelation('roles');

        try {
            return $this->hasRole($role);
        } finally {
            $registrar->setPermissionsTeamId($previous);
            $this->unsetRelation('roles');
        }
    }

    /** Whether this user opted into automated notifications for a category. */
    public function optedIntoCategory(int $category): bool
    {
        return (bool) data_get($this->notification_opt_ins, (string) $category, false);
    }

    /** Route SMS (sent.dm) notifications to the user's phone number (§5.5). */
    public function routeNotificationForSentdm(): ?string
    {
        return $this->phone;
    }

    /**
     * Global staff (Jeff / Tessa) — the recipients of the on-call pager (§6.5).
     *
     * @param  Builder<User>  $query
     */
    public function scopeStaff($query)
    {
        return $query->where('is_staff', true);
    }
}
