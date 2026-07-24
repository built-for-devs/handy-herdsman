<?php

namespace Tests\Unit;

use App\Support\PriceRuleFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PriceRuleFormatterTest extends TestCase
{
    /**
     * @param  array<string, mixed>|null  $rule
     */
    #[DataProvider('ruleProvider')]
    public function test_it_formats_each_price_rule_model(?array $rule, string $expected): void
    {
        $this->assertSame($expected, PriceRuleFormatter::display($rule));
    }

    /** @return array<string, array{0: array<string, mixed>|null, 1: string}> */
    public static function ruleProvider(): array
    {
        return [
            'flat' => [['model' => 'flat', 'price' => 300], '$300'],
            'flat free-with-ai' => [['model' => 'flat', 'price' => 50, 'free_with_ai_booking' => true], '$50 (free with any AI booking)'],
            'free' => [['model' => 'free', 'price' => 0], 'Free'],
            'per_head with minimum' => [['model' => 'per_head', 'per_head' => 25, 'visit_minimum' => 50], '$25/head ($50 visit minimum)'],
            'per_head with lab addon' => [['model' => 'per_head', 'per_head' => 25, 'visit_minimum' => 50, 'lab_confirmation_addon' => 15], '$25/head ($50 visit minimum), +$15 optional lab confirmation'],
            'per_shipment' => [['model' => 'per_shipment', 'price' => 15], '$15 per shipment'],
            'annual free year one' => [['model' => 'annual', 'price' => 50, 'free_year_one' => true, 'max_straws' => 10], '$50/yr (free year 1 with AI, up to 10 straws)'],
            'handling_fee' => [['model' => 'handling_fee', 'price' => 25], '$25 handling fee (plus the cost you pay your source)'],
            'flat_plus_hourly' => [['model' => 'flat_plus_hourly', 'price' => 150, 'hourly' => 50, 'free_hours' => 2], '$150, then $50/hr after 2 hr'],
            'callout_plus_hourly' => [['model' => 'callout_plus_hourly', 'callout' => 200, 'hourly' => 75], '$200 call-out, then $75/hr'],
            'day_rate' => [['model' => 'day_rate', 'day' => 250, 'half_day' => 150], '$250/day or $150/half-day'],
            'hourly with minimum' => [['model' => 'hourly', 'hourly' => 60, 'minimum_hours' => 1], '$60/hr (1 hr minimum)'],
            'per_visit_or_day' => [['model' => 'per_visit_or_day', 'per_visit' => 60, 'per_day' => 100], '$60/visit or $100/day'],
            'per_session_or_day' => [['model' => 'per_session_or_day', 'per_session' => 40, 'per_day' => 70], '$40/session or $70/day'],
            'null rule' => [null, 'Contact for pricing'],
            'unknown model' => [['model' => 'mystery'], 'Contact for pricing'],
        ];
    }

    public function test_money_formats_whole_and_fractional_amounts(): void
    {
        $this->assertSame('$1,000', PriceRuleFormatter::money(1000));
        $this->assertSame('$12.50', PriceRuleFormatter::money(12.5));
    }
}
