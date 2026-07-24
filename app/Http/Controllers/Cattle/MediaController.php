<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cattle;

use App\Http\Controllers\Concerns\ResolvesCurrentTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cattle\StoreMediaRequest;
use App\Models\Cattle;
use App\Models\Media;
use App\Models\Visit;
use App\Support\TeamAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Photo/media uploads for a cattle profile (spec §228, §10b). Clients upload
 * for-sale photos; staff upload at appointments (healing progress, condition
 * comparison) and may link the photo to a visit. Files live on the config-
 * driven media disk (local in dev; swap to cloud via config). Media anchors to
 * the animal so the gallery reads chronologically.
 */
class MediaController extends Controller
{
    use ResolvesCurrentTeam;

    public function store(StoreMediaRequest $request, Cattle $cattle): RedirectResponse
    {
        // Uploading requires full access — vets are read-only and cannot upload (§4).
        abort_unless(TeamAccess::hasFullAccess($request->user(), $cattle->team), 403);

        // A linked visit must belong to the same animal/team (§10b tenant isolation).
        $visitId = $request->integer('visit_id') ?: null;

        if ($visitId !== null) {
            $visit = Visit::findOrFail($visitId);
            abort_unless($visit->team_id === $cattle->team_id, 403);
        }

        $path = $request->file('photo')->store('media', config('records.media_disk'));

        $media = new Media([
            'visit_id' => $visitId,
            'uploaded_by' => $request->user()->id,
            'uploaded_role' => TeamAccess::role($request->user(), $cattle->team) ?? 'member',
            'path' => $path,
            'caption' => $request->input('caption'),
            'taken_at' => $request->date('taken_at') ?? now(),
        ]);
        $media->mediable()->associate($cattle);
        $media->save();

        return back();
    }

    /** Stream the file through the policy so only authorized viewers can see it. */
    public function show(Request $request, Media $media): StreamedResponse
    {
        $this->authorize('view', $media);

        $disk = Storage::disk(config('records.media_disk'));
        abort_unless($disk->exists($media->path), 404);

        return $disk->response($media->path);
    }

    public function destroy(Request $request, Media $media): RedirectResponse
    {
        $this->authorize('delete', $media);

        $media->delete(); // soft delete — history preserved (§10b)

        return back();
    }
}
