<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\VisitCompletion;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a visit's completion form is first recorded (§5.5). The reminder
 * engine listens here to schedule the around-a-breeding follow-ups and the
 * body-condition nutrition nudge from the freshly-written records (§5.7).
 */
class VisitCompleted
{
    use Dispatchable;

    public function __construct(public readonly VisitCompletion $completion) {}
}
