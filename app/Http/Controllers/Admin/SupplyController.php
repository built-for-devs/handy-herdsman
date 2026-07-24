<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Supply;
use App\Models\SupplyUsageProfile;
use App\Services\SupplyStockAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff-facing management of Jeff's working stock and per-service usage
 * profiles (§5.6b).
 */
class SupplyController extends Controller
{
    public function index(SupplyStockAlertService $alerts): Response
    {
        return Inertia::render('admin/supplies/Index', [
            'supplies' => Supply::query()->orderBy('item')->get(),
            'lowStock' => $alerts->lowStock()->values(),
            'usageProfiles' => SupplyUsageProfile::query()->with('service:id,name')->get(),
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Supply::create($this->validated($request));

        return back();
    }

    public function update(Request $request, Supply $supply): RedirectResponse
    {
        $supply->update($this->validated($request));

        return back();
    }

    public function destroy(Supply $supply): RedirectResponse
    {
        $supply->delete();

        return back();
    }

    /**
     * Create or replace the usage profile (default consumables) for a service.
     */
    public function saveUsageProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'exists:services,id'],
            'consumables' => ['array'],
            'consumables.*' => ['numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        SupplyUsageProfile::updateOrCreate(
            ['service_id' => $data['service_id']],
            ['consumables' => $data['consumables'] ?? [], 'notes' => $data['notes'] ?? null],
        );

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'item' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
            'on_hand' => ['required', 'numeric', 'min:0'],
            'low_stock_threshold' => ['required', 'numeric', 'min:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'is_prescription' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
