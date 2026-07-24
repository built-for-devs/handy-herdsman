<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CattleDeactivated;
use App\Models\Reminder;

/**
 * Cancels every pending reminder tied to a deactivated animal (§10b — "marking
 * a cow inactive STOPS all pending reminders for that animal immediately.
 * Nothing should keep texting about a cow that's gone."). Already-sent
 * reminders are left untouched — only `pending` rows are flagged `cancelled`.
 */
class CancelPendingRemindersForCattle
{
    public function handle(CattleDeactivated $event): void
    {
        $event->cattle->reminders()
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }
}
