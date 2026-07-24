<?php

declare(strict_types=1);

namespace App\Actions\Calculators;

use App\Notifications\AiTimingResultNotification;
use App\Support\Calculators\AiTimingResult;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * Sends the AI-timing result to a visitor via email and/or SMS (spec §5.4,
 * issue 2.5). Uses an on-demand notification so no account/model is required
 * for a public lead — the notification is queued and each channel degrades
 * gracefully when its provider is unconfigured.
 */
class SendAiTimingResult
{
    public function handle(AiTimingResult $result, ?string $email, ?string $phone): void
    {
        $notifiable = new AnonymousNotifiable;

        if ($email !== null && $email !== '') {
            $notifiable->route('mail', $email);
        }

        if ($phone !== null && $phone !== '') {
            $notifiable->route('sentdm', $phone);
        }

        $notifiable->notify(new AiTimingResultNotification($result));
    }
}
