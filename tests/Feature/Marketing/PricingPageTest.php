<?php

namespace Tests\Feature\Marketing;

use App\Models\RateConfig;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PricingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_reflects_service_and_rate_config_data(): void
    {
        Service::create([
            'name' => 'Pricing Probe Service',
            'slug' => 'pricing-probe-service',
            'category' => 'breeding',
            'type' => 'protocol',
            'price_rule' => ['model' => 'flat', 'price' => 999],
            'active' => true,
            'sort_order' => 1,
        ]);

        RateConfig::create([
            'key' => 'distance_fee',
            'value' => ['price' => 30, 'threshold_miles' => 15, 'per' => 'booking'],
            'label' => 'Distance fee',
            'group' => 'fees',
        ]);

        $this->get('/pricing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('marketing/Pricing')
                ->where('services.0.name', 'Pricing Probe Service')
                ->where('services.0.price', '$999')
                ->where('fees.0.label', 'Distance fee')
                ->where('fees.0.display', '$30 (over 15 mi, per booking)')
            );
    }

    public function test_storage_free_year_rule_is_rendered_from_config(): void
    {
        RateConfig::create([
            'key' => 'semen_storage_annual',
            'value' => ['price' => 50, 'free_year_one' => true, 'max_straws' => 10],
            'label' => 'Semen storage',
            'group' => 'semen',
        ]);

        $this->get('/pricing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('fees.0.display', 'Free year 1 with AI, then $50/yr')
            );
    }
}
