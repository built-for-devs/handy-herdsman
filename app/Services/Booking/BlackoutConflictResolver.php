<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Enums\AnimalType;
use App\Enums\VisitStatus;
use App\Enums\VisitType;
use App\Models\BlackoutDate;
use App\Models\Booking;
use App\Notifications\BookingRescheduleNeededNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/**
 * Handles a blackout added OVER existing bookings (spec §6.3). For each booking
 * whose visits fall inside the blacked-out range:
 *
 *  - if the protocol is already past Visit 1 (CIDR inserted), V2/V3 are
 *    physiologically fixed — it CANNOT be auto-moved, so it is flagged for
 *    Jeff as a manual decision (never silently rebooked);
 *  - otherwise the client is notified with alternative slots that still satisfy
 *    protocol timing, and asked to confirm.
 */
class BlackoutConflictResolver
{
    public function __construct(
        private SchedulerAvailability $availability,
        private ProtocolScheduler $scheduler,
    ) {}

    public function resolve(BlackoutDate $blackout): BlackoutConflictReport
    {
        $tz = $this->availability->timezone();
        $startUtc = CarbonImmutable::parse($blackout->start_date, $tz)->startOfDay()->setTimezone('UTC');
        $endUtc = CarbonImmutable::parse($blackout->end_date, $tz)->endOfDay()->setTimezone('UTC');

        $bookings = Booking::query()
            ->whereHas('visits', function ($q) use ($startUtc, $endUtc) {
                $q->where('status', VisitStatus::Scheduled->value)
                    ->whereNotNull('scheduled_at')
                    ->whereBetween('scheduled_at', [$startUtc, $endUtc]);
            })
            ->with(['visits', 'protocol', 'team.owner', 'team.client'])
            ->get();

        $rescheduled = [];
        $manual = [];

        foreach ($bookings as $booking) {
            if ($this->isPastVisit1($booking)) {
                $this->flagManualConflict($booking);
                $manual[] = $booking;

                continue;
            }

            $this->notifyClient($booking, $endUtc);
            $rescheduled[] = $booking;
        }

        return new BlackoutConflictReport($rescheduled, $manual);
    }

    /** A protocol is locked once its Visit 1 has been completed (CIDR is in). */
    private function isPastVisit1(Booking $booking): bool
    {
        return $booking->visits
            ->where('type', VisitType::Visit1->value)
            ->where('status', VisitStatus::Completed->value)
            ->isNotEmpty();
    }

    private function flagManualConflict(Booking $booking): void
    {
        $booking->forceFill([
            'requires_review' => true,
            'review_reason' => 'Blackout conflicts with an in-progress protocol (past Visit 1) — '
                .'V2/V3 are physiologically fixed and cannot be moved. Manual decision needed.',
        ])->save();
    }

    private function notifyClient(Booking $booking, CarbonImmutable $afterUtc): void
    {
        $proposals = $this->proposals($booking, $afterUtc);

        $recipient = $booking->team?->owner;

        if ($recipient !== null) {
            Notification::send($recipient, new BookingRescheduleNeededNotification($booking, $proposals));
        }
    }

    /**
     * Alternative slots after the blackout that still satisfy protocol timing.
     *
     * @return list<string>
     */
    private function proposals(Booking $booking, CarbonImmutable $afterUtc): array
    {
        $protocol = $booking->protocol;

        if ($protocol === null) {
            return [];
        }

        $type = AnimalType::from($protocol->animal_type);
        $result = $this->scheduler->findCandidates($type, $afterUtc->addDay());

        return array_map(
            fn (ProtocolCandidate $c) => $c->schedule->displayTimezone()['visit1_at']->format('D, M j, Y g:i A'),
            array_slice($result->candidates, 0, 3),
        );
    }
}
