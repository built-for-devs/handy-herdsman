<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Cattle;
use App\Models\PregCheck;
use App\Models\Reminder;
use App\Notifications\ReminderNotification;
use App\Support\ReminderChannelResolver;
use App\Support\TeamNotifiables;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

/**
 * Sends a single reminder (#244/#245). The dispatcher claims a reminder
 * (`pending` → `queued`) before pushing this job, so re-running the scheduler
 * never double-sends. This job:
 *
 *  - re-checks the claim (defensive against a stale/duplicate dispatch),
 *  - honours late suppression (a +60d "book a preg check" is cancelled if the
 *    client has since booked one — see `suppress_if_preg_check_after`),
 *  - resolves recipients (owner always; opted-in members; vets never — §10b),
 *  - resolves the channel per-category from client prefs/override/consent,
 *  - marks the reminder `sent`.
 */
class SendReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $reminderId) {}

    public function handle(ReminderChannelResolver $channels): void
    {
        $reminder = Reminder::query()->find($this->reminderId);

        // Only a claimed reminder is ours to send — anything else was already
        // handled, cancelled, or superseded.
        if ($reminder === null || $reminder->status !== Reminder::STATUS_QUEUED) {
            return;
        }

        if ($this->shouldSuppress($reminder)) {
            $reminder->forceFill(['status' => Reminder::STATUS_CANCELLED])->save();

            return;
        }

        $channel = $channels->forReminder($reminder);
        $recipients = TeamNotifiables::recipientsForCategory($reminder->team, $reminder->category);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ReminderNotification($reminder, $channel));
        }

        $reminder->forceFill([
            'status' => Reminder::STATUS_SENT,
            'channel' => $channel->value,
            'sent_at' => CarbonImmutable::now(),
        ])->save();
    }

    /**
     * A transactional follow-up is suppressed once its purpose is moot — e.g. a
     * "still need a preg check?" nudge when a check has since been recorded for
     * the animal after the insemination it was scheduled from.
     */
    private function shouldSuppress(Reminder $reminder): bool
    {
        $after = data_get($reminder->payload, 'suppress_if_preg_check_after');

        if ($after === null || $reminder->remindable_type !== Cattle::class) {
            return false;
        }

        return PregCheck::query()
            ->where('cattle_id', $reminder->remindable_id)
            ->where('created_at', '>=', CarbonImmutable::parse($after))
            ->exists();
    }
}
