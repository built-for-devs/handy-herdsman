<?php

namespace Database\Seeders;

use App\Models\RateConfig;
use Illuminate\Database\Seeder;

/**
 * Editable pricing + fee rules (spec §2, §5.2b, §7). Known values are locked;
 * everything else is a placeholder Tessa/Jeff can edit without a deploy.
 */
class RateConfigSeeder extends Seeder
{
    public function run(): void
    {
        $rates = [
            // key, value, label, group
            ['ai_natural_plan', ['price' => 100], 'AI — Natural Plan (1 farm call)', 'breeding'],
            ['ai_sync_plan', ['price' => 300, 'included_cows' => 2], 'AI — Basic/Sync Plan (3 calls, ≤2 cows)', 'breeding'],
            ['additional_cow', ['price' => 100], 'Additional cow on a plan', 'breeding'],
            ['semen_receipt_fee', ['price' => 15], 'Semen receipt (per shipment)', 'semen'],
            ['semen_storage_annual', ['price' => 50, 'free_year_one' => true, 'max_straws' => 10], 'Semen storage (free yr 1 w/ AI, then $50/yr)', 'semen'],
            ['distance_fee', ['price' => 30, 'threshold_miles' => 15, 'per' => 'booking'], 'Distance fee (>15mi, per booking/protocol)', 'fees'],
            // Optional lab confirmation on a blood preg check — charged ONLY if
            // the client opts in; the base price is the draw itself (§10b).
            ['preg_check_lab_confirmation', ['price' => 15], 'Preg check — optional lab confirmation (per head)', 'breeding'],
            ['visit_minimum', ['price' => 50, 'per' => 'visit'], 'Per-visit minimum', 'fees'],
            // Normal farm-call rate a failed/aborted visit is still billed at
            // (§10b — Rescheduling, failed visits). No automated trip-fee logic.
            ['standard_farm_call', ['price' => 100], 'Standard farm call (also the failed-visit rate)', 'fees'],

            // Straw-cost REFERENCE ranges — not our prices; shown in FAQ only (§2).
            ['straw_cost_reference', [
                'standard' => ['min' => 25, 'max' => 50],
                'premium' => ['min' => 50, 'max' => 300],
                'shipping' => ['min' => 150, 'max' => 250],
            ], 'Straw cost reference ranges (client pays their source)', 'reference'],
        ];

        foreach ($rates as [$key, $value, $label, $group]) {
            RateConfig::updateOrCreate(['key' => $key], compact('value', 'label', 'group'));
        }
    }
}
