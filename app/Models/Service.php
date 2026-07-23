<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Service — source of truth for the services page, pricing, and booking flow.
 * `type` (protocol|oncall|standard) routes the flow (§5.2, §6, §7).
 */
class Service extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'category', 'type', 'price_rule',
        'requires_first_time_review', 'requires_containment', 'active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_rule' => 'array',
            'requires_first_time_review' => 'boolean',
            'requires_containment' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /**
     * Whether this service physically breeds/AIs the animal, and therefore
     * requires a breeding-eligible animal (heifer/cow, never bull/steer —
     * §10b). Every `protocol`-type service breeds; a config slug list covers
     * breeding services of other types (e.g. on-call heat breeding).
     */
    public function breedsAnimal(): bool
    {
        if ($this->type === 'protocol') {
            return true;
        }

        return in_array($this->slug, (array) config('protocol.breeding_service_slugs'), true);
    }

    public function usageProfile(): HasOne
    {
        return $this->hasOne(SupplyUsageProfile::class);
    }
}
