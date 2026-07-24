<?php

namespace App\Services;

use App\Models\Supply;
use App\Models\User;
use App\Notifications\LowSupplyStockNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Finds supplies at/below their reorder threshold and alerts staff (§5.6b).
 * Thresholds are DB-driven (per-supply); recipients are the staff role.
 */
class SupplyStockAlertService
{
    /**
     * All supplies currently at or below their reorder threshold.
     *
     * @return Collection<int, Supply>
     */
    public function lowStock(): Collection
    {
        return Supply::query()->lowStock()->orderBy('item')->get();
    }

    /**
     * Alert staff about the given low-stock supplies. No-op when the list is
     * empty. Returns the collection that was alerted on.
     *
     * @param  Collection<int, Supply>  $supplies
     * @return Collection<int, Supply>
     */
    public function alert(Collection $supplies): Collection
    {
        if ($supplies->isEmpty()) {
            return $supplies;
        }

        $recipients = $this->staffRecipients();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new LowSupplyStockNotification($supplies));
        }

        return $supplies;
    }

    /**
     * Scan all supplies and alert on any that are low. Convenience entry point
     * for the scheduled/console check.
     *
     * @return Collection<int, Supply>
     */
    public function scanAndAlert(): Collection
    {
        return $this->alert($this->lowStock());
    }

    /**
     * Staff users to notify. Queried straight off the role pivot so tenant
     * (team) scoping never hides a staff member from a reorder alert.
     *
     * @return Collection<int, User>
     */
    public function staffRecipients(): Collection
    {
        $modelHasRoles = config('permission.table_names.model_has_roles');
        $roles = config('permission.table_names.roles');

        $ids = DB::table($modelHasRoles)
            ->join($roles, "$roles.id", '=', "$modelHasRoles.role_id")
            ->where("$roles.name", 'staff')
            ->pluck("$modelHasRoles.model_id");

        return User::query()->whereIn('id', $ids)->get();
    }
}
