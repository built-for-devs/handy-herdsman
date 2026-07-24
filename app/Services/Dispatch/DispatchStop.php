<?php

declare(strict_types=1);

namespace App\Services\Dispatch;

/**
 * One stop on Jeff's day route — a scheduled visit plus the client's cached
 * service-area outcome (spec §5.10). Distance flags come from the already
 * geocoded/cached client distance; the dispatch view NEVER triggers a new
 * geocoding call. Ordering across stops is purely by scheduled time — this is
 * a day list, NOT a route-optimization engine (§5.10).
 */
final readonly class DispatchStop
{
    public function __construct(
        public int $visitId,
        public string $type,
        public string $status,
        public ?string $scheduledAt,
        public ?string $teamName,
        public ?string $clientName,
        public ?string $address,
        public ?float $lat,
        public ?float $lng,
        public ?float $distanceMiles,
        public bool $inRange,
        public bool $feeApplies,
        public float $feeAmount,
        public bool $declined,
    ) {}

    /**
     * Beyond the standard no-fee servicing range: either a declined zone or a
     * client far enough out that the distance fee kicks in (spec §2, §5.10).
     */
    public function outOfRange(): bool
    {
        return $this->declined || $this->feeApplies;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'visit_id' => $this->visitId,
            'type' => $this->type,
            'status' => $this->status,
            'scheduled_at' => $this->scheduledAt,
            'team_name' => $this->teamName,
            'client_name' => $this->clientName,
            'address' => $this->address,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'distance_miles' => $this->distanceMiles,
            'in_range' => $this->inRange,
            'fee_applies' => $this->feeApplies,
            'fee_amount' => $this->feeAmount,
            'declined' => $this->declined,
            'out_of_range' => $this->outOfRange(),
        ];
    }
}
