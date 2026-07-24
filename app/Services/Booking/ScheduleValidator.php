<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\VisitStatus;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Validates a single proposed visit slot against Jeff's WHOLE calendar (spec
 * §10b — Scheduler). Jeff is one person, so collisions and the daily visit cap
 * are global across every team, not tenant-scoped. Layers three constraints on
 * top of {@see SchedulerAvailability}:
 *
 *  1. working day / hours / Sunday-morning / blackout (availability),
 *  2. the inter-appointment buffer + assumed visit duration (no overlaps),
 *  3. max visits per day for that weekday.
 *
 * On-call EMERGENCIES bypass every check — Jeff always takes an emergency.
 */
class ScheduleValidator
{
    public function __construct(private SchedulerAvailability $availability) {}

    /**
     * Why the slot cannot be booked, or null if it is bookable.
     *
     * @param  bool  $emergency  On-call emergencies bypass all availability.
     * @param  int|null  $ignoreBookingId  Exclude a booking's own visits (edits/reschedule).
     */
    public function slotConflict(
        CarbonInterface $proposedAt,
        bool $emergency = false,
        ?int $ignoreBookingId = null,
    ): ?string {
        if ($emergency) {
            return null;
        }

        $utc = CarbonImmutable::instance($proposedAt)->setTimezone('UTC');

        if (($reason = $this->availability->slotConflict($utc)) !== null) {
            return $reason;
        }

        if (($reason = $this->collisionConflict($utc, $ignoreBookingId)) !== null) {
            return $reason;
        }

        return $this->capacityConflict($utc, $ignoreBookingId);
    }

    public function isBookable(CarbonInterface $proposedAt, bool $emergency = false, ?int $ignoreBookingId = null): bool
    {
        return $this->slotConflict($proposedAt, $emergency, $ignoreBookingId) === null;
    }

    private function collisionConflict(CarbonImmutable $utc, ?int $ignoreBookingId): ?string
    {
        $duration = (int) config('booking.visit_duration_minutes');
        $buffer = $this->bufferMinutes($utc);
        $gap = $duration + $buffer;

        // Two visits collide when their start times are closer than one visit
        // length plus the travel buffer.
        $conflict = $this->activeVisitsQuery($ignoreBookingId)
            ->whereBetween('scheduled_at', [
                $utc->subMinutes($gap),
                $utc->addMinutes($gap),
            ])
            ->exists();

        return $conflict
            ? 'That time collides with another appointment (including travel buffer).'
            : null;
    }

    private function capacityConflict(CarbonImmutable $utc, ?int $ignoreBookingId): ?string
    {
        $local = $utc->setTimezone($this->availability->timezone());
        $rule = $this->availability->ruleFor($local->dayOfWeek);
        $max = $rule?->max_visits_per_day;

        if ($max === null) {
            return null;
        }

        $dayStartUtc = $local->startOfDay()->setTimezone('UTC');
        $dayEndUtc = $local->endOfDay()->setTimezone('UTC');

        $count = $this->activeVisitsQuery($ignoreBookingId)
            ->whereBetween('scheduled_at', [$dayStartUtc, $dayEndUtc])
            ->count();

        return $count >= $max
            ? sprintf('%s is fully booked (%d of %d visits).', $local->format('D, M j'), $count, $max)
            : null;
    }

    /** Buffer minutes for the weekday the slot falls on. */
    private function bufferMinutes(CarbonImmutable $utc): int
    {
        $local = $utc->setTimezone($this->availability->timezone());

        return (int) ($this->availability->ruleFor($local->dayOfWeek)?->buffer_minutes ?? 0);
    }

    /**
     * Jeff's live calendar: scheduled visits with a time, whose booking is not
     * declined/cancelled, across every team.
     *
     * @return Builder<Visit>
     */
    private function activeVisitsQuery(?int $ignoreBookingId)
    {
        return Visit::query()
            ->whereNotNull('scheduled_at')
            ->where('status', VisitStatus::Scheduled->value)
            ->when($ignoreBookingId !== null, fn ($q) => $q->where(function ($q) use ($ignoreBookingId) {
                $q->whereNull('booking_id')->orWhere('booking_id', '!=', $ignoreBookingId);
            }))
            ->where(function ($q) {
                $q->whereNull('booking_id')->orWhereHas('booking', function ($b) {
                    $b->whereNotIn('status', [
                        BookingStatus::Declined->value,
                        BookingStatus::Cancelled->value,
                    ]);
                });
            });
    }
}
