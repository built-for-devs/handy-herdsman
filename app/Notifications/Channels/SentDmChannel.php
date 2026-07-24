<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Notifications\Messages\SentDmMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Config-driven SMS channel for sent.dm (spec §5.7). Reads credentials from
 * `services.sentdm`; if no key is configured (local/dev, or before creds are
 * provisioned) it gracefully LOGS the message instead of erroring, so the
 * public informal-proposal send (§5.4) never blows up on a missing key.
 */
class SentDmChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSentDm')) {
            return;
        }

        $to = $notifiable->routeNotificationFor('sentdm', $notification);

        if (empty($to)) {
            return;
        }

        /** @var SentDmMessage $message */
        $message = $notification->toSentDm($notifiable);

        $key = config('services.sentdm.key');

        if (empty($key)) {
            Log::info('sent.dm not configured; SMS queued/logged instead of sent.', [
                'to' => $to,
                'body' => $message->content,
            ]);

            return;
        }

        Http::withToken($key)
            ->acceptJson()
            ->post((string) config('services.sentdm.endpoint'), array_filter([
                'to' => $to,
                'from' => config('services.sentdm.from'),
                'text' => $message->content,
            ]));
    }
}
