<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Dispatch\DispatchDayService;
use App\Services\Dispatch\DispatchStop;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff dispatch / route view (spec §5.10). A day list of scheduled visits so
 * Jeff isn't crisscrossing — ordered by time, each flagged for out-of-range
 * clients with the distance fee surfaced (reusing the 6.6 service-area logic
 * and cached client distances). Explicitly NOT a route optimizer.
 */
class DispatchController extends Controller
{
    public function __construct(private readonly DispatchDayService $dispatch) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $tz = (string) config('protocol.timezone', config('app.timezone'));
        $date = isset($validated['date'])
            ? CarbonImmutable::parse($validated['date'], $tz)
            : CarbonImmutable::now($tz);

        $stops = $this->dispatch->forDate($date)
            ->map(fn (DispatchStop $stop) => $stop->toArray())
            ->values();

        return Inertia::render('admin/dispatch/Index', [
            'date' => $date->toDateString(),
            'stops' => $stops,
            'origin' => [
                'lat' => config('geocoding.origin.lat') !== null ? (float) config('geocoding.origin.lat') : null,
                'lng' => config('geocoding.origin.lng') !== null ? (float) config('geocoding.origin.lng') : null,
            ],
            'outOfRangeCount' => $stops->where('out_of_range', true)->count(),
        ]);
    }
}
