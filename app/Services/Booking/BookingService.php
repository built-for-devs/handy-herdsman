<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Enums\AnimalType;
use App\Enums\BookingStatus;
use App\Enums\PlanType;
use App\Enums\VisitStatus;
use App\Enums\VisitType;
use App\Exceptions\BookingValidationException;
use App\Models\Booking;
use App\Models\Cattle;
use App\Models\Protocol;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\OnCallPagerNotification;
use App\Services\Protocol\ProtocolSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The booking engine core (spec §6.1). Books IMMEDIATELY (no pre-approval) and
 * routes by `services.type`:
 *
 *  - protocol → the CIDR sync scheduler (3 visits) or a single natural call;
 *  - oncall   → an expedited request that pages Jeff instantly;
 *  - standard → a single dated visit (may cover many animals).
 *
 * First-time clients are held PROVISIONAL for staff review (24h SLA); active
 * clients self-confirm after validation; global staff bookings confirm outright.
 * Everything is team-scoped and wrapped in a single transaction.
 */
class BookingService
{
    public function __construct(
        private ProtocolScheduler $scheduler,
        private ScheduleValidator $validator,
        private AnimalGroupValidator $groups,
        private ServiceAreaResolver $serviceArea,
    ) {}

    public function book(BookingRequest $request): Booking
    {
        $service = $request->service;
        $cattle = $this->resolveCattle($request);

        // Same-set animal validation (breeding eligibility + mixed-group block).
        $windowType = $this->groups->validate($service, $cattle);

        // Distance / service-area. A declined zone blocks a client but is a
        // manual-decision flag for staff (Jeff's judgment).
        $area = $this->serviceArea->resolve($this->client($request));

        if ($area->declined && ! $request->byStaff) {
            throw new BookingValidationException(
                'Your location is outside our service area for online booking. '
                .'Please contact us so we can review your request.'
            );
        }

        return DB::transaction(function () use ($request, $service, $cattle, $windowType, $area) {
            return match ($service->type) {
                'oncall' => $this->bookOnCall($request, $cattle, $area),
                'protocol' => $this->bookProtocol($request, $cattle, $windowType, $area),
                default => $this->bookStandard($request, $cattle, $area),
            };
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Flow: protocol (sync = 3 visits, natural = single call)
    |--------------------------------------------------------------------------
    */
    private function bookProtocol(BookingRequest $request, Collection $cattle, ?AnimalType $windowType, ServiceAreaResult $area): Booking
    {
        $animalType = $windowType ?? AnimalType::Cow;

        if ($this->isSyncPlan($request->service)) {
            return $this->bookSyncProtocol($request, $cattle, $animalType, $area);
        }

        return $this->bookNaturalProtocol($request, $cattle, $animalType, $area);
    }

    private function bookSyncProtocol(BookingRequest $request, Collection $cattle, AnimalType $animalType, ServiceAreaResult $area): Booking
    {
        $visit1 = $request->proposedStart;

        if ($visit1 === null) {
            throw new BookingValidationException('Choose a Visit 1 date to start the protocol.');
        }

        // Full up-front validation of the whole three-visit chain (§6.2).
        $reason = $this->scheduler->validateChain($visit1, $animalType);

        if ($reason !== null) {
            throw new BookingValidationException($reason);
        }

        $schedule = $this->scheduler->schedule($visit1, $animalType);

        $booking = $this->createBooking($request, $area, $schedule, isOnCall: false);

        $protocol = Protocol::create([
            'team_id' => $request->team->id,
            'booking_id' => $booking->id,
            'service_id' => $request->service->id,
            'plan_type' => PlanType::Sync->value,
            'animal_type' => $animalType->value,
            'visit1_at' => $schedule->visit1At,
            'visit2_at' => $schedule->visit2At,
            'visit3_window_start' => $schedule->visit3WindowStart,
            'visit3_window_end' => $schedule->visit3WindowEnd,
            'visit3_recommended_at' => $schedule->visit3RecommendedAt,
            'visit3_conflict' => false,
            'status' => 'scheduled',
        ]);

        $protocol->cattle()->sync($cattle->pluck('id')->all());
        $booking->cattle()->sync($cattle->pluck('id')->all());

        foreach ([
            [VisitType::Visit1, $schedule->visit1At],
            [VisitType::Visit2, $schedule->visit2At],
            [VisitType::Visit3, $schedule->visit3RecommendedAt],
        ] as [$type, $at]) {
            $this->createVisit($request, $booking, $type, $at, $protocol->id);
        }

        return $booking;
    }

    private function bookNaturalProtocol(BookingRequest $request, Collection $cattle, AnimalType $animalType, ServiceAreaResult $area): Booking
    {
        $at = $request->proposedStart ?? $this->defaultVisitTime();

        if (($reason = $this->validator->slotConflict($at)) !== null) {
            throw new BookingValidationException("That time is not available: {$reason}");
        }

        $booking = $this->createBooking($request, $area, isOnCall: false);

        $protocol = Protocol::create([
            'team_id' => $request->team->id,
            'booking_id' => $booking->id,
            'service_id' => $request->service->id,
            'plan_type' => PlanType::Natural->value,
            'animal_type' => $animalType->value,
            'visit1_at' => CarbonImmutable::instance($at)->setTimezone('UTC'),
            'status' => 'scheduled',
        ]);

        $protocol->cattle()->sync($cattle->pluck('id')->all());
        $booking->cattle()->sync($cattle->pluck('id')->all());
        $this->createVisit($request, $booking, VisitType::Visit1, $at, $protocol->id);

        return $booking;
    }

    /*
    |--------------------------------------------------------------------------
    | Flow: standard (single dated visit, one or many animals)
    |--------------------------------------------------------------------------
    */
    private function bookStandard(BookingRequest $request, Collection $cattle, ServiceAreaResult $area): Booking
    {
        $at = $request->proposedStart ?? $this->defaultVisitTime();

        if (($reason = $this->validator->slotConflict($at)) !== null) {
            throw new BookingValidationException("That time is not available: {$reason}");
        }

        $booking = $this->createBooking($request, $area, isOnCall: false);
        $booking->cattle()->sync($cattle->pluck('id')->all());
        $this->createVisit($request, $booking, VisitType::Standard, $at, null);

        return $booking;
    }

    /*
    |--------------------------------------------------------------------------
    | Flow: on-call (expedited, bypasses availability, pages Jeff)
    |--------------------------------------------------------------------------
    */
    private function bookOnCall(BookingRequest $request, Collection $cattle, ServiceAreaResult $area): Booking
    {
        $kind = $request->onCallKind;
        $observedAt = $request->heatObservedAt ?? now();
        $guidance = $kind?->breedingGuidance($observedAt) ?? 'Respond as soon as possible.';

        // On-call always awaits Jeff's response (he is paged and acts in-app).
        $booking = $this->createBooking(
            $request,
            $area,
            isOnCall: true,
            forceReview: true,
            reviewReason: sprintf('On-call: %s', $kind?->label() ?? 'request'),
        );

        $booking->cattle()->sync($cattle->pluck('id')->all());

        // A placeholder visit with no time — Jeff schedules it when he responds.
        $this->createVisit($request, $booking, VisitType::OnCall, null, null);

        $this->pageStaff($booking, $request, $guidance);

        return $booking;
    }

    private function pageStaff(Booking $booking, BookingRequest $request, string $guidance): void
    {
        $recipients = User::query()->staff()->get();
        $summary = sprintf(
            '%s request from %s',
            $request->onCallKind?->label() ?? 'On-call',
            $request->team->name,
        );

        if ($recipients->isNotEmpty()) {
            // Sent synchronously — the pager must fire the instant the request
            // lands, never deferred behind a queue worker (§6.5).
            Notification::send($recipients, new OnCallPagerNotification($booking, $summary, $guidance));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Shared helpers
    |--------------------------------------------------------------------------
    */
    private function createBooking(
        BookingRequest $request,
        ServiceAreaResult $area,
        ?ProtocolSchedule $schedule = null,
        bool $isOnCall = false,
        bool $forceReview = false,
        ?string $reviewReason = null,
    ): Booking {
        $client = $this->client($request);
        $requiresReview = $forceReview || $client->requiresBookingReview();

        // Staff bookings confirm outright (Jeff is doing it himself), except an
        // on-call request, which still awaits an in-app response.
        $confirmedByStaff = $request->byStaff && ! $isOnCall;
        $status = ($requiresReview && ! $confirmedByStaff)
            ? BookingStatus::Provisional
            : BookingStatus::Confirmed;

        if ($status === BookingStatus::Provisional && $reviewReason === null) {
            $reviewReason = 'First-time client — pending review (within 24 hours).';
        }

        return Booking::create([
            'team_id' => $request->team->id,
            'service_id' => $request->service->id,
            'created_by' => $request->actor->id,
            'proposed_start' => $schedule?->visit1At ?? $request->proposedStart,
            'computed_windows' => $schedule !== null ? $this->windowsPayload($schedule) : null,
            'status' => $status->value,
            'requires_review' => $status === BookingStatus::Provisional,
            'review_reason' => $status === BookingStatus::Provisional ? $reviewReason : null,
            'is_oncall' => $isOnCall,
            'distance_fee_flag' => $area->feeApplies,
            'is_cash' => $request->isCash,
            'reviewed_at' => $confirmedByStaff ? now() : null,
            'reviewed_by' => $confirmedByStaff ? $request->actor->id : null,
        ]);
    }

    private function createVisit(BookingRequest $request, Booking $booking, VisitType $type, ?CarbonImmutable $at, ?int $protocolId): Visit
    {
        return Visit::create([
            'team_id' => $request->team->id,
            'protocol_id' => $protocolId,
            'booking_id' => $booking->id,
            'type' => $type->value,
            'status' => VisitStatus::Scheduled->value,
            'scheduled_at' => $at?->setTimezone('UTC'),
        ]);
    }

    private function windowsPayload(ProtocolSchedule $schedule): array
    {
        $display = $schedule->displayTimezone();

        return [
            'visit1_at' => $schedule->visit1At->toIso8601String(),
            'visit2_at' => $schedule->visit2At->toIso8601String(),
            'visit3_window_start' => $schedule->visit3WindowStart->toIso8601String(),
            'visit3_window_end' => $schedule->visit3WindowEnd->toIso8601String(),
            'visit3_recommended_at' => $schedule->visit3RecommendedAt->toIso8601String(),
            'timezone' => $display['visit1_at']->timezoneName,
        ];
    }

    /**
     * @return Collection<int, Cattle>
     */
    private function resolveCattle(BookingRequest $request): Collection
    {
        if ($request->cattleIds === []) {
            throw new BookingValidationException('Select at least one animal for this booking.');
        }

        $cattle = Cattle::query()
            ->where('team_id', $request->team->id)
            ->whereIn('id', $request->cattleIds)
            ->get();

        if ($cattle->count() !== count(array_unique($request->cattleIds))) {
            throw new BookingValidationException('One or more selected animals do not belong to this account.');
        }

        return $cattle;
    }

    private function client(BookingRequest $request)
    {
        $client = $request->team->client;

        if ($client === null) {
            throw new BookingValidationException('This account has no client profile yet — finish onboarding first.');
        }

        return $client;
    }

    private function isSyncPlan(Service $service): bool
    {
        return (int) (data_get($service->price_rule, 'farm_calls', 1)) >= 3;
    }

    private function defaultVisitTime(): CarbonImmutable
    {
        $tz = $this->validator instanceof ScheduleValidator
            ? (string) config('protocol.timezone')
            : 'UTC';

        return CarbonImmutable::now($tz)
            ->addDay()
            ->setTimeFromTimeString((string) config('booking.default_visit_time'));
    }
}
