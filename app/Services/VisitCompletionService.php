<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cattle;
use App\Models\HealthRecord;
use App\Models\SemenInventory;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCompletion;
use App\Observers\VisitCompletionObserver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * VisitCompletionService — the write path for the appointment completion form
 * (#239, §5.5). It records the authoritative `completed_at` (the timestamp V3
 * recomputes from — the {@see VisitCompletionObserver} handles
 * the recompute automatically) and drives every downstream effect in ONE
 * transaction:
 *
 *  - AI: decrement the chosen semen lot by the straws used, and log any wasted
 *    straw SEPARATELY as waste so counts stay honest.
 *  - Supplies: decrement Jeff's working stock from the service usage profile,
 *    with the manual adjustments Jeff made on the form.
 *  - BCS + notes: write per-animal body-condition and general records so the
 *    herd history is captured automatically.
 *
 * Side effects run only when the completion is first created; re-saving to
 * correct the timestamp just re-shifts V3 (via the observer) without
 * double-decrementing inventory or duplicating records.
 */
class VisitCompletionService
{
    public function __construct(
        private SemenCustodyService $custody,
        private SupplyUsageService $supplies,
    ) {}

    /**
     * @param  array{
     *     completed_at: string|\DateTimeInterface,
     *     procedure_confirmed?: bool,
     *     semen_inventory_id?: int|null,
     *     straws_used?: int,
     *     straws_wasted?: int,
     *     supplies_used?: array<int|string, int|float>|null,
     *     mileage?: int|float|null,
     *     notes?: string|null,
     *     animals?: array<int, array{cattle_id: int, bcs_score?: int|null, note?: string|null}>,
     * }  $data
     */
    public function complete(Visit $visit, array $data, User $recordedBy): VisitCompletion
    {
        $completedAt = CarbonImmutable::parse($data['completed_at']);

        return DB::transaction(function () use ($visit, $data, $recordedBy, $completedAt): VisitCompletion {
            $suppliesUsed = $this->resolveSupplies($visit, $data);

            $completion = VisitCompletion::updateOrCreate(
                ['visit_id' => $visit->id],
                [
                    'completed_at' => $completedAt,
                    'procedure_confirmed' => (bool) ($data['procedure_confirmed'] ?? false),
                    'semen_inventory_id' => $data['semen_inventory_id'] ?? null,
                    'straws_used' => (int) ($data['straws_used'] ?? 0),
                    'straws_wasted' => (int) ($data['straws_wasted'] ?? 0),
                    'supplies_used' => $suppliesUsed,
                    'mileage' => $data['mileage'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ],
            );

            // Keep the visit itself in sync — this is the authoritative record
            // of when the farm call actually happened.
            $visit->forceFill([
                'completed_at' => $completedAt,
                'status' => 'completed',
                'mileage' => $data['mileage'] ?? $visit->mileage,
            ])->save();

            if ($completion->wasRecentlyCreated) {
                $this->applySemen($completion, $recordedBy, $completedAt);
                $this->applySupplies($suppliesUsed, $recordedBy);
                $this->writeRecords($visit, $data, $recordedBy, $completedAt);
            }

            return $completion;
        });
    }

    /**
     * The final consumption map to decrement. The form pre-fills the service's
     * usage-profile defaults and lets Jeff adjust, so a submitted map is
     * authoritative (0/removed entries are respected). With nothing submitted,
     * fall back to the service defaults.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, float>
     */
    private function resolveSupplies(Visit $visit, array $data): array
    {
        if (array_key_exists('supplies_used', $data) && is_array($data['supplies_used'])) {
            $map = [];
            foreach ($data['supplies_used'] as $id => $qty) {
                if ((float) $qty > 0) {
                    $map[(int) $id] = (float) $qty;
                }
            }

            return $map;
        }

        $service = $visit->service();

        return $service !== null ? $this->supplies->defaultConsumption($service) : [];
    }

    private function applySemen(VisitCompletion $completion, User $recordedBy, CarbonImmutable $at): void
    {
        if ($completion->semen_inventory_id === null) {
            return;
        }

        $lot = SemenInventory::query()->find($completion->semen_inventory_id);

        if ($lot === null) {
            return;
        }

        if ($completion->straws_used > 0) {
            $this->custody->useStraws($lot, $completion->straws_used, $at, $recordedBy, 'Used at appointment completion.');
        }

        if ($completion->straws_wasted > 0) {
            $this->custody->wasteStraws($lot, $completion->straws_wasted, $at, $recordedBy, 'Wasted/failed straw at appointment.');
        }
    }

    /**
     * @param  array<int, float>  $suppliesUsed
     */
    private function applySupplies(array $suppliesUsed, User $recordedBy): void
    {
        if ($suppliesUsed === []) {
            return;
        }

        $this->supplies->decrement($suppliesUsed, $recordedBy);
    }

    /**
     * Write per-animal records: a body-condition entry for each BCS captured
     * and a general entry for each note. Visit-level notes are copied onto every
     * animal so the completion note is preserved in each herd history (§5.5).
     *
     * @param  array<string, mixed>  $data
     */
    private function writeRecords(Visit $visit, array $data, User $recordedBy, CarbonImmutable $at): void
    {
        $visitNote = trim((string) ($data['notes'] ?? ''));

        foreach (($data['animals'] ?? []) as $animal) {
            $cattle = Cattle::query()->find($animal['cattle_id'] ?? null);

            if ($cattle === null || $cattle->team_id !== $visit->team_id) {
                continue;
            }

            $bcs = $animal['bcs_score'] ?? null;
            if ($bcs !== null && $bcs !== '') {
                $this->record($cattle, $visit, 'body_condition', $recordedBy, $at, (int) $bcs, [
                    'source' => 'completion_form',
                ]);
            }

            $animalNote = trim((string) ($animal['note'] ?? ''));
            $note = trim($animalNote."\n".$visitNote);
            if ($note !== '') {
                $this->record($cattle, $visit, 'general', $recordedBy, $at, null, ['note' => $note]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(Cattle $cattle, Visit $visit, string $type, User $by, CarbonImmutable $at, ?int $bcs, array $payload): void
    {
        HealthRecord::create([
            'team_id' => $cattle->team_id,
            'cattle_id' => $cattle->id,
            'visit_id' => $visit->id,
            'type' => $type,
            'payload' => $payload,
            'bcs_score' => $bcs,
            'recorded_at' => $at,
            'added_by' => $by->id,
            'added_role' => 'staff',
        ]);
    }
}
