<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SemenInventory;
use App\Models\Team;
use App\Services\SemenCustodyService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff-facing management of the semen custody ledger (§5.6, §10b). We only
 * ever hold straws in custody — owned_by is forced to the client by the model.
 */
class SemenCustodyController extends Controller
{
    public function __construct(private SemenCustodyService $custody) {}

    public function index(): Response
    {
        return Inertia::render('admin/semen/Index', [
            'lots' => SemenInventory::query()
                ->with(['team:id,name', 'ledgerEntries' => fn ($q) => $q->orderByDesc('occurred_at')])
                ->orderByDesc('created_at')
                ->get(),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name']),
            'fees' => [
                'receipt' => $this->custody->receiptFee(),
                'storage' => $this->custody->storagePrice(),
                'storage_free_year_one' => $this->custody->storageFreeYearOne(),
                'storage_max_straws' => $this->custody->storageMaxStraws(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
            'source' => ['required', 'in:client,jeff'],
            'sire' => ['nullable', 'string', 'max:255'],
            'breed' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'with_ai' => ['boolean'],
            // Client-sourced intake info.
            'intake_shipping_address' => ['nullable', 'string'],
            'tank_details' => ['nullable', 'string', 'max:255'],
            'expected_arrival' => ['nullable', 'date'],
            // Jeff-sourced ordering info.
            'source_farm' => ['nullable', 'string', 'max:255'],
            'bull_info' => ['nullable', 'string', 'max:255'],
            'source_contact' => ['nullable', 'string', 'max:255'],
            'pay_to' => ['nullable', 'string', 'max:255'],
        ]);

        SemenInventory::create($data);

        return back();
    }

    public function receive(Request $request, SemenInventory $lot): RedirectResponse
    {
        $data = $request->validate([
            'straws' => ['required', 'integer', 'min:1'],
            'occurred_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->custody->receiveShipment(
            $lot,
            $data['straws'],
            isset($data['occurred_at']) ? CarbonImmutable::parse($data['occurred_at']) : null,
            $request->user(),
            $data['notes'] ?? null,
        );

        return back();
    }

    public function use(Request $request, SemenInventory $lot): RedirectResponse
    {
        $data = $request->validate([
            'straws' => ['required', 'integer', 'min:1'],
            'occurred_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->custody->useStraws(
            $lot,
            $data['straws'],
            isset($data['occurred_at']) ? CarbonImmutable::parse($data['occurred_at']) : null,
            $request->user(),
            $data['notes'] ?? null,
        );

        return back();
    }

    public function relocate(Request $request, SemenInventory $lot): RedirectResponse
    {
        $data = $request->validate([
            'location_to' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->custody->storeAt($lot, $data['location_to'], null, $request->user(), $data['notes'] ?? null);

        return back();
    }

    public function destroy(SemenInventory $lot): RedirectResponse
    {
        $lot->delete();

        return back();
    }
}
