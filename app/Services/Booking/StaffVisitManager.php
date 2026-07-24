<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Enums\VisitStatus;
use App\Models\Visit;

/**
 * Jeff's manual override on individual visits (spec §6.7, §10b). A failed or
 * aborted visit (cow won't load, no chute, not contained) is STILL billed at
 * the normal visit rate — there is no automated trip-fee logic, and Jeff can
 * freely adjust the charge and every other field afterward.
 */
class StaffVisitManager
{
    public function __construct(private BookingPricing $pricing) {}

    /**
     * Record a failed/aborted visit. It stays billable at the normal farm-call
     * rate unless Jeff supplies a specific amount.
     */
    public function markFailed(Visit $visit, ?float $amount = null): Visit
    {
        $visit->forceFill([
            'status' => VisitStatus::Failed->value,
            'fee_applied' => true,
            'billed_amount' => $amount ?? $this->pricing->failedVisitAmount(),
        ])->save();

        return $visit;
    }

    /**
     * Free-form staff edit of a visit — the escape hatch for every irregular
     * case (reschedule to a new time, adjust mileage/charge, correct notes).
     * Reschedule proper is "create a new appointment"; this just edits in place.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function adjust(Visit $visit, array $attributes): Visit
    {
        $allowed = array_intersect_key($attributes, array_flip([
            'status', 'scheduled_at', 'completed_at', 'staff_notes', 'mileage', 'fee_applied', 'billed_amount',
        ]));

        $visit->forceFill($allowed)->save();

        return $visit;
    }
}
