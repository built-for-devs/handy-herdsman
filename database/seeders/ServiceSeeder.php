<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The services catalog (spec §5.2, §5.2b). Every service is a row with a `type`
 * that routes its booking flow (protocol | oncall | standard) and an editable
 * `price_rule`. Adding a service later is data entry, not a deploy (§7).
 *
 * Prices anchor to the known numbers; the rest are placeholders to adjust.
 */
class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $sort = 0;

        foreach ($this->services() as $s) {
            $sort++;
            Service::updateOrCreate(
                ['slug' => Str::slug($s['name'])],
                [
                    'name' => $s['name'],
                    'description' => $s['description'] ?? null,
                    'category' => $s['category'],
                    'type' => $s['type'],
                    'price_rule' => $s['price_rule'],
                    'requires_first_time_review' => $s['review'] ?? true,
                    'requires_containment' => $s['containment'] ?? true,
                    'active' => true,
                    'sort_order' => $sort,
                ]
            );
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function services(): array
    {
        return [
            // ---- Breeding & repro ----
            ['name' => 'AI — Natural Plan', 'category' => 'breeding', 'type' => 'protocol',
                'price_rule' => ['model' => 'flat', 'price' => 100, 'farm_calls' => 1],
                'description' => 'Client recognizes heat; single timed farm call, 1 cow.'],
            ['name' => 'AI — Basic/Sync Plan', 'category' => 'breeding', 'type' => 'protocol',
                'price_rule' => ['model' => 'flat', 'price' => 300, 'farm_calls' => 3, 'included_cows' => 2, 'per_additional_cow' => 100],
                'description' => 'CIDR 10-day sync, 3 farm calls, up to 2 cows.'],
            ['name' => 'On-call heat breeding', 'category' => 'breeding', 'type' => 'oncall',
                'price_rule' => ['model' => 'flat', 'price' => 100],
                'description' => 'Text when in standing heat; expedited timed farm call.'],
            ['name' => 'Heat detection / standing-heat training', 'category' => 'breeding', 'type' => 'standard',
                'price_rule' => ['model' => 'flat', 'price' => 75], 'containment' => false],
            ['name' => 'Estrus sync planning (small herd)', 'category' => 'breeding', 'type' => 'standard',
                'price_rule' => ['model' => 'flat', 'price' => 100], 'containment' => false],
            ['name' => 'Genetics / breeding-program consult', 'category' => 'breeding', 'type' => 'standard',
                'price_rule' => ['model' => 'flat', 'price' => 100], 'containment' => false],
            ['name' => 'Bull recommendation', 'category' => 'breeding', 'type' => 'standard',
                'price_rule' => ['model' => 'flat', 'price' => 50, 'free_with_ai_booking' => true], 'containment' => false],
            ['name' => 'Semen sourcing', 'category' => 'breeding', 'type' => 'standard',
                'price_rule' => ['model' => 'handling_fee', 'price' => 25, 'note' => 'on top of straw cost paid to source'], 'containment' => false],
            ['name' => 'Semen intake / receipt', 'category' => 'breeding', 'type' => 'standard',
                'price_rule' => ['model' => 'per_shipment', 'price' => 15], 'containment' => false],
            ['name' => 'Semen storage', 'category' => 'breeding', 'type' => 'standard',
                'price_rule' => ['model' => 'annual', 'price' => 50, 'free_year_one' => true, 'max_straws' => 10], 'containment' => false],
            ['name' => 'Pregnancy check — blood', 'category' => 'breeding', 'type' => 'standard',
                'price_rule' => ['model' => 'per_head', 'per_head' => 25, 'visit_minimum' => 50, 'lab_confirmation_addon' => 15]],
            ['name' => 'Pregnancy check — palpation', 'category' => 'breeding', 'type' => 'standard',
                'price_rule' => ['model' => 'per_head', 'per_head' => 40, 'visit_minimum' => 50, 'note' => 'after 4 months (~120 days)']],

            // ---- Calving ----
            ['name' => 'Pre-calving prep', 'category' => 'calving', 'type' => 'standard',
                'price_rule' => ['model' => 'flat', 'price' => 75]],
            ['name' => 'Newborn-calf care', 'category' => 'calving', 'type' => 'standard',
                'price_rule' => ['model' => 'flat', 'price' => 75]],
            ['name' => 'Calving support / assist (scheduled)', 'category' => 'calving', 'type' => 'standard',
                'price_rule' => ['model' => 'flat_plus_hourly', 'price' => 150, 'hourly' => 50, 'free_hours' => 2]],
            ['name' => 'On-call calving emergency', 'category' => 'calving', 'type' => 'oncall',
                'price_rule' => ['model' => 'callout_plus_hourly', 'callout' => 200, 'hourly' => 75]],

            // ---- Cattle handling / ranch-hand ----
            ['name' => 'Work-your-cattle-for-a-day', 'category' => 'ranch_hand', 'type' => 'standard',
                'price_rule' => ['model' => 'day_rate', 'day' => 250, 'half_day' => 150]],
            ['name' => 'Chute-side / restraint for vet visit', 'category' => 'ranch_hand', 'type' => 'standard',
                'price_rule' => ['model' => 'hourly', 'hourly' => 60, 'minimum_hours' => 1]],
            ['name' => 'Farm / ranch watch while traveling', 'category' => 'ranch_hand', 'type' => 'standard',
                'price_rule' => ['model' => 'per_visit_or_day', 'per_visit' => 60, 'per_day' => 100], 'containment' => false],
            ['name' => 'Part-time ranch hand', 'category' => 'ranch_hand', 'type' => 'standard',
                'price_rule' => ['model' => 'hourly', 'hourly' => 40], 'containment' => false],

            // ---- Non-vet health tasks ----
            ['name' => 'Vaccinations', 'category' => 'health', 'type' => 'standard',
                'price_rule' => ['model' => 'per_head', 'per_head' => 10, 'visit_minimum' => 50, 'note' => 'plus vaccine cost']],
            ['name' => 'Deworming / parasite management', 'category' => 'health', 'type' => 'standard',
                'price_rule' => ['model' => 'per_head', 'per_head' => 8, 'visit_minimum' => 50, 'note' => 'plus product']],
            ['name' => 'Dehorning / disbudding', 'category' => 'health', 'type' => 'standard',
                'price_rule' => ['model' => 'per_head', 'per_head' => 35, 'visit_minimum' => 50]],
            ['name' => 'Ear tagging / ID setup', 'category' => 'health', 'type' => 'standard',
                'price_rule' => ['model' => 'per_head', 'per_head' => 8, 'visit_minimum' => 50]],
            ['name' => 'Body condition + nutrition assessment', 'category' => 'health', 'type' => 'standard',
                'price_rule' => ['model' => 'flat', 'price' => 75]],
            ['name' => 'Mineral / feed program setup', 'category' => 'health', 'type' => 'standard',
                'price_rule' => ['model' => 'flat', 'price' => 75], 'containment' => false],
            ['name' => 'Weighing', 'category' => 'health', 'type' => 'standard',
                'price_rule' => ['model' => 'per_head', 'per_head' => 10, 'visit_minimum' => 50]],
            ['name' => 'Lameness / hoof assessment', 'category' => 'health', 'type' => 'standard',
                'price_rule' => ['model' => 'flat', 'price' => 60, 'note' => 'assessment only — trimming not offered']],

            // ---- Consult / onboarding ----
            ['name' => 'New-owner onboarding', 'category' => 'consult', 'type' => 'standard',
                'price_rule' => ['model' => 'flat', 'price' => 100], 'containment' => false],
            ['name' => 'Herd-records setup', 'category' => 'consult', 'type' => 'standard',
                'price_rule' => ['model' => 'free', 'price' => 0, 'note' => 'the data hook'], 'containment' => false],

            // ---- Milking ----
            ['name' => 'Milking / relief milking', 'category' => 'milking', 'type' => 'standard',
                'price_rule' => ['model' => 'per_session_or_day', 'per_session' => 40, 'per_day' => 70], 'containment' => false],
        ];
    }
}
