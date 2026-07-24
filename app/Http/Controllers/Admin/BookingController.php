<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Billing\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff booking back-office (spec §6.7). Jeff reviews the provisional queue,
 * confirms or declines first-time bookings, and sees the full calendar. Staff
 * can create bookings through the same scheduler via the client store endpoint
 * with staff privileges (§6.7).
 */
class BookingController extends Controller
{
    public function index(Request $request): Response
    {
        $bookings = Booking::query()
            ->with(['service:id,name,type', 'team:id,name', 'visits', 'protocol'])
            ->latest()
            ->get();

        return Inertia::render('admin/bookings/Index', [
            'reviewQueue' => $bookings->where('requires_review', true)->values()
                ->map(fn (Booking $b) => $this->summary($b)),
            'all' => $bookings->map(fn (Booking $b) => $this->summary($b))->values(),
        ]);
    }

    public function confirm(Request $request, Booking $booking, PaymentService $payments): RedirectResponse
    {
        $booking->forceFill([
            'status' => BookingStatus::Confirmed->value,
            'requires_review' => false,
            'review_reason' => null,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ])->save();

        // Reaching `confirmed` fires the charge on the card captured at booking
        // (spec §5.5). Cash bookings stay owed for Jeff to settle in person.
        $payments->settleConfirmedBooking($booking);

        return back()->with('status', 'Booking confirmed.');
    }

    public function decline(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate([
            'decline_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $booking->forceFill([
            'status' => BookingStatus::Declined->value,
            'requires_review' => false,
            'decline_reason' => $data['decline_reason'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ])->save();

        return back()->with('status', 'Booking declined.');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'team' => $booking->team?->name,
            'service' => $booking->service?->name,
            'type' => $booking->service?->type,
            'status' => $booking->status,
            'status_label' => $booking->bookingStatus()->label(),
            'requires_review' => $booking->requires_review,
            'review_reason' => $booking->review_reason,
            'is_oncall' => $booking->is_oncall,
            'distance_fee_flag' => $booking->distance_fee_flag,
            'proposed_start' => $booking->proposed_start?->toIso8601String(),
            'computed_windows' => $booking->computed_windows,
            'visits' => $booking->visits->map(fn ($v) => [
                'id' => $v->id,
                'type' => $v->type,
                'status' => $v->status,
                'scheduled_at' => $v->scheduled_at?->toIso8601String(),
                'billed_amount' => $v->billed_amount,
            ])->values(),
        ];
    }
}
