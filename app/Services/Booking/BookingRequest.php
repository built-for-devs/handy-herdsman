<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Enums\OnCallKind;
use App\Models\Service;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The immutable input to {@see BookingService::book()} (spec §6.1). Carries who
 * is booking (a client or global staff), for which team, which service and
 * animals, and the chosen time. On-call requests additionally carry the kind
 * (standing heat / calving emergency) and when the heat was observed.
 */
final readonly class BookingRequest
{
    /**
     * @param  list<int>  $cattleIds
     */
    public function __construct(
        public Team $team,
        public Service $service,
        public array $cattleIds,
        public User $actor,
        public bool $byStaff = false,
        public ?CarbonImmutable $proposedStart = null,
        public ?OnCallKind $onCallKind = null,
        public ?CarbonImmutable $heatObservedAt = null,
        public bool $isCash = false,
        public ?string $paymentMethodId = null,
    ) {}

    public static function make(
        Team $team,
        Service $service,
        array $cattleIds,
        User $actor,
        bool $byStaff = false,
        ?CarbonInterface $proposedStart = null,
        ?OnCallKind $onCallKind = null,
        ?CarbonInterface $heatObservedAt = null,
        bool $isCash = false,
        ?string $paymentMethodId = null,
    ): self {
        return new self(
            team: $team,
            service: $service,
            cattleIds: array_values(array_map('intval', $cattleIds)),
            actor: $actor,
            byStaff: $byStaff,
            proposedStart: $proposedStart !== null ? CarbonImmutable::instance($proposedStart) : null,
            onCallKind: $onCallKind,
            heatObservedAt: $heatObservedAt !== null ? CarbonImmutable::instance($heatObservedAt) : null,
            isCash: $isCash,
            paymentMethodId: $paymentMethodId,
        );
    }
}
