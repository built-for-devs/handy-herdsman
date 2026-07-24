<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ClientStatus;
use App\Models\Client;
use Illuminate\Console\Command;

/**
 * Reverts active clients to `inactive` once idle past the config threshold
 * (default 1 year). Inactive clients require staff review on their next
 * booking (spec §10b — Client status). Run daily by the scheduler.
 */
class MarkIdleClientsInactive extends Command
{
    protected $signature = 'clients:mark-idle-inactive';

    protected $description = 'Revert clients idle past the threshold to inactive (spec §10b)';

    public function handle(): int
    {
        $count = 0;

        Client::query()
            ->where('status', ClientStatus::Active->value)
            ->whereNotNull('last_activity_at')
            ->each(function (Client $client) use (&$count) {
                if ($client->markInactiveIfIdle()) {
                    $count++;
                }
            });

        $this->info("Marked {$count} client(s) inactive.");

        return self::SUCCESS;
    }
}
