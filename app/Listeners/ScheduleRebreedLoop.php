<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CattleCalved;
use App\Services\Reminders\NurtureService;

/**
 * Starts the post-calving rebreed loop when a cow calves (§5.7 — the core
 * recurring-revenue loop).
 */
class ScheduleRebreedLoop
{
    public function __construct(private NurtureService $nurture) {}

    public function handle(CattleCalved $event): void
    {
        $this->nurture->onCalving($event->cattle, $event->calvedAt);
    }
}
