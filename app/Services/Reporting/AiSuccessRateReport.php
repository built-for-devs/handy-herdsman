<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\AnimalType;
use App\Enums\PregCheckState;
use App\Models\HealthRecord;
use App\Models\PregCheck;
use App\Models\Visit;
use App\Services\PregCheckService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * AI success-rate reporting (spec §5.6c, issue #249). Read-only aggregation of
 * conception outcomes from records already captured: final preg-check results
 * (M7 #241) matched to the breeding that produced them, with the BCS recorded
 * on that breeding visit (M7 #239), the sire/breed from the semen lot (M5), and
 * the protocol type / cow-vs-heifer from M1.
 *
 * The BCS-vs-conception comparison is the headline report — it turns "fat cows
 * don't breed well" into evidence ("cows at BCS 7+ settled X% vs. Y% at 5–6").
 */
class AiSuccessRateReport
{
    /**
     * Build one {@see ConceptionOutcome} per final preg check that can be
     * matched to a breeding. Optionally scoped to a single team.
     *
     * @return Collection<int, ConceptionOutcome>
     */
    public function outcomes(?int $teamId = null): Collection
    {
        $checks = PregCheck::query()
            ->with(['cattle', 'team.client:id,team_id,contact_name'])
            ->whereIn('state', array_map(fn (PregCheckState $s) => $s->value, PregCheckState::finalStates()))
            ->whereNotNull('result_recorded_at')
            ->when($teamId !== null, fn ($q) => $q->where('team_id', $teamId))
            ->orderBy('result_recorded_at')
            ->get();

        return $checks
            ->map(fn (PregCheck $check) => $this->outcomeFor($check))
            ->filter()
            ->values();
    }

    /**
     * Overall conception rate plus the settled/open/recheck counts (§5.6c).
     *
     * @return array<string, mixed>
     */
    public function overall(?int $teamId = null): array
    {
        return $this->rateFor($this->outcomes($teamId));
    }

    /**
     * Conception rate grouped by a dimension: sire, breed, client, animal type
     * (cow vs. heifer), protocol type (sync vs. natural), or season (§5.6c).
     *
     * @param  'sire'|'breed'|'client'|'animal_type'|'protocol_type'|'season'  $dimension
     * @return Collection<int, array<string, mixed>>
     */
    public function conceptionRateBy(string $dimension, ?int $teamId = null): Collection
    {
        $keyFn = $this->dimensionKey($dimension);

        return $this->outcomes($teamId)
            ->groupBy(fn (ConceptionOutcome $o) => $keyFn($o))
            ->map(fn (Collection $group, string $key) => array_merge(
                ['group' => $key],
                $this->rateFor($group),
            ))
            ->sortByDesc('evaluated')
            ->values();
    }

    /**
     * BCS-vs-conception — the headline report (§5.6c). One row per BCS bucket
     * (thin / target / over-conditioned), ordered thin → over-conditioned, so
     * Jeff can show "cows at BCS 7+ settled X% vs. Y% at 5–6".
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function bcsVsConception(?int $teamId = null): Collection
    {
        return $this->outcomes($teamId)
            ->filter(fn (ConceptionOutcome $o) => $o->bcsBucket() !== null)
            ->groupBy(fn (ConceptionOutcome $o) => $o->bcsBucket()->value)
            ->map(function (Collection $group) {
                $bucket = $group->first()->bcsBucket();

                return array_merge([
                    'bucket' => $bucket->value,
                    'label' => $bucket->label(),
                    'order' => $bucket->order(),
                ], $this->rateFor($group));
            })
            ->sortBy('order')
            ->values();
    }

    /**
     * BCS trend per animal over time (§5.6c) — is the herd improving or sliding?
     * Grouped by animal, each a time-ordered list of body-condition scores.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function bcsTrend(?int $teamId = null): Collection
    {
        return HealthRecord::query()
            ->with('cattle:id,reg_name,herd_number')
            ->where('type', 'body_condition')
            ->whereNotNull('bcs_score')
            ->when($teamId !== null, fn ($q) => $q->where('team_id', $teamId))
            ->orderBy('recorded_at')
            ->get()
            ->groupBy('cattle_id')
            ->map(function (Collection $records) {
                $cattle = $records->first()->cattle;

                return [
                    'cattle_id' => $records->first()->cattle_id,
                    'name' => $cattle?->reg_name ?? ('#'.($cattle?->herd_number ?? '?')),
                    'points' => $records->map(fn (HealthRecord $r) => [
                        'date' => $r->recorded_at?->toDateString(),
                        'bcs' => (int) $r->bcs_score,
                    ])->values(),
                ];
            })
            ->values();
    }

    /**
     * Client value over time (§5.6c): revenue per client, number of breeding
     * outcomes (a proxy for repeat business), and dormancy (days since the last
     * breeding). Staff-only — pass a $teamId to scope to one client.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function clientValue(?int $teamId = null): Collection
    {
        $now = CarbonImmutable::now();

        return $this->outcomes($teamId)
            ->groupBy(fn (ConceptionOutcome $o) => $o->teamId)
            ->map(function (Collection $group) use ($now) {
                $rate = $this->rateFor($group);
                $lastBred = $group->max(fn (ConceptionOutcome $o) => $o->bredAt?->timestamp);

                return array_merge([
                    'team_id' => $group->first()->teamId,
                    'client_name' => $group->first()->clientName,
                    'breedings' => $group->count(),
                    'last_bred_at' => $lastBred !== null ? CarbonImmutable::createFromTimestamp($lastBred)->toDateString() : null,
                    'dormant_days' => $lastBred !== null ? $now->diffInDays(CarbonImmutable::createFromTimestamp($lastBred)->startOfDay()) : null,
                ], $rate);
            })
            ->sortByDesc('conception_rate')
            ->values();
    }

    private function outcomeFor(PregCheck $check): ?ConceptionOutcome
    {
        $cattle = $check->cattle;

        if ($cattle === null) {
            return null;
        }

        $visit = $this->breedingVisit($check);

        if ($visit === null) {
            return null;
        }

        $lot = $visit->completion?->semenInventory;

        $protocolAnimalType = $visit->protocol?->animal_type;
        $animalType = $protocolAnimalType
            ?? ($cattle->animal_type instanceof AnimalType ? $cattle->animal_type->value : $cattle->animal_type);

        $bredAt = $visit->completed_at !== null ? CarbonImmutable::parse($visit->completed_at) : null;

        return new ConceptionOutcome(
            cattleId: $cattle->id,
            teamId: $check->team_id,
            clientName: $check->team?->client?->contact_name ?? $check->team?->name,
            breedingVisitId: $visit->id,
            sire: $lot?->sire,
            breed: $lot?->breed ?? $cattle->breed,
            animalType: $animalType,
            protocolType: $visit->protocol !== null ? (string) $visit->protocol->plan_type : 'natural',
            season: $this->season($bredAt),
            bcs: $this->bcsForVisit($check->cattle_id, $visit->id),
            state: $check->state,
            bredAt: $bredAt,
        );
    }

    /**
     * The breeding this preg check evaluates: the animal's most recent completed
     * breeding-related visit at or before the result date. Mirrors
     * {@see PregCheckService} so reporting and nurture agree.
     */
    private function breedingVisit(PregCheck $check): ?Visit
    {
        $cattleId = $check->cattle_id;
        $before = $check->result_recorded_at ?? CarbonImmutable::now();

        return Visit::query()
            ->with(['completion.semenInventory', 'protocol', 'booking.service'])
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', $before)
            ->where(fn ($q) => $q
                ->where('cattle_id', $cattleId)
                ->orWhereHas('booking', fn ($b) => $b->whereHas('cattle', fn ($c) => $c->where('cattle.id', $cattleId))))
            ->orderByDesc('completed_at')
            ->get()
            ->first(fn (Visit $v) => $v->isBreedingRelated());
    }

    private function bcsForVisit(int $cattleId, int $visitId): ?int
    {
        $record = HealthRecord::query()
            ->where('cattle_id', $cattleId)
            ->where('visit_id', $visitId)
            ->where('type', 'body_condition')
            ->whereNotNull('bcs_score')
            ->latest('recorded_at')
            ->latest('id')
            ->first();

        return $record?->bcs_score !== null ? (int) $record->bcs_score : null;
    }

    private function season(?CarbonImmutable $date): string
    {
        if ($date === null) {
            return 'unknown';
        }

        return match ((int) $date->month) {
            12, 1, 2 => 'winter',
            3, 4, 5 => 'spring',
            6, 7, 8 => 'summer',
            default => 'fall',
        };
    }

    /**
     * @return callable(ConceptionOutcome): string
     */
    private function dimensionKey(string $dimension): callable
    {
        return match ($dimension) {
            'sire' => fn (ConceptionOutcome $o) => $o->sire ?? 'Unknown sire',
            'breed' => fn (ConceptionOutcome $o) => $o->breed ?? 'Unknown breed',
            'client' => fn (ConceptionOutcome $o) => $o->clientName ?? ('Team #'.$o->teamId),
            'animal_type' => fn (ConceptionOutcome $o) => $o->cowOrHeifer(),
            'protocol_type' => fn (ConceptionOutcome $o) => $o->protocolType,
            'season' => fn (ConceptionOutcome $o) => $o->season,
            default => throw new \InvalidArgumentException("Unknown conception dimension: {$dimension}"),
        };
    }

    /**
     * Reduce a set of outcomes to a conception rate. The rate is settled ÷
     * evaluated (bred + open); recheck results are inconclusive and excluded
     * from the denominator but reported alongside.
     *
     * @param  Collection<int, ConceptionOutcome>  $outcomes
     * @return array<string, mixed>
     */
    private function rateFor(Collection $outcomes): array
    {
        $settled = $outcomes->filter(fn (ConceptionOutcome $o) => $o->settled())->count();
        $evaluated = $outcomes->filter(fn (ConceptionOutcome $o) => $o->evaluated())->count();
        $recheck = $outcomes->filter(fn (ConceptionOutcome $o) => $o->state === PregCheckState::Recheck)->count();

        return [
            'settled' => $settled,
            'evaluated' => $evaluated,
            'recheck' => $recheck,
            'total' => $outcomes->count(),
            'conception_rate' => $evaluated > 0 ? round($settled / $evaluated, 4) : null,
        ];
    }
}
