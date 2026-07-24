<?php

namespace App\Support;

/**
 * Turns a Service `price_rule` array (the editable pricing data, §5.2b, §7)
 * into human-readable copy for the public services + pricing pages.
 *
 * Pure and deterministic: NO prices are hardcoded here — every number comes
 * from the passed-in rule, which originates from the services table / rate
 * config. Adding a service or changing a price is data entry, not a deploy.
 */
class PriceRuleFormatter
{
    /**
     * @param  array<string, mixed>|null  $rule
     */
    public static function display(?array $rule): string
    {
        if (! $rule || ! isset($rule['model'])) {
            return 'Contact for pricing';
        }

        return match ($rule['model']) {
            'free' => 'Free',
            'flat' => self::money($rule['price'] ?? 0).self::freeWithAi($rule),
            'per_head' => self::perHead($rule),
            'handling_fee' => self::money($rule['price'] ?? 0).' handling fee (plus the cost you pay your source)',
            'per_shipment' => self::money($rule['price'] ?? 0).' per shipment',
            'annual' => self::annual($rule),
            'flat_plus_hourly' => self::money($rule['price'] ?? 0)
                .', then '.self::money($rule['hourly'] ?? 0).'/hr after '.($rule['free_hours'] ?? 0).' hr',
            'callout_plus_hourly' => self::money($rule['callout'] ?? 0)
                .' call-out, then '.self::money($rule['hourly'] ?? 0).'/hr',
            'day_rate' => self::money($rule['day'] ?? 0).'/day or '.self::money($rule['half_day'] ?? 0).'/half-day',
            'hourly' => self::money($rule['hourly'] ?? 0).'/hr'
                .(isset($rule['minimum_hours']) ? ' ('.$rule['minimum_hours'].' hr minimum)' : ''),
            'per_visit_or_day' => self::money($rule['per_visit'] ?? 0).'/visit or '.self::money($rule['per_day'] ?? 0).'/day',
            'per_session_or_day' => self::money($rule['per_session'] ?? 0).'/session or '.self::money($rule['per_day'] ?? 0).'/day',
            default => 'Contact for pricing',
        };
    }

    /** @param array<string, mixed> $rule */
    private static function perHead(array $rule): string
    {
        $out = self::money($rule['per_head'] ?? 0).'/head';

        if (isset($rule['visit_minimum'])) {
            $out .= ' ('.self::money($rule['visit_minimum']).' visit minimum)';
        }

        if (isset($rule['lab_confirmation_addon'])) {
            $out .= ', +'.self::money($rule['lab_confirmation_addon']).' optional lab confirmation';
        }

        return $out;
    }

    /** @param array<string, mixed> $rule */
    private static function annual(array $rule): string
    {
        $out = self::money($rule['price'] ?? 0).'/yr';

        if (! empty($rule['free_year_one'])) {
            $out .= ' (free year 1 with AI';
            $out .= isset($rule['max_straws']) ? ', up to '.$rule['max_straws'].' straws)' : ')';
        }

        return $out;
    }

    /** @param array<string, mixed> $rule */
    private static function freeWithAi(array $rule): string
    {
        return ! empty($rule['free_with_ai_booking']) ? ' (free with any AI booking)' : '';
    }

    public static function money(int|float $amount): string
    {
        if ($amount == (int) $amount) {
            return '$'.number_format((int) $amount);
        }

        return '$'.number_format($amount, 2);
    }
}
