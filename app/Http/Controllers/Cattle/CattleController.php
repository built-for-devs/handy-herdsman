<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cattle;

use App\Http\Controllers\Concerns\ResolvesCurrentTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cattle\StoreCattleRequest;
use App\Http\Requests\Cattle\UpdateCattleRequest;
use App\Models\Cattle;
use App\Models\HealthRecord;
use App\Models\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cattle profiles CRUD (spec §5.5, §10b). Nothing hard-deletes — destroy soft-
 * deletes. Marking a profile `inactive` cancels its pending reminders via the
 * model observer. All access is authorized through the CattlePolicy (owners /
 * members full access, vets read-only on profile scope, staff bypass).
 */
class CattleController extends Controller
{
    use ResolvesCurrentTeam;

    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        return Inertia::render('cattle/Index', [
            'cattle' => $team->cattle()
                ->orderBy('reg_name')
                ->get()
                ->map(fn (Cattle $cattle) => $this->summary($cattle)),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', $this->cattleForTeam($request));

        return Inertia::render('cattle/Create', [
            'animalTypes' => ['heifer', 'cow', 'bull', 'steer'],
        ]);
    }

    public function store(StoreCattleRequest $request): RedirectResponse
    {
        $team = $this->currentTeam($request);
        $this->authorize('create', $this->cattleForTeam($request));

        $cattle = new Cattle($request->validated());
        $cattle->team_id = $team->id;
        $cattle->save();

        return to_route('cattle.show', $cattle);
    }

    public function show(Request $request, Cattle $cattle): Response
    {
        $this->authorize('view', $cattle);

        $cattle->load([
            'healthRecords' => fn ($query) => $query->latest('recorded_at')->latest('id'),
            'healthRecords.addedBy:id,name',
            'healthRecords.editedBy:id,name',
            'media' => fn ($query) => $query->orderByDesc('taken_at')->orderByDesc('id'),
            'media.uploadedBy:id,name',
            'media.visit:id,scheduled_at',
        ]);

        return Inertia::render('cattle/Show', [
            'cattle' => $this->summary($cattle),
            'canEdit' => $request->user()->can('update', $cattle),
            'recordTypes' => config('records.types'),
            'bcs' => config('records.bcs'),
            'records' => $cattle->healthRecords->map(fn (HealthRecord $record) => [
                'id' => $record->id,
                'type' => $record->type,
                'type_label' => config('records.types')[$record->type] ?? $record->type,
                'payload' => $record->payload,
                'bcs_score' => $record->bcs_score,
                'recorded_at' => $record->recorded_at,
                'added_role' => $record->added_role,
                'added_by' => $record->addedBy?->name,
                'edited_by' => $record->editedBy?->name,
                'edited_at' => $record->edited_at,
                'can_edit' => $request->user()->can('update', $record),
                'can_delete' => $request->user()->can('delete', $record),
            ]),
            'media' => $cattle->media->map(fn (Media $item) => [
                'id' => $item->id,
                'url' => route('cattle.media.show', $item->id),
                'caption' => $item->caption,
                'taken_at' => $item->taken_at,
                'uploaded_role' => $item->uploaded_role,
                'uploaded_by' => $item->uploadedBy?->name,
                'visit_id' => $item->visit_id,
                'can_delete' => $request->user()->can('delete', $item),
            ]),
        ]);
    }

    public function edit(Request $request, Cattle $cattle): Response
    {
        $this->authorize('update', $cattle);

        return Inertia::render('cattle/Edit', [
            'cattle' => $this->summary($cattle),
            'animalTypes' => ['heifer', 'cow', 'bull', 'steer'],
        ]);
    }

    public function update(UpdateCattleRequest $request, Cattle $cattle): RedirectResponse
    {
        $this->authorize('update', $cattle);

        $cattle->fill($request->validated())->save();

        return to_route('cattle.show', $cattle);
    }

    public function destroy(Request $request, Cattle $cattle): RedirectResponse
    {
        $this->authorize('delete', $cattle);

        $cattle->delete(); // soft delete — history preserved (§10b)

        return to_route('cattle.index');
    }

    /** A transient Cattle bound to the current team for create authorization. */
    private function cattleForTeam(Request $request): Cattle
    {
        return (new Cattle)->setRelation('team', $this->currentTeam($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Cattle $cattle): array
    {
        return [
            'id' => $cattle->id,
            'reg_name' => $cattle->reg_name,
            'herd_number' => $cattle->herd_number,
            'dob' => $cattle->dob,
            'breed' => $cattle->breed,
            'animal_type' => $cattle->animal_type,
            'has_calved' => $cattle->has_calved,
            'status' => $cattle->status->value,
            'a2a2' => $cattle->a2a2,
            'for_sale' => $cattle->for_sale,
            'for_sale_shared_fields' => $cattle->for_sale_shared_fields,
            'notes' => $cattle->notes,
        ];
    }
}
