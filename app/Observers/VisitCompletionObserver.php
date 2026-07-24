<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\VisitCompletion;

/**
 * Auto-promotes a client to `active` after their first completed visit
 * (spec §10b — Client status). A completed visit is the authoritative signal
 * that Jeff has worked with them, so subsequent bookings self-confirm.
 */
class VisitCompletionObserver
{
    public function created(VisitCompletion $completion): void
    {
        $client = $completion->visit?->team?->client;

        $client?->recordCompletedVisit($completion->completed_at);
    }
}
