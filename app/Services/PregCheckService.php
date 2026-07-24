<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AnimalType;
use App\Enums\PregCheckMethod;
use App\Enums\PregCheckState;
use App\Events\PregCheckResulted;
use App\Models\Cattle;
use App\Models\PregCheck;
use App\Models\RateConfig;
use App\Models\Reminder;
use App\Models\User;
use App\Models\Visit;
use App\Services\Gestation\GestationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * PregCheckService — the write path for the async preg-check flow (#241, §10b).
 *
 * Blood is a two-stage event: Jeff draws at the visit (state `pending`, optional
 * +$15 lab confirmation charged ONLY if the client opts in), then records the
 * definitive lab result later. Palpation (after ~4 months) is immediate and
 * definitive — no pending state, no lab fee.
 *
 * Downstream nurture fires ONLY on a FINAL result, never on `pending`:
 *   bred    → store the due date (GestationService) + schedule calving countdown
 *   open    → rebreed prompt ("don't lose the season")
 *   recheck → schedule another check
 * Clients never record results — only staff.
 */
class PregCheckService
{
    public function __construct(private GestationService $gestation) {}

    /** The optional lab-confirmation fee, config-driven (§10b). */
    public function labFee(): float
    {
        return (float) (RateConfig::value('preg_check_lab_confirmation', [])['price'] ?? 0);
    }

    /**
     * The fee owed for a given check: the lab fee only when it is a blood draw
     * the client opted into confirming. Palpation and un-opted blood owe $0.
     */
    public function feeFor(PregCheck $check): float
    {
        return $check->method === PregCheckMethod::Blood && $check->lab_requested
            ? $this->labFee()
            : 0.0;
    }

    /**
     * Record a preg check performed at a visit.
     *
     * Blood defaults to `pending` (awaiting the lab). Palpation is immediate:
     * an `$immediateState` (open|bred|recheck) is REQUIRED and nurture fires now.
     * `lab_requested` is ignored for palpation (no lab fee).
     */
    public function record(
        Cattle $cattle,
        PregCheckMethod $method,
        User $recordedBy,
        ?Visit $visit = null,
        bool $labRequested = false,
        ?PregCheckState $immediateState = null,
    ): PregCheck {
        return DB::transaction(function () use ($cattle, $method, $recordedBy, $visit, $labRequested, $immediateState): PregCheck {
            if ($method->isImmediate()) {
                if ($immediateState === null || ! $immediateState->isFinal()) {
                    throw new InvalidArgumentException('Palpation is definitive at the visit — a final result is required.');
                }

                $check = PregCheck::create([
                    'team_id' => $cattle->team_id,
                    'cattle_id' => $cattle->id,
                    'visit_id' => $visit?->id,
                    'method' => $method,
                    'lab_requested' => false, // no lab fee on palpation
                    'state' => $immediateState,
                    'result_recorded_at' => now(),
                    'recorded_by' => $recordedBy->id,
                ]);

                $this->fireNurture($check);

                return $check;
            }

            // Blood draw — pending until the lab reports back. No nurture yet.
            return PregCheck::create([
                'team_id' => $cattle->team_id,
                'cattle_id' => $cattle->id,
                'visit_id' => $visit?->id,
                'method' => $method,
                'lab_requested' => $labRequested,
                'state' => PregCheckState::Pending,
            ]);
        });
    }

    /**
     * Record the definitive lab result for a pending blood check. Only staff
     * call this; clients never enter results (§10b). Fires downstream nurture.
     */
    public function recordResult(PregCheck $check, PregCheckState $finalState, User $recordedBy): PregCheck
    {
        if (! $finalState->isFinal()) {
            throw new InvalidArgumentException('A recorded result must be a final state (open, bred, or recheck).');
        }

        return DB::transaction(function () use ($check, $finalState, $recordedBy): PregCheck {
            $check->forceFill([
                'state' => $finalState,
                'result_recorded_at' => now(),
                'recorded_by' => $recordedBy->id,
            ])->save();

            $this->fireNurture($check);

            return $check;
        });
    }

    /**
     * Schedule the nurture that a FINAL result triggers, and announce the event
     * for M9. A no-op on `pending` (defensive — callers never pass pending).
     */
    private function fireNurture(PregCheck $check): void
    {
        if ($check->isPending()) {
            return;
        }

        match ($check->state) {
            PregCheckState::Bred => $this->scheduleCalvingCountdown($check),
            PregCheckState::Open => $this->scheduleRebreedPrompt($check),
            PregCheckState::Recheck => $this->scheduleRecheck($check),
            default => null,
        };

        PregCheckResulted::dispatch($check);
    }

    /**
     * `bred` → compute + store the due date (GestationService) and schedule a
     * calving-countdown reminder at each gestation milestone (§5.4b / §5.7).
     */
    private function scheduleCalvingCountdown(PregCheck $check): void
    {
        $cattle = $check->cattle;

        if ($cattle === null) {
            return;
        }

        $animalType = $cattle->animal_type instanceof AnimalType ? $cattle->animal_type : null;
        $estimate = $this->gestation->estimate($this->breedingDate($check), $cattle->breed, $animalType);

        $cattle->forceFill(['due_date' => $estimate->estimatedDueDate->toDateString()])->save();

        $cfg = (array) config('reminders.nurture.calving_countdown');
        $now = CarbonImmutable::now();

        foreach ($estimate->milestones as $fireAt) {
            // Skip milestones already in the past (a late-recorded pregnancy).
            if ($fireAt->lessThan($now)) {
                continue;
            }

            $this->schedule($check, (int) $cfg['category'], (string) $cfg['template'], $fireAt);
        }
    }

    /** `open` → rebreed prompt: don't lose the breeding season (§5.7). */
    private function scheduleRebreedPrompt(PregCheck $check): void
    {
        $cfg = (array) config('reminders.nurture.rebreed_prompt');

        $this->schedule(
            $check,
            (int) $cfg['category'],
            (string) $cfg['template'],
            CarbonImmutable::now()->addDays((int) ($cfg['after_days'] ?? 0)),
        );
    }

    /** `recheck` → schedule another pregnancy check (§5.7). */
    private function scheduleRecheck(PregCheck $check): void
    {
        $cfg = (array) config('reminders.nurture.preg_recheck');

        $this->schedule(
            $check,
            (int) $cfg['category'],
            (string) $cfg['template'],
            CarbonImmutable::now()->addDays((int) ($cfg['after_days'] ?? 0)),
        );
    }

    private function schedule(PregCheck $check, int $category, string $template, CarbonImmutable $fireAt): void
    {
        Reminder::create([
            'team_id' => $check->team_id,
            'remindable_type' => Cattle::class,
            'remindable_id' => $check->cattle_id,
            'fire_at' => $fireAt,
            'category' => $category,
            'template' => $template,
            'recipient_role' => 'owner',
            'status' => 'pending',
        ]);
    }

    /**
     * The breeding date the due date is computed from: the animal's most recent
     * completed breeding-related visit. Falls back to the check's result date
     * when no breeding visit is on record (defensive — noted in the PR).
     */
    private function breedingDate(PregCheck $check): CarbonImmutable
    {
        $cattleId = $check->cattle_id;

        $visit = Visit::query()
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->where(fn ($q) => $q
                ->where('cattle_id', $cattleId)
                ->orWhereHas('booking', fn ($b) => $b->whereHas('cattle', fn ($c) => $c->where('cattle.id', $cattleId))))
            ->orderByDesc('completed_at')
            ->get()
            ->first(fn (Visit $v) => $v->isBreedingRelated());

        if ($visit?->completed_at !== null) {
            return CarbonImmutable::parse($visit->completed_at);
        }

        return CarbonImmutable::parse($check->result_recorded_at ?? now());
    }
}
