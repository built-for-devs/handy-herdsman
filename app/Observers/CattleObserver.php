<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\CattleStatus;
use App\Events\CattleDeactivated;
use App\Models\Cattle;

/**
 * Watches for an animal transitioning to `inactive` and fires
 * {@see CattleDeactivated} so pending reminders are cancelled immediately
 * (§10b — Cattle status). Centralising on the model event means every code
 * path that deactivates a cow — CRUD, bulk update, `deactivate()` — triggers
 * the hook, so no caller can forget it.
 */
class CattleObserver
{
    public function updated(Cattle $cattle): void
    {
        if (! $cattle->wasChanged('status')) {
            return;
        }

        if ($cattle->status === CattleStatus::Inactive) {
            CattleDeactivated::dispatch($cattle);
        }
    }
}
