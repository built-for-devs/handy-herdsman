<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdateStaffChannelOverrideRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;

/**
 * Staff override of a client's channel preferences (spec §5.7). The override
 * wins over the client's own prefs; the client's settings remain intact
 * underneath. Restricted to staff by the form request's authorize().
 */
class StaffChannelOverrideController extends Controller
{
    public function update(UpdateStaffChannelOverrideRequest $request, Client $client): RedirectResponse
    {
        $override = $request->input('override');

        $client->setStaffChannelOverride(
            $override === [] ? null : $override,
            $override === null || $override === [] ? null : $request->input('note'),
        );

        $client->save();

        return back();
    }
}
