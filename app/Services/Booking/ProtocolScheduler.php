<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Enums\AnimalType;
use App\Services\Protocol\ProtocolSchedule;
use App\Services\Protocol\ProtocolTimingService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Finds viable Visit-1 dates for a CIDR 10-day sync protocol (spec §6.2).
 *
 * The whole three-visit chain is scheduled UP FRONT: only a V1 whose downstream
 * V2 AND V3 all land in bookable slots is offered. Because V3 is a fixed 63h
 * (cow) / 54h (heifer) after V2, the V1 TIME OF DAY is what decides whether V3
 * lands in working hours — so the search probes candidate V1 times across each
 * working day rather than assuming a fixed start time.
 *
 * Timing math is delegated entirely to the tested M1 {@see ProtocolTimingService}
 * (absolute-duration, DST-safe, UTC). This class only decides which of its
 * outputs are OPERATIONALLY bookable.
 */
class ProtocolScheduler
{
    public function __construct(
        private ProtocolTimingService $timing,
        private ScheduleValidator $validator,
        private SchedulerAvailability $availability,
    ) {}

    /**
     * @param  CarbonInterface|null  $earliestVisit1  First date to consider (defaults to tomorrow).
     */
    public function findCandidates(AnimalType $animalType, ?CarbonInterface $earliestVisit1 = null): ProtocolSchedulingResult
    {
        $tz = $this->availability->timezone();
        $horizonDays = (int) config('protocol.search_horizon_days');
        $capDays = (int) config('booking.beyond_horizon_cap_days');
        $maxDates = (int) config('booking.max_candidate_dates');
        $step = max(5, (int) config('booking.candidate_slot_minutes'));

        $start = CarbonImmutable::instance($earliestVisit1 ?? now()->addDay())->setTimezone($tz)->startOfDay();
        $horizonEnd = $start->addDays($horizonDays);
        $capEnd = $start->addDays($capDays);

        /** @var list<ProtocolCandidate> $withinHorizon */
        $withinHorizon = [];
        /** @var list<ProtocolCandidate> $beyondHorizon */
        $beyondHorizon = [];

        for ($day = $start; $day->lt($capEnd); $day = $day->addDay()) {
            // Stop once we have enough in-horizon options to offer.
            if (count($withinHorizon) >= $maxDates) {
                break;
            }

            $schedule = $this->firstViableV1OnDay($day, $animalType, $step);

            if ($schedule === null) {
                continue;
            }

            $beyond = $day->gte($horizonEnd);
            $candidate = new ProtocolCandidate($schedule, $beyond);

            if ($beyond) {
                if (count($beyondHorizon) < $maxDates) {
                    $beyondHorizon[] = $candidate;
                }
                // Once past the horizon with a few options gathered, stop.
                if (count($beyondHorizon) >= $maxDates) {
                    break;
                }
            } else {
                $withinHorizon[] = $candidate;
            }
        }

        return $this->buildResult($withinHorizon, $beyondHorizon, $horizonDays);
    }

    /**
     * Validate ONE specific Visit-1 instant's whole chain. Returns the first
     * failing reason (naming which visit), or null if V1/V2/V3 are all bookable.
     */
    public function validateChain(CarbonInterface $visit1At, AnimalType $animalType, ?int $ignoreBookingId = null): ?string
    {
        $schedule = $this->timing->scheduleFromVisit1($visit1At, $animalType);

        foreach ($this->chainSlots($schedule) as $label => $moment) {
            $reason = $this->validator->slotConflict($moment, emergency: false, ignoreBookingId: $ignoreBookingId);

            if ($reason !== null) {
                return sprintf('%s cannot be scheduled: %s', $label, $reason);
            }
        }

        return null;
    }

    /** The recommended full schedule for a chosen V1 (for persistence). */
    public function schedule(CarbonInterface $visit1At, AnimalType $animalType): ProtocolSchedule
    {
        return $this->timing->scheduleFromVisit1($visit1At, $animalType);
    }

    /**
     * Probe candidate V1 times across a single local working day and return the
     * schedule for the first time whose V1/V2/V3 are all bookable, or null.
     */
    private function firstViableV1OnDay(CarbonImmutable $day, AnimalType $animalType, int $step): ?ProtocolSchedule
    {
        $rule = $this->availability->ruleFor($day->dayOfWeek);

        if ($rule === null || ! $rule->is_working_day || $rule->start_time === null || $rule->end_time === null) {
            return null;
        }

        $slot = $day->setTimeFromTimeString($rule->start_time);
        $lastSlot = $day->setTimeFromTimeString($rule->end_time);

        for (; $slot->lte($lastSlot); $slot = $slot->addMinutes($step)) {
            $schedule = $this->timing->scheduleFromVisit1($slot, $animalType);

            if ($this->chainIsBookable($schedule)) {
                return $schedule;
            }
        }

        return null;
    }

    private function chainIsBookable(ProtocolSchedule $schedule): bool
    {
        foreach ($this->chainSlots($schedule) as $moment) {
            if (! $this->validator->isBookable($moment)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, CarbonImmutable>
     */
    private function chainSlots(ProtocolSchedule $schedule): array
    {
        return [
            'Visit 1' => $schedule->visit1At,
            'Visit 2' => $schedule->visit2At,
            'Visit 3' => $schedule->visit3RecommendedAt,
        ];
    }

    /**
     * @param  list<ProtocolCandidate>  $withinHorizon
     * @param  list<ProtocolCandidate>  $beyondHorizon
     */
    private function buildResult(array $withinHorizon, array $beyondHorizon, int $horizonDays): ProtocolSchedulingResult
    {
        if ($withinHorizon !== []) {
            return new ProtocolSchedulingResult(
                candidates: $withinHorizon,
                hasWithinHorizon: true,
                horizonDays: $horizonDays,
                message: sprintf('%d available start date(s) in the next %d days.', count($withinHorizon), $horizonDays),
            );
        }

        if ($beyondHorizon !== []) {
            return new ProtocolSchedulingResult(
                candidates: $beyondHorizon,
                hasWithinHorizon: false,
                horizonDays: $horizonDays,
                message: sprintf(
                    'No start dates are available in the next %d days — the calendar is full. '
                    .'The next viable dates are shown below.',
                    $horizonDays,
                ),
            );
        }

        return new ProtocolSchedulingResult(
            candidates: [],
            hasWithinHorizon: false,
            horizonDays: $horizonDays,
            message: 'No viable protocol start dates could be found. Please contact us to arrange a visit.',
        );
    }
}
