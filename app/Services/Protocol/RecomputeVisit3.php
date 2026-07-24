<?php

declare(strict_types=1);

namespace App\Services\Protocol;

use App\Enums\AnimalType;
use App\Events\Visit3Recomputed;
use App\Models\Protocol;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Recomputes and persists a protocol's Visit 3 window from Visit 2's ACTUAL
 * completed timestamp, flags any resulting conflict, and notifies (1.3, §10b).
 *
 * Invoked automatically when a Visit 2 is completed (see VisitCompletionObserver)
 * — the common real-world case where V2 runs late and V3 must shift with it.
 */
class RecomputeVisit3
{
    public function __construct(
        private ProtocolTimingService $timing,
        private Visit3ConflictDetector $conflicts,
    ) {}

    public function handle(Protocol $protocol, CarbonInterface $visit2CompletedAt): Protocol
    {
        $animalType = AnimalType::from($protocol->animal_type);

        $window = $this->timing->recomputeVisit3($visit2CompletedAt, $animalType);
        $reason = $this->conflicts->conflictReason($window['recommended']);

        $protocol->forceFill([
            'visit2_at' => CarbonImmutable::instance($visit2CompletedAt)->setTimezone('UTC'),
            'visit3_window_start' => $window['start'],
            'visit3_window_end' => $window['end'],
            'visit3_recommended_at' => $window['recommended'],
            'visit3_conflict' => $reason !== null,
            'visit3_conflict_reason' => $reason,
        ])->save();

        Visit3Recomputed::dispatch($protocol, $reason);

        return $protocol;
    }
}
