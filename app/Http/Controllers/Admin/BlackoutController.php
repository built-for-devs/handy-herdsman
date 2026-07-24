<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlackoutDate;
use App\Services\Booking\BlackoutConflictResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Staff blackout management (spec §6.3). Creating a blackout that lands over
 * existing bookings notifies those clients with alternative slots, and surfaces
 * any protocol already past Visit 1 as a manual-decision conflict.
 */
class BlackoutController extends Controller
{
    public function __construct(private BlackoutConflictResolver $resolver) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $blackout = BlackoutDate::create([
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $report = $this->resolver->resolve($blackout);

        $message = 'Blackout saved.';

        if ($report->hasConflicts()) {
            $message .= sprintf(
                ' %d client(s) notified with alternatives; %d in-progress protocol(s) need your manual decision.',
                count($report->rescheduled),
                count($report->manualConflicts),
            );
        }

        return back()->with('status', $message);
    }
}
