<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\RateConfig;
use App\Models\Service;
use App\Support\PriceRuleFormatter;
use Inertia\Inertia;
use Inertia\Response;

class PricingController extends Controller
{
    /**
     * Public pricing page — pulled from the services table + rate_config so it
     * is never stale (§5.2, §5.2b). NO hardcoded prices.
     */
    public function __invoke(): Response
    {
        $services = Service::where('active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Service $s) => [
                'name' => $s->name,
                'category' => $s->category,
                'price' => PriceRuleFormatter::display($s->price_rule),
            ])
            ->values();

        // Headline fees/rules straight from rate_config (editable, no deploy).
        $fees = RateConfig::whereIn('group', ['fees', 'semen'])
            ->get()
            ->map(fn (RateConfig $r) => [
                'key' => $r->key,
                'label' => $r->label,
                'display' => $this->displayRate($r),
            ])
            ->values();

        return Inertia::render('marketing/Pricing', [
            'services' => $services,
            'fees' => $fees,
        ]);
    }

    private function displayRate(RateConfig $rate): string
    {
        $value = $rate->value;

        if (! empty($value['free_year_one'])) {
            return 'Free year 1 with AI, then '.PriceRuleFormatter::money($value['price'] ?? 0).'/yr';
        }

        $out = PriceRuleFormatter::money($value['price'] ?? 0);

        if (isset($value['threshold_miles'])) {
            $out .= ' (over '.$value['threshold_miles'].' mi, per '.($value['per'] ?? 'booking').')';
        } elseif (isset($value['per'])) {
            $out .= ' per '.$value['per'];
        }

        return $out;
    }
}
