<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Enums\CattleStatus;
use App\Enums\ClientStatus;
use App\Models\Cattle;
use App\Models\Client;
use App\Models\HealthRecord;
use App\Models\SemenInventory;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The transactional + lifecycle nurture triggers (#246/#247, §5.7). Each public
 * method reacts to a domain event and schedules the appropriate config-driven
 * reminders through {@see ReminderScheduler} (which enforces idempotency and
 * skips inactive animals). Offsets, categories and templates are all data.
 */
class NurtureService
{
    public function __construct(private ReminderScheduler $scheduler) {}

    /**
     * Around a breeding (§5.7). For each animal on a completed breeding visit,
     * schedule the return-to-heat / book-a-preg-check follow-ups from the
     * insemination date. The +60d follow-up carries the insemination date so it
     * self-suppresses if a preg check gets booked in the meantime.
     */
    public function onBreedingVisitCompleted(Visit $visit, CarbonInterface $inseminatedAt): void
    {
        if (! $visit->isBreedingRelated()) {
            return;
        }

        $inseminatedAt = CarbonImmutable::instance($inseminatedAt);

        foreach ($visit->animals() as $cattle) {
            foreach ((array) config('reminders.transactional.breeding') as $step) {
                $payload = [];

                if (! empty($step['suppress_if_preg_check'])) {
                    $payload['suppress_if_preg_check_after'] = $inseminatedAt->toIso8601String();
                }

                $this->scheduler->schedule(
                    $cattle,
                    (int) $step['category'],
                    (string) $step['template'],
                    $inseminatedAt->addDays((int) $step['offset_days']),
                    $this->key('breeding', (string) $step['template'], $cattle->id, $inseminatedAt),
                    $payload,
                );
            }
        }
    }

    /**
     * Post-calving rebreed loop (§5.7 — the core recurring-revenue loop). Plans
     * the rebreed timeline (+30d), the direct rebreed-booking prompt (+45-60d),
     * and a referral ask, all counted from the calving date.
     */
    public function onCalving(Cattle $cattle, CarbonInterface $calvedAt): void
    {
        $calvedAt = CarbonImmutable::instance($calvedAt);

        foreach ((array) config('reminders.lifecycle.calving') as $step) {
            $this->scheduler->schedule(
                $cattle,
                (int) $step['category'],
                (string) $step['template'],
                $calvedAt->addDays((int) $step['offset_days']),
                $this->key('calving', (string) $step['template'], $cattle->id, $calvedAt),
            );
        }
    }

    /**
     * Body condition out of the target range at the last visit → nutrition
     * follow-up (§5.5, §5.7 — "the #1 reason AI fails"). Reads the BCS the
     * completion form just wrote for each animal on the visit.
     */
    public function onVisitCompleted(Visit $visit, CarbonInterface $completedAt): void
    {
        $cfg = (array) config('reminders.data_driven.bcs');
        $min = (int) $cfg['target_min'];
        $max = (int) $cfg['target_max'];
        $completedAt = CarbonImmutable::instance($completedAt);

        foreach ($visit->animals() as $cattle) {
            $bcs = $this->latestBcsForVisit($visit->id, $cattle->id);

            if ($bcs === null || ($bcs >= $min && $bcs <= $max)) {
                continue;
            }

            $this->scheduler->schedule(
                $cattle,
                (int) $cfg['category'],
                (string) $cfg['template'],
                $completedAt->addDays((int) $cfg['after_days']),
                $this->key('bcs', (string) $cfg['template'], $cattle->id, $visit->id),
            );
        }
    }

    /**
     * The daily data-driven sweep (§5.7 — cat.3/4 nudges): due-date-passed care
     * checks, semen storage renewals, unused straws, and dormant clients.
     * Idempotent via `dedupe_key`, so it is safe to run every day.
     */
    public function sweep(?CarbonInterface $now = null): void
    {
        $now = CarbonImmutable::instance($now ?? CarbonImmutable::now());

        $this->sweepCalvingCareChecks($now);
        $this->sweepStorageRenewals($now);
        $this->sweepUnusedStraws($now);
        $this->sweepDormantClients($now);
    }

    private function sweepCalvingCareChecks(CarbonImmutable $now): void
    {
        $cfg = (array) config('reminders.lifecycle.calving_care_check');

        Cattle::query()
            ->where('status', CattleStatus::Active)
            ->where('has_calved', false)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $now->toDateString())
            ->each(function (Cattle $cattle) use ($cfg, $now): void {
                $due = CarbonImmutable::parse((string) $cattle->due_date);

                $this->scheduler->schedule(
                    $cattle,
                    (int) $cfg['category'],
                    (string) $cfg['template'],
                    $now,
                    $this->key('calving_care_check', (string) $cfg['template'], $cattle->id, $due),
                );
            });
    }

    private function sweepStorageRenewals(CarbonImmutable $now): void
    {
        $cfg = (array) config('reminders.data_driven.storage_renewal');
        $window = $now->addDays((int) $cfg['lead_days']);

        SemenInventory::query()
            ->whereNotNull('storage_free_until')
            ->whereDate('storage_free_until', '>=', $now->toDateString())
            ->whereDate('storage_free_until', '<=', $window->toDateString())
            ->each(function (SemenInventory $lot) use ($cfg, $now): void {
                $this->scheduler->schedule(
                    $lot,
                    (int) $cfg['category'],
                    (string) $cfg['template'],
                    $now,
                    $this->key('storage_renewal', (string) $cfg['template'], $lot->id, (string) $lot->storage_free_until),
                );
            });
    }

    private function sweepUnusedStraws(CarbonImmutable $now): void
    {
        $cfg = (array) config('reminders.data_driven.straws_unused');
        $cutoff = $now->subMonths((int) $cfg['after_months']);

        SemenInventory::query()
            ->where('straws_count', '>', 0)
            ->whereNotNull('storage_start')
            ->whereDate('storage_start', '<=', $cutoff->toDateString())
            ->each(function (SemenInventory $lot) use ($cfg, $now): void {
                $this->scheduler->schedule(
                    $lot,
                    (int) $cfg['category'],
                    (string) $cfg['template'],
                    $now,
                    $this->key('straws_unused', (string) $cfg['template'], $lot->id, $now->format('Y-m')),
                );
            });
    }

    private function sweepDormantClients(CarbonImmutable $now): void
    {
        $cfg = (array) config('reminders.data_driven.dormant');
        $cutoff = $now->subMonths((int) $cfg['after_months']);

        Client::query()
            ->where('status', ClientStatus::Active)
            ->whereNotNull('last_activity_at')
            ->where('last_activity_at', '<=', $cutoff)
            ->each(function (Client $client) use ($cfg, $now): void {
                $this->scheduler->schedule(
                    $client,
                    (int) $cfg['category'],
                    (string) $cfg['template'],
                    $now,
                    $this->key('dormant', (string) $cfg['template'], $client->id, $now->format('Y-m')),
                );
            });
    }

    private function latestBcsForVisit(int $visitId, int $cattleId): ?int
    {
        $score = HealthRecord::query()
            ->where('visit_id', $visitId)
            ->where('cattle_id', $cattleId)
            ->where('type', 'body_condition')
            ->whereNotNull('bcs_score')
            ->latest('recorded_at')
            ->value('bcs_score');

        return $score === null ? null : (int) $score;
    }

    private function key(string $trigger, string $template, int $id, CarbonInterface|int|string $discriminator): string
    {
        $suffix = $discriminator instanceof CarbonInterface
            ? $discriminator->toDateString()
            : (string) $discriminator;

        return "{$trigger}:{$template}:{$id}:{$suffix}";
    }
}
