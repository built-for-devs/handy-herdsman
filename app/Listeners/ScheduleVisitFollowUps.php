<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\VisitCompleted;
use App\Services\Reminders\NurtureService;

/**
 * Schedules the reminders that a completed visit triggers (§5.7): the
 * around-a-breeding transactional follow-ups (#246) and the body-condition
 * nutrition nudge when BCS is out of range (#247). Both are idempotent, so
 * a corrected/re-saved completion never double-schedules.
 */
class ScheduleVisitFollowUps
{
    public function __construct(private NurtureService $nurture) {}

    public function handle(VisitCompleted $event): void
    {
        $visit = $event->completion->visit;
        $completedAt = $event->completion->completed_at;

        if ($visit === null || $completedAt === null) {
            return;
        }

        $this->nurture->onBreedingVisitCompleted($visit, $completedAt);
        $this->nurture->onVisitCompleted($visit, $completedAt);
    }
}
