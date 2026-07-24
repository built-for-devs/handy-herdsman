<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PregCheckMethod;
use App\Enums\PregCheckState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\RecordPregCheckResultRequest;
use App\Http\Requests\Booking\StorePregCheckRequest;
use App\Models\Cattle;
use App\Models\PregCheck;
use App\Models\Visit;
use App\Services\PregCheckService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Async preg-check result flow (#241, §10b). Jeff records blood draws (pending)
 * and palpation results (immediate), then records the definitive lab result on
 * pending blood checks later. Clients never touch this — staff-only route.
 */
class PregCheckController extends Controller
{
    public function __construct(private PregCheckService $checks) {}

    public function index(): Response
    {
        return Inertia::render('admin/preg-checks/Index', [
            'pending' => PregCheck::query()
                ->where('state', PregCheckState::Pending->value)
                ->with(['cattle:id,reg_name,herd_number', 'team:id,name'])
                ->orderBy('created_at')
                ->get()
                ->map(fn (PregCheck $c) => $this->row($c))
                ->values(),
            'recent' => PregCheck::query()
                ->whereNot('state', PregCheckState::Pending->value)
                ->with(['cattle:id,reg_name,herd_number', 'team:id,name'])
                ->latest('result_recorded_at')
                ->limit(25)
                ->get()
                ->map(fn (PregCheck $c) => $this->row($c))
                ->values(),
            'labFee' => $this->checks->labFee(),
            'finalStates' => array_map(fn (PregCheckState $s) => $s->value, PregCheckState::finalStates()),
        ]);
    }

    public function store(StorePregCheckRequest $request): RedirectResponse
    {
        $cattle = Cattle::query()->findOrFail($request->integer('cattle_id'));

        $state = $request->filled('state') ? PregCheckState::from($request->string('state')->value()) : null;

        $this->checks->record(
            cattle: $cattle,
            method: PregCheckMethod::from($request->string('method')->value()),
            recordedBy: $request->user(),
            visit: $request->filled('visit_id') ? Visit::find($request->integer('visit_id')) : null,
            labRequested: $request->boolean('lab_requested'),
            immediateState: $state,
        );

        return back()->with('status', 'Preg check recorded.');
    }

    public function result(RecordPregCheckResultRequest $request, PregCheck $pregCheck): RedirectResponse
    {
        $this->checks->recordResult(
            $pregCheck,
            PregCheckState::from($request->string('state')->value()),
            $request->user(),
        );

        return back()->with('status', 'Result recorded.');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(PregCheck $check): array
    {
        return [
            'id' => $check->id,
            'animal' => $check->cattle?->reg_name ?? $check->cattle?->herd_number ?? "Animal #{$check->cattle_id}",
            'team' => $check->team?->name,
            'method' => $check->method->value,
            'lab_requested' => $check->lab_requested,
            'fee' => $this->checks->feeFor($check),
            'state' => $check->state->value,
            'result_recorded_at' => optional($check->result_recorded_at)->toIso8601String(),
        ];
    }
}
