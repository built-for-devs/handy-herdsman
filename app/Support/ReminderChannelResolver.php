<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\MessageChannel;
use App\Models\Client;
use App\Models\Reminder;
use App\Models\Team;

/**
 * Resolves the delivery channel for a reminder (§5.7 / #245). The client's
 * per-category channel preference and the staff override live on the Client
 * record; SMS additionally requires a timestamped per-category consent. All of
 * that logic already lives on {@see Client::resolvedChannel()} —
 * this wrapper picks the right client for a reminder and applies a safe default
 * (email, which needs no separate consent) when there is no client on record.
 */
final class ReminderChannelResolver
{
    public function forReminder(Reminder $reminder): MessageChannel
    {
        return $this->forTeam($reminder->team, $reminder->category);
    }

    public function forTeam(?Team $team, int $category): MessageChannel
    {
        $client = $team?->client;

        if ($client === null) {
            return MessageChannel::Email;
        }

        return $client->resolvedChannel($category);
    }
}
