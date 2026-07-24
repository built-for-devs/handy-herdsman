<?php

declare(strict_types=1);

namespace App\Http\Controllers\ForSale;

use App\Http\Controllers\Concerns\ResolvesCurrentTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\ForSale\UpdateForSaleListingRequest;
use App\Models\Cattle;
use Illuminate\Http\RedirectResponse;

/**
 * Manage a single animal's for-sale listing (spec §5.9). A client lists their
 * own herd animal (one-tap toggle + field-share checkboxes); staff can post
 * cattle Jeff knows others want to sell. Access reuses the CattlePolicy update
 * ability — owners/members for their team, staff for any animal. No transaction
 * flow: this only toggles visibility and the shared-field selection.
 */
class ForSaleListingController extends Controller
{
    use ResolvesCurrentTeam;

    public function update(UpdateForSaleListingRequest $request, Cattle $cattle): RedirectResponse
    {
        $this->authorize('update', $cattle);

        if ($request->boolean('for_sale')) {
            $role = $request->user()->isStaff() ? 'staff' : 'client';
            $cattle->listForSale($request->input('shared_fields', []), $role);
        } else {
            $cattle->unlistFromSale();
        }

        return to_route('cattle.show', $cattle)->with('status', 'Listing updated.');
    }
}
