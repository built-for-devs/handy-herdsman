<?php

declare(strict_types=1);

namespace App\Services\Dispatch;

use App\Models\Client;
use App\Models\Visit;
use App\Services\Booking\ServiceAreaResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds Jeff's day/route view (spec §5.10): the scheduled visits for one day,
 * ordered by time, each flagged with the client's cached distance / out-of-
 * range status / distance fee. Deliberately minimal — a day list + distance
 * flag + fee trigger, NOT a territory-day optimization engine. Distance comes
 * from the cached client mileage via {@see ServiceAreaResolver}; no new
 * geocoding is ever performed here.
 */
class DispatchDayService
{
    public function __construct(private readonly ServiceAreaResolver $serviceArea) {}

    /**
     * The ordered stops for the local calendar day containing $date. Only
     * scheduled (upcoming, not cancelled/failed) visits appear on the route.
     *
     * @return Collection<int, DispatchStop>
     */
    public function forDate(CarbonInterface $date): Collection
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        return Visit::query()
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [$start, $end])
            ->with(['team.client', 'cattle:id,reg_name'])
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Visit $visit) => $this->toStop($visit));
    }

    private function toStop(Visit $visit): DispatchStop
    {
        /** @var Client|null $client */
        $client = $visit->team?->client;

        $area = $client !== null ? $this->serviceArea->resolve($client) : null;

        return new DispatchStop(
            visitId: $visit->id,
            type: $visit->type,
            status: $visit->status,
            scheduledAt: $visit->scheduled_at?->toIso8601String(),
            teamName: $visit->team?->name,
            clientName: $client?->contact_name,
            address: $client?->fullAddress(),
            lat: $client?->lat !== null ? (float) $client->lat : null,
            lng: $client?->lng !== null ? (float) $client->lng : null,
            distanceMiles: $area?->distanceMiles,
            inRange: $area?->inRange ?? true,
            feeApplies: $area?->feeApplies ?? false,
            feeAmount: $area?->feeAmount ?? 0.0,
            declined: $area?->declined ?? false,
        );
    }
}
