<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Jobs\SendReminder;
use App\Models\Reminder;
use App\Support\QuietHours;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The scheduler/queue backbone (#244, §5.7). A daily/hourly command calls
 * {@see dispatchDue()}, which finds every reminder that has come due and either:
 *
 *  - defers it, if it is a non-urgent category that landed inside quiet hours
 *    (~8am-9pm) — the `quiet_hours_deferred_to` is set to the next allowed
 *    window so the 2-days-out calving reminder never fires at 4am; or
 *  - claims it (`pending` → `queued`) and pushes a {@see SendReminder} job.
 *
 * On-call / emergency categories (`bypasses_quiet_hours`) send at any hour.
 * The atomic status claim makes the whole thing idempotent: re-running never
 * double-dispatches, because a queued reminder is no longer `pending`.
 */
class ReminderDispatcher
{
    public function __construct(private QuietHours $quietHours) {}

    /**
     * Process all due reminders. Returns a small report for the command output.
     *
     * @return array{dispatched: int, deferred: int}
     */
    public function dispatchDue(): array
    {
        $now = CarbonImmutable::now();
        $dispatched = 0;
        $deferred = 0;

        Reminder::query()
            ->due($now)
            ->orderBy('id')
            ->each(function (Reminder $reminder) use ($now, &$dispatched, &$deferred): void {
                if ($this->deferForQuietHours($reminder, $now)) {
                    $deferred++;

                    return;
                }

                if ($this->claim($reminder)) {
                    SendReminder::dispatch($reminder->id);
                    $dispatched++;
                }
            });

        return ['dispatched' => $dispatched, 'deferred' => $deferred];
    }

    /**
     * Defer a non-urgent reminder that came due inside quiet hours to the next
     * allowed window. Returns whether it was deferred. Categories that bypass
     * quiet hours (emergencies) are never deferred.
     */
    private function deferForQuietHours(Reminder $reminder, CarbonImmutable $now): bool
    {
        $bypasses = (bool) config("reminders.categories.{$reminder->category}.bypasses_quiet_hours", false);

        if ($bypasses || ! $this->quietHours->isWithinQuietHours($now)) {
            return false;
        }

        $reminder->forceFill([
            'quiet_hours_deferred_to' => $this->quietHours->nextAllowedTime($now),
        ])->save();

        return true;
    }

    /** Atomically claim a still-pending reminder so no other run can grab it. */
    private function claim(Reminder $reminder): bool
    {
        return DB::table('reminders')
            ->where('id', $reminder->id)
            ->where('status', Reminder::STATUS_PENDING)
            ->update(['status' => Reminder::STATUS_QUEUED, 'updated_at' => CarbonImmutable::now()]) === 1;
    }
}
