<?php

namespace App\Services;

use App\Models\Service;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * SupplyUsageService — turns a service's usage profile into concrete supply
 * decrements at appointment completion (§5.6b).
 *
 * M7's completion hook calls {@see applyForService()} with any manual
 * adjustments from the completion form; the decrement logic itself is unit
 * tested directly here. Each decrement triggers a low-stock re-check so Jeff
 * gets a reorder alert the moment stock crosses the threshold.
 */
class SupplyUsageService
{
    public function __construct(private SupplyStockAlertService $alerts) {}

    /**
     * Default consumables for a service, keyed by supply id => quantity, taken
     * from its usage profile. Empty when the service has no profile.
     *
     * @return array<int, float>
     */
    public function defaultConsumption(Service $service): array
    {
        $profile = $service->usageProfile()->first();

        return $this->normalize($profile?->consumables ?? []);
    }

    /**
     * Resolve what will actually be consumed: profile defaults overlaid with
     * manual adjustments from the completion form. An adjustment REPLACES the
     * default quantity for a supply (0 removes it), and may add supplies the
     * profile didn't list.
     *
     * @param  array<int|string, int|float>  $manualAdjustments
     * @return array<int, float>
     */
    public function resolveConsumption(Service $service, array $manualAdjustments = []): array
    {
        $consumption = $this->defaultConsumption($service);

        foreach ($this->normalize($manualAdjustments) as $supplyId => $qty) {
            $consumption[$supplyId] = $qty;
        }

        // Drop zero/negative quantities — nothing to decrement.
        return array_filter($consumption, fn (float $qty) => $qty > 0);
    }

    /**
     * Resolve a service's consumption (with optional manual adjustments) and
     * decrement stock. This is the clean entry point M7 calls on completion.
     *
     * @param  array<int|string, int|float>  $manualAdjustments
     * @return array{consumption: array<int, float>, low_stock: Collection<int, Supply>, total_cost: float}
     */
    public function applyForService(Service $service, array $manualAdjustments = [], ?User $recordedBy = null): array
    {
        return $this->decrement($this->resolveConsumption($service, $manualAdjustments), $recordedBy);
    }

    /**
     * Decrement supplies by the given consumption map (supply id => quantity),
     * then alert staff about anything that fell to/below its threshold.
     *
     * @param  array<int|string, int|float>  $consumption
     * @return array{consumption: array<int, float>, low_stock: Collection<int, Supply>, total_cost: float}
     */
    public function decrement(array $consumption, ?User $recordedBy = null): array
    {
        $consumption = array_filter($this->normalize($consumption), fn (float $qty) => $qty > 0);

        /** @var Collection<int, Supply> $lowStock */
        $lowStock = new Collection;
        $totalCost = 0.0;

        DB::transaction(function () use ($consumption, &$lowStock, &$totalCost) {
            foreach ($consumption as $supplyId => $qty) {
                $supply = Supply::query()->find($supplyId);

                if (! $supply) {
                    throw new InvalidArgumentException("Unknown supply id [{$supplyId}] in usage profile.");
                }

                $supply->on_hand = (float) $supply->on_hand - $qty;
                $supply->save();

                $totalCost += $qty * (float) $supply->unit_cost;

                if ($supply->isLowStock()) {
                    $lowStock->push($supply);
                }
            }
        });

        $this->alerts->alert($lowStock);

        return [
            'consumption' => $consumption,
            'low_stock' => $lowStock,
            'total_cost' => round($totalCost, 2),
        ];
    }

    /**
     * Cast a raw supply_id => qty map to int keys / float values.
     *
     * @param  array<int|string, int|float>  $map
     * @return array<int, float>
     */
    private function normalize(array $map): array
    {
        $out = [];

        foreach ($map as $supplyId => $qty) {
            $out[(int) $supplyId] = (float) $qty;
        }

        return $out;
    }
}
