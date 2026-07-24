<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cattle;

use App\Http\Controllers\Concerns\ResolvesCurrentTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cattle\StoreHealthRecordRequest;
use App\Http\Requests\Cattle\UpdateHealthRecordRequest;
use App\Models\Cattle;
use App\Models\HealthRecord;
use App\Support\TeamAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Health records nested under a cattle profile (spec §5.5, §6, §10b).
 *
 * Attribution + permissions: the author (added_by/added_role) is stamped on
 * create. Clients CANNOT edit/delete staff-added records; Jeff CAN edit
 * client-added records — those edits are attributed to him (edited_by/at). All
 * enforced through HealthRecordPolicy.
 */
class HealthRecordController extends Controller
{
    use ResolvesCurrentTeam;

    public function store(StoreHealthRecordRequest $request, Cattle $cattle): RedirectResponse
    {
        $this->authorize('view', $cattle);

        $record = new HealthRecord($request->validated());
        $record->team_id = $cattle->team_id;
        $record->cattle_id = $cattle->id;
        $record->recorded_at ??= now();
        $record->added_by = $request->user()->id;
        $record->added_role = TeamAccess::role($request->user(), $cattle->team) ?? 'member';

        $this->authorize('create', $record);
        $record->save();

        return back();
    }

    public function update(UpdateHealthRecordRequest $request, HealthRecord $record): RedirectResponse
    {
        $this->authorize('update', $record);

        $record->fill($request->validated());

        // Attribute the edit. Staff editing a client-added record is the case
        // that must be tracked (§10b — "Jeff CAN edit client-added records, attributed").
        if ($request->user()->isStaff() && $record->added_role !== 'staff') {
            $record->edited_by = $request->user()->id;
            $record->edited_role = 'staff';
            $record->edited_at = now();
        }

        $record->save();

        return back();
    }

    public function destroy(Request $request, HealthRecord $record): RedirectResponse
    {
        $this->authorize('delete', $record);

        $record->delete(); // soft delete — audit history preserved (§10b)

        return back();
    }
}
