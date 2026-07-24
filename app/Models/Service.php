<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Service — source of truth for the services page, pricing, and booking flow.
 * `type` (protocol|oncall|standard) routes the flow (§5.2, §6, §7).
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, SoftDeletes;

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

    public function usageProfile(): HasOne
    {
        return $this->hasOne(SupplyUsageProfile::class);
    }
}
