<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Reminders\NurtureService;
use Illuminate\Console\Command;

/**
 * The daily data-driven nurture sweep (#247, §5.7). Scans logged records for
 * nudge conditions — due-date-passed care checks, semen storage renewals,
 * unused straws, dormant clients — and schedules the reminders. Idempotent via
 * `dedupe_key`, so running it every day never double-schedules.
 */
class RunNurtureSweep extends Command
{
    protected $signature = 'reminders:nurture-sweep';

    protected $description = 'Scan logged records and schedule data-driven nurture reminders';

    public function handle(NurtureService $nurture): int
    {
        $nurture->sweep();

        $this->info('Nurture sweep complete.');

        return self::SUCCESS;
    }
}
