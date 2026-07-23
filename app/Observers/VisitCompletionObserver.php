<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\VisitCompletion;
use App\Services\Protocol\RecomputeVisit3;

/**
 * When a Visit 2 is completed, Visit 3 must be recomputed from the actual
 * completed timestamp — automatically (§10b — Timing math, 1.3). This observer
 * is the automatic trigger: the completion form (M7) just saves the record.
 */
class VisitCompletionObserver
{
    public function __construct(private RecomputeVisit3 $recompute) {}

    public function created(VisitCompletion $completion): void
    {
        $this->maybeRecompute($completion);
    }

    public function updated(VisitCompletion $completion): void
    {
        // A corrected completed_at (Jeff's manual override) must re-shift V3.
        if ($completion->wasChanged('completed_at')) {
            $this->maybeRecompute($completion);
        }
    }

    private function maybeRecompute(VisitCompletion $completion): void
    {
        $visit = $completion->visit;

        if ($visit === null || $visit->type !== 'v2' || $visit->protocol_id === null) {
            return;
        }

        $protocol = $visit->protocol;

        if ($protocol === null || $completion->completed_at === null) {
            return;
        }

        $this->recompute->handle($protocol, $completion->completed_at);
    }
}
