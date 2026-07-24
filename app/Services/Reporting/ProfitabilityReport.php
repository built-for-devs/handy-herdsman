<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\RateConfig;
use App\Models\Supply;
use App\Models\Visit;
use App\Models\VisitCompletion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Cost & profitability reporting (spec §5.6c, issue #248). Read-only: every
 * number is derived from records already captured — no separate bookkeeping.
 *
 *  - Revenue: the booking's earned {@see Payment} totals (paid + owed; pending,
 *    failed and refunded do not count as earned).
 *  - Supply/drug cost: each visit's completion `supplies_used` map ×
 *    `supplies.unit_cost` (M5). Prescription items are reported as "drugs".
 *  - Mileage cost: completion mileage × the `mileage_cost_per_mile` rate.
 *
 * An "appointment" is a booking (the trip the client books) — a sync protocol's
 * three farm calls share one booking and one payment, so their supply and
 * mileage costs are pooled against that one revenue figure.
 */
class ProfitabilityReport
{
    /** Payment states that count as earned revenue (§5.6c, §10b — Money). */
    private const EARNED = [PaymentStatus::Paid->value, PaymentStatus::Owed->value];

    /**
     * Profit for every appointment (booking) with recorded activity, newest
     * first. Optionally scoped to one team (a client sees only their own) and a
     * date window.
     *
     * @return Collection<int, AppointmentProfit>
     */
    public function perAppointment(?int $teamId = null, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): Collection
    {
        $supplyLookup = $this->supplyLookup();
        $mileageRate = $this->mileageCostPerMile();

        $bookings = Booking::query()
            ->with([
                'service:id,name,type',
                'team.client:id,team_id,contact_name',
                'visits' => fn ($q) => $q->with('completion'),
                'payments',
            ])
            ->when($teamId !== null, fn ($q) => $q->where('team_id', $teamId))
            ->orderByDesc('id')
            ->get();

        return $bookings
            ->map(fn (Booking $booking) => $this->profitForBooking($booking, $supplyLookup, $mileageRate))
            ->filter(fn (AppointmentProfit $p) => $this->withinWindow($p->date, $from, $to))
            ->values();
    }

    /**
     * Profit rolled up per service type — "which services are actually worth
     * doing" (§5.6c). Sorted by profit descending.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function perServiceType(?int $teamId = null, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): Collection
    {
        return $this->groupSummary(
            $this->perAppointment($teamId, $from, $to),
            fn (AppointmentProfit $p) => $p->serviceName ?? 'Uncategorised',
            fn (AppointmentProfit $p) => [
                'service_id' => $p->serviceId,
                'service_name' => $p->serviceName,
                'service_type' => $p->serviceType,
            ],
        );
    }

    /**
     * Profit rolled up per client (team). Staff see every client; a client sees
     * only their own row when $teamId is set.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function perClient(?int $teamId = null, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): Collection
    {
        return $this->groupSummary(
            $this->perAppointment($teamId, $from, $to),
            fn (AppointmentProfit $p) => (string) $p->teamId,
            fn (AppointmentProfit $p) => [
                'team_id' => $p->teamId,
                'client_name' => $p->clientName,
            ],
        );
    }

    /**
     * Mileage driven totalled by calendar period (for the tax deduction, §5.6c).
     * Uses each visit's authoritative completion mileage, falling back to the
     * visit's own mileage. Independent of bookings so standalone/on-call visits
     * are still counted.
     *
     * @param  'month'|'year'  $granularity
     * @return Collection<int, array<string, mixed>>
     */
    public function mileageTotalsByPeriod(string $granularity = 'month', ?int $teamId = null): Collection
    {
        $rate = $this->mileageCostPerMile();
        $format = $granularity === 'year' ? 'Y' : 'Y-m';

        $visits = Visit::query()
            ->with('completion')
            ->when($teamId !== null, fn ($q) => $q->where('team_id', $teamId))
            ->whereNotNull('completed_at')
            ->get();

        return $visits
            ->map(function (Visit $visit) {
                $date = $visit->completion?->completed_at ?? $visit->completed_at;

                return ['miles' => $this->visitMileage($visit), 'date' => $date];
            })
            ->filter(fn (array $row) => $row['date'] !== null && $row['miles'] > 0)
            ->groupBy(fn (array $row) => CarbonImmutable::parse($row['date'])->format($format))
            ->map(function (Collection $rows, string $period) use ($rate) {
                $miles = round((float) $rows->sum('miles'), 2);

                return [
                    'period' => $period,
                    'miles' => $miles,
                    'deduction' => round($miles * $rate, 2),
                    'visits' => $rows->count(),
                ];
            })
            ->sortKeysDesc()
            ->values();
    }

    /** The editable per-mile cost rate (§5.6c, §7 — config over hardcoded). */
    public function mileageCostPerMile(): float
    {
        $value = RateConfig::value('mileage_cost_per_mile', []);

        return (float) (is_array($value) ? ($value['price'] ?? 0) : 0);
    }

