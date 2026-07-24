<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\CattleStatus;
use App\Events\CattleCalved;
use App\Events\CattleDeactivated;
use App\Models\Cattle;
use Carbon\CarbonImmutable;

/**
 * Watches for two animal transitions and fires the matching event:
 *
 *  - → `inactive`: {@see CattleDeactivated}, so pending reminders are cancelled
 *    immediately (§10b — Cattle status).
 *  - first calving (`has_calved` false → true): {@see CattleCalved}, the seam
 *    the reminder engine uses to start the post-calving rebreed loop (§5.7).
 *
 * Centralising on the model event means every code path — CRUD, bulk update,
 * `deactivate()`, `recordCalving()` — triggers the hook, so no caller forgets.
 */
class CattleObserver
{
    public function updated(Cattle $cattle): void
    {
        if ($cattle->wasChanged('status') && $cattle->status === CattleStatus::Inactive) {
            CattleDeactivated::dispatch($cattle);
        }

        if ($cattle->wasChanged('has_calved') && $cattle->has_calved === true) {
            CattleCalved::dispatch($cattle, CarbonImmutable::now());
        }
    }
}
