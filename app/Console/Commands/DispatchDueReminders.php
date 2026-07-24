<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Reminders\ReminderDispatcher;
use Illuminate\Console\Command;

/**
 * The scheduler/queue backbone entrypoint (#244, §5.7). Runs frequently; finds
 * every reminder that has come due, defers non-urgent ones that landed inside
 * quiet hours, and queues a send job for the rest. Idempotent — safe to run on
 * a schedule or by hand.
 */
class DispatchDueReminders extends Command
{
    protected $signature = 'reminders:dispatch';

    protected $description = 'Queue send jobs for all due reminders (deferring non-urgent ones during quiet hours)';

    public function handle(ReminderDispatcher $dispatcher): int
    {
        $report = $dispatcher->dispatchDue();

        $this->info("Reminders dispatched: {$report['dispatched']}, deferred for quiet hours: {$report['deferred']}.");

        return self::SUCCESS;
    }
}
