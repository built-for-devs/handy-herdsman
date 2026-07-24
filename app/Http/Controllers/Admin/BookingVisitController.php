<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Services\Booking\StaffVisitManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Staff manual override of individual visits (spec §6.7). Jeff can mark a visit
 * failed (still billable at the normal rate) and freely adjust its charge,
 * schedule, mileage and notes. Reschedule proper is "create a new appointment".
 */
class BookingVisitController extends Controller
{
    public function __construct(private StaffVisitManager $visits) {}

    public function update(Request $request, Visit $visit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(VisitStatus::class)],
            'scheduled_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'staff_notes' => ['nullable', 'string', 'max:2000'],
            'mileage' => ['nullable', 'numeric', 'min:0'],
            'billed_amount' => ['nullable', 'numeric', 'min:0'],
            'fee_applied' => ['nullable', 'boolean'],
        ]);

        $this->visits->adjust($visit, array_filter($data, fn ($v) => $v !== null));

        return back()->with('status', 'Visit updated.');
    }

    public function fail(Request $request, Visit $visit): RedirectResponse
    {
        $data = $request->validate([
            'billed_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->visits->markFailed($visit, isset($data['billed_amount']) ? (float) $data['billed_amount'] : null);

        return back()->with('status', 'Visit marked failed — still billed at the normal rate.');
    }
}
