<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Models\AvailabilityRule;
use App\Models\BlackoutDate;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The per-weekday availability + blackout + Sunday-morning rules the scheduler
 * reads (spec §7, §10b — Scheduler & availability). Working hours, max visits
 * per day and the inter-appointment buffer are DATA in `availability_rules`;
 * they are never hardcoded here. The one hard safety rule that is not
 * per-weekday — no Sunday-morning appointment ever — lives in config/booking.
 *
 * On-call EMERGENCIES bypass all of this (see ScheduleValidator).
 */
class SchedulerAvailability
{
    private string $timezone;

    public function __construct(?string $timezone = null)
    {
        $this->timezone = $timezone ?? (string) config('protocol.timezone');
    }

    /** The availability rule for a weekday (0=Sun..6=Sat), or null. */
    public function ruleFor(int $dayOfWeek): ?AvailabilityRule
    {
        return AvailabilityRule::query()->where('day_of_week', $dayOfWeek)->first();
    }

    /** Whether the given instant's local date sits inside any blackout range. */
    public function isBlackout(CarbonInterface $moment): bool
    {
        $localDate = CarbonImmutable::instance($moment)->setTimezone($this->timezone)->toDateString();

        return BlackoutDate::query()
            ->whereDate('start_date', '<=', $localDate)
            ->whereDate('end_date', '>=', $localDate)
            ->exists();
    }

    /**
     * Why the given instant is NOT an ordinarily-bookable slot, or null if it
     * is. Checks working day, working hours, the never-Sunday-morning rule and
     * blackout dates — everything that depends only on the calendar, not on
     * other bookings (those live in ScheduleValidator).
     */
    public function slotConflict(CarbonInterface $moment): ?string
    {
        $local = CarbonImmutable::instance($moment)->setTimezone($this->timezone);

        if ($this->isSundayMorning($local)) {
            return 'No Sunday-morning appointments are available.';
        }

        $rule = $this->ruleFor($local->dayOfWeek);

        if ($rule === null || ! $rule->is_working_day) {
            return sprintf('%s is not a working day.', $local->format('l'));
        }

        if ($rule->start_time === null || $rule->end_time === null) {
            return sprintf('No working hours are configured for %s.', $local->format('l'));
        }

        $start = $local->setTimeFromTimeString($rule->start_time);
        $end = $local->setTimeFromTimeString($rule->end_time);

        if ($local->lt($start) || $local->gt($end)) {
            return sprintf(
                '%s is outside working hours (%s–%s).',
                $local->format('D g:i A'),
                $start->format('g:i A'),
                $end->format('g:i A'),
            );
        }

        if ($this->isBlackout($local)) {
            return sprintf('%s is blacked out.', $local->format('D, M j'));
        }

        return null;
    }

    /** True when the local instant is a Sunday before the config cut-off. */
    public function isSundayMorning(CarbonInterface $moment): bool
    {
        $local = CarbonImmutable::instance($moment)->setTimezone($this->timezone);

        if ($local->dayOfWeek !== CarbonInterface::SUNDAY) {
            return false;
        }

        $cutoff = $local->setTimeFromTimeString((string) config('booking.sunday_morning_until'));

        return $local->lt($cutoff);
    }

    public function timezone(): string
    {
        return $this->timezone;
    }
}
