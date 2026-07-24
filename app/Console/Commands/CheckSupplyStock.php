<?php

namespace App\Console\Commands;

use App\Services\SupplyStockAlertService;
use Illuminate\Console\Command;

/**
 * Scans Jeff's stock and alerts staff about anything at/below its reorder
 * threshold (§5.6b). Safe to run on a schedule or by hand.
 */
class CheckSupplyStock extends Command
{
    protected $signature = 'supplies:check-stock';

    protected $description = 'Alert staff about supplies at or below their reorder threshold';

    public function handle(SupplyStockAlertService $alerts): int
    {
        $low = $alerts->scanAndAlert();

        if ($low->isEmpty()) {
            $this->info('All supplies are above their reorder thresholds.');

            return self::SUCCESS;
        }

        $this->warn("{$low->count()} supply item(s) at or below threshold:");
        foreach ($low as $supply) {
            $this->line(sprintf('  - %s: %s on hand (threshold %s)', $supply->item, $supply->on_hand, $supply->low_stock_threshold));
        }

        return self::SUCCESS;
    }
}