    /**
     * @param  array<int, array{unit_cost: float, is_prescription: bool}>  $supplyLookup
     */
    private function profitForBooking(Booking $booking, array $supplyLookup, float $mileageRate): AppointmentProfit
    {
        $supplyCost = 0.0;
        $drugCost = 0.0;
        $miles = 0.0;
        $lastDate = null;

        foreach ($booking->visits as $visit) {
            $completion = $visit->completion;

            if ($completion === null) {
                continue;
            }

            [$supply, $drug] = $this->costForUsage($completion, $supplyLookup);
            $supplyCost += $supply;
            $drugCost += $drug;
            $miles += $this->visitMileage($visit);

            $completedAt = $completion->completed_at ?? $visit->completed_at;
            if ($completedAt !== null) {
                $date = CarbonImmutable::parse($completedAt);
                $lastDate = $lastDate === null || $date->greaterThan($lastDate) ? $date : $lastDate;
            }
        }

        $revenue = (float) $booking->payments
            ->whereIn('status', self::EARNED)
            ->sum(fn (Payment $p) => (float) $p->total);

        $date = $lastDate
            ?? ($booking->proposed_start !== null ? CarbonImmutable::parse($booking->proposed_start) : null);

        return new AppointmentProfit(
            bookingId: $booking->id,
            teamId: $booking->team_id,
            clientName: $booking->team?->client?->contact_name ?? $booking->team?->name,
            serviceId: $booking->service_id,
            serviceName: $booking->service?->name,
            serviceType: $booking->service?->type,
            revenue: round($revenue, 2),
            supplyCost: round($supplyCost, 2),
            drugCost: round($drugCost, 2),
            mileage: round($miles, 2),
            mileageCost: round($miles * $mileageRate, 2),
            date: $date,
        );
    }

    /**
     * Split a completion's supply usage into non-prescription (supplies) and
     * prescription (drugs) cost using each item's unit_cost.
     *
     * @param  array<int, array{unit_cost: float, is_prescription: bool}>  $supplyLookup
     * @return array{0: float, 1: float}
     */
    private function costForUsage(VisitCompletion $completion, array $supplyLookup): array
    {
        $supply = 0.0;
        $drug = 0.0;

        foreach (($completion->supplies_used ?? []) as $supplyId => $qty) {
            $item = $supplyLookup[(int) $supplyId] ?? null;

            if ($item === null) {
                continue;
            }

            $lineCost = (float) $qty * $item['unit_cost'];

            if ($item['is_prescription']) {
                $drug += $lineCost;
            } else {
                $supply += $lineCost;
            }
        }

        return [round($supply, 2), round($drug, 2)];
    }

    private function visitMileage(Visit $visit): float
    {
        $miles = $visit->completion?->mileage ?? $visit->mileage;

        return $miles !== null ? (float) $miles : 0.0;
    }

    /**
     * @return array<int, array{unit_cost: float, is_prescription: bool}>
     */
    private function supplyLookup(): array
    {
        return Supply::query()
            ->withTrashed()
            ->get(['id', 'unit_cost', 'is_prescription'])
            ->mapWithKeys(fn (Supply $s) => [
                $s->id => [
                    'unit_cost' => (float) $s->unit_cost,
                    'is_prescription' => (bool) $s->is_prescription,
                ],
            ])
            ->all();
    }

    /**
     * Roll a set of appointment-profit lines up by a grouping key.
     *
     * @param  Collection<int, AppointmentProfit>  $appointments
     * @param  callable(AppointmentProfit): string  $keyBy
     * @param  callable(AppointmentProfit): array<string, mixed>  $labels
     * @return Collection<int, array<string, mixed>>
     */
    private function groupSummary(Collection $appointments, callable $keyBy, callable $labels): Collection
    {
        return $appointments
            ->groupBy($keyBy)
            ->map(function (Collection $group) use ($labels) {
                $revenue = round((float) $group->sum(fn (AppointmentProfit $p) => $p->revenue), 2);
                $supply = round((float) $group->sum(fn (AppointmentProfit $p) => $p->supplyCost), 2);
                $drug = round((float) $group->sum(fn (AppointmentProfit $p) => $p->drugCost), 2);
                $mileageCost = round((float) $group->sum(fn (AppointmentProfit $p) => $p->mileageCost), 2);
                $miles = round((float) $group->sum(fn (AppointmentProfit $p) => $p->mileage), 2);
                $cogs = round($supply + $drug + $mileageCost, 2);
                $profit = round($revenue - $cogs, 2);

                return array_merge($labels($group->first()), [
                    'appointments' => $group->count(),
                    'revenue' => $revenue,
                    'supply_cost' => $supply,
                    'drug_cost' => $drug,
                    'mileage' => $miles,
                    'mileage_cost' => $mileageCost,
                    'cogs' => $cogs,
                    'profit' => $profit,
                    'margin' => $revenue > 0 ? round($profit / $revenue, 4) : 0.0,
                ]);
            })
            ->sortByDesc('profit')
            ->values();
    }

    private function withinWindow(?CarbonImmutable $date, ?CarbonImmutable $from, ?CarbonImmutable $to): bool
    {
        if ($from === null && $to === null) {
            return true;
        }

        if ($date === null) {
            return false;
        }

        if ($from !== null && $date->lessThan($from)) {
            return false;
        }

        return $to === null || ! $date->greaterThan($to);
    }
}
