<?php

namespace App\Models;

use App\Enums\ClientStatus;
use App\Enums\MessageChannel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Client — the client record for a team. `status` (new|active|inactive) gates
 * self-booking (§10b — Client status). Contact + consent model per §5.7:
 * email (identity) and phone (operational) are required; SMS consent is a
 * SEPARATE, timestamped, per-category opt-in — a phone number alone is NOT
 * permission to auto-text.
 */
class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id', 'contact_name', 'email', 'phone',
        'address_line1', 'address_line2', 'city', 'state', 'postal_code',
        'lat', 'lng', 'cached_distance_miles', 'in_range', 'status',
        'consent_sms', 'channel_prefs', 'staff_channel_override', 'staff_channel_override_note',
        'agreement_signed_at', 'waiver_signed_at', 'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'cached_distance_miles' => 'decimal:2',
            'in_range' => 'boolean',
            'status' => ClientStatus::class,
            'consent_sms' => 'array',
            'channel_prefs' => 'array',
            'staff_channel_override' => 'array',
            'agreement_signed_at' => 'datetime',
            'waiver_signed_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Onboarding — agreement & waiver (§4, §5.6)
    |--------------------------------------------------------------------------
    */

    /** Record acceptance of the service agreement + liability waiver, timestamped. */
    public function acceptAgreementAndWaiver(?CarbonInterface $at = null): void
    {
        $at ??= now();

        $this->agreement_signed_at = $at;
        $this->waiver_signed_at = $at;
    }

    public function hasAcceptedAgreement(): bool
    {
        return $this->agreement_signed_at !== null && $this->waiver_signed_at !== null;
    }

    /** The one-line address used for geocoding, or null when none is stored. */
    public function fullAddress(): ?string
    {
        $parts = array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->postal_code,
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /*
    |--------------------------------------------------------------------------
    | Status lifecycle (§10b — Client status)
    |--------------------------------------------------------------------------
    */

    /** A booking made now must be held for staff review unless the client is active. */
    public function requiresBookingReview(): bool
    {
        return $this->status->requiresBookingReview();
    }

    /**
     * Record a completed visit. Promotes `new` (first-timer) or a reactivated
     * `inactive` client to `active` — active clients then book freely (§10b).
     */
    public function recordCompletedVisit(?CarbonInterface $at = null): void
    {
        $this->last_activity_at = $at ?? now();

        if ($this->status !== ClientStatus::Active) {
            $this->status = ClientStatus::Active;
        }

        $this->save();
    }

    /** Bump the idle clock without changing status (e.g. any client activity). */
    public function touchActivity(?CarbonInterface $at = null): void
    {
        $this->forceFill(['last_activity_at' => $at ?? now()])->save();
    }

    /**
     * Revert an active client to `inactive` once idle past the config threshold
     * (default 1 year). Inactive clients require staff review on their next
     * booking. Returns whether a transition occurred (§10b).
     */
    public function markInactiveIfIdle(?CarbonInterface $now = null): bool
    {
        if ($this->status !== ClientStatus::Active || $this->last_activity_at === null) {
            return false;
        }

        $now ??= now();
        $threshold = (int) config('clients.idle_threshold_days');

        if ($this->last_activity_at->diffInDays($now, absolute: false) < $threshold) {
            return false;
        }

        $this->status = ClientStatus::Inactive;
        $this->save();

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | SMS consent — separate, timestamped, per category (§5.7)
    |--------------------------------------------------------------------------
    */

    public function hasSmsConsent(int $category): bool
    {
        return $this->smsConsentAt($category) !== null;
    }

    public function smsConsentAt(int $category): ?Carbon
    {
        $value = data_get($this->consent_sms, (string) $category);

        return $value ? Carbon::parse($value) : null;
    }

    public function grantSmsConsent(int $category, ?CarbonInterface $at = null): void
    {
        $consent = $this->consent_sms ?? [];
        $consent[(string) $category] = ($at ?? now())->toIso8601String();
        $this->consent_sms = $consent;
    }

    public function revokeSmsConsent(int $category): void
    {
        $consent = $this->consent_sms ?? [];
        unset($consent[(string) $category]);
        $this->consent_sms = $consent;
    }

    /*
    |--------------------------------------------------------------------------
    | Channel preferences (§5.7) — 4 categories, each email/text/both
    |--------------------------------------------------------------------------
    */

    /** Config-driven per-category defaults (cat.1 both, cat.4 email-only). */
    public static function defaultChannelPrefs(): array
    {
        $prefs = [];

        foreach (config('reminders.categories') as $number => $category) {
            $prefs[(string) $number] = $category['default_channel'];
        }

        return $prefs;
    }

    /** The client's stored preference for a category, falling back to defaults. */
    public function preferredChannel(int $category): MessageChannel
    {
        $stored = data_get($this->channel_prefs, (string) $category)
            ?? data_get(self::defaultChannelPrefs(), (string) $category, MessageChannel::Email->value);

        return MessageChannel::from($stored);
    }

    /** Staff override wins over the client's own preference when present (§5.7). */
    public function channelForCategory(int $category): MessageChannel
    {
        $override = data_get($this->staff_channel_override, (string) $category);

        if ($override !== null) {
            return MessageChannel::from($override);
        }

        return $this->preferredChannel($category);
    }

    /**
     * The channel actually usable for a category once SMS consent is applied.
     * A text/both preference degrades to email-only when the client has not
     * granted SMS consent for that category — a phone number is not consent.
     */
    public function resolvedChannel(int $category): MessageChannel
    {
        $channel = $this->channelForCategory($category);

        if ($channel->includesText() && ! $this->hasSmsConsent($category)) {
            return $channel->withoutText();
        }

        return $channel;
    }

    public function setStaffChannelOverride(?array $override, ?string $note): void
    {
        $this->staff_channel_override = $override;
        $this->staff_channel_override_note = $note;
    }
}
