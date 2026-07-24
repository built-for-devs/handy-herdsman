<?php

declare(strict_types=1);

namespace App\Events;

use App\Listeners\CancelPendingRemindersForCattle;
use App\Models\Cattle;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a cow is marked `inactive` (sold/deceased/culled/out of program).
 *
 * This is the clean hook the reminders subsystem (M9) plugs into: marking an
 * animal inactive must STOP all pending reminders for her immediately (§10b —
 * Cattle status). The listener {@see CancelPendingRemindersForCattle}
 * cancels any reminder rows that already exist; future reminder generation
 * checks the animal's status before scheduling.
 */
class CattleDeactivated
{
    use Dispatchable;

    public function __construct(public readonly Cattle $cattle) {}
}
