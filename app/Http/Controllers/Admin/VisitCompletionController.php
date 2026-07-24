<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreVisitCompletionRequest;
use App\Models\SemenInventory;
use App\Models\Service;
use App\Models\Supply;
use App\Models\Visit;
use App\Services\SupplyUsageService;
use App\Services\VisitCompletionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Appointment completion form (#239, §5.5) — the keystone flow. Mobile-first,
 * one-handed, gloves-on: Jeff confirms the procedure + the actual completed
 * timestamp (authoritative; V3 recomputes from it), records straws used/wasted,
 * BCS per animal, supplies consumed, mileage, and notes. The form persists a
 * local draft and syncs on reconnect (#240) — the client handles that; this
 * controller just serves and saves.
 */
class VisitCompletionController extends Controller
{
    public function __construct(
        private VisitCompletionService $completion,
        private SupplyUsageService $supplies,
    ) {}

    public function create(Visit $visit): Response
    {
        $visit->loadMissing(['booking.service', 'booking.cattle', 'protocol', 'cattle', 'completion']);

        $service = $visit->service();

        return Inertia::render('admin/completion/Form', [
            'visit' => [
                'id' => $visit->id,
                'type' => $visit->type,
                'status' => $visit->status,
                'scheduled_at' => optional($visit->scheduled_at)->toIso8601String(),
                'completed_at' => optional($visit->completed_at)->toIso8601String(),
                'service' => $service?->name,
                'is_breeding_related' => $visit->isBreedingRelated(),
                'is_ai' => $service !== null && $service->breedsAnimal(),
            ],
            'animals' => $visit->animals()->map(fn ($c) => [
                'id' => $c->id,
                'label' => $c->reg_name ?? $c->herd_number ?? "Animal #{$c->id}",
                'breed' => $c->breed,
            ])->values(),
            'semenLots' => SemenInventory::query()
                ->where('team_id', $visit->team_id)
                ->where('straws_count', '>', 0)
                ->orderBy('sire')
                ->get(['id', 'sire', 'breed', 'straws_count', 'location'])
                ->map(fn (SemenInventory $lot) => [
                    'id' => $lot->id,
                    'label' => trim(($lot->sire ?? 'Unknown sire').($lot->breed ? " · {$lot->breed}" : '')),
                    'straws_count' => $lot->straws_count,
                ])
                ->values(),
            'supplies' => $this->supplyOptions($service),
            'existing' => $visit->completion !== null ? [
                'completed_at' => optional($visit->completion->completed_at)->toIso8601String(),
                'straws_used' => $visit->completion->straws_used,
                'straws_wasted' => $visit->completion->straws_wasted,
                'mileage' => $visit->completion->mileage,
                'notes' => $visit->completion->notes,
            ] : null,
            'bcsRange' => [
                'min' => (int) config('records.bcs.min', 1),
                'max' => (int) config('records.bcs.max', 9),
            ],
            'draftKey' => "visit-completion-{$visit->id}",
        ]);
    }

    public function store(StoreVisitCompletionRequest $request, Visit $visit): RedirectResponse
    {
        $this->completion->complete($visit, $request->validated(), $request->user());

        return redirect()
            ->route('admin.bookings.index')
            ->with('status', 'Appointment completed.');
    }

    /**
     * The supply picker: the service's usage-profile defaults, pre-filled and
     * adjustable, plus every supply so Jeff can add one that wasn't defaulted.
     *
     * @return array<int, array<string, mixed>>
     */
    private function supplyOptions(?Service $service): array
    {
        $defaults = $service !== null ? $this->supplies->defaultConsumption($service) : [];

        return Supply::query()
            ->orderBy('item')
            ->get(['id', 'item', 'unit', 'on_hand'])
            ->map(fn (Supply $s) => [
                'id' => $s->id,
                'item' => $s->item,
                'unit' => $s->unit,
                'on_hand' => (float) $s->on_hand,
                'default_qty' => (float) ($defaults[$s->id] ?? 0),
            ])
            ->values()
            ->all();
    }
}
