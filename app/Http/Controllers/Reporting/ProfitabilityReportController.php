<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Services\Reporting\AppointmentProfit;
use App\Services\Reporting\ProfitabilityReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cost & profitability reporting (spec §5.6c, issue #248). Staff (Jeff) see the
 * aggregate across every client; a client sees only their own team's numbers —
 * scope is decided from the staff flag, never a client-supplied team id.
 */
class ProfitabilityReportController extends Controller
{
    public function index(Request $request, ProfitabilityReport $report): Response
    {
        $user = $request->user();
        $isStaff = $user->isStaff();
        $teamId = $isStaff ? null : $user->current_team_id;

        [$from, $to] = $this->window($request);
        $period = $request->string('period')->toString() === 'year' ? 'year' : 'month';

        $appointments = $report->perAppointment($teamId, $from, $to);

        return Inertia::render('reports/Profitability', [
            'isStaff' => $isStaff,
            'filters' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
                'period' => $period,
            ],
            'totals' => $this->totals($appointments),
            'appointments' => $appointments->map->toArray()->values(),
            'byServiceType' => $report->perServiceType($teamId, $from, $to)->values(),
            'byClient' => $isStaff ? $report->perClient($teamId, $from, $to)->values() : [],
            'mileageByPeriod' => $report->mileageTotalsByPeriod($period, $teamId)->values(),
            'mileageRate' => $report->mileageCostPerMile(),
        ]);
    }

    /**
     * @param  Collection<int, AppointmentProfit>  $appointments
     * @return array<string, float|int>
     */
    private function totals($appointments): array
    {
        $revenue = round((float) $appointments->sum(fn (AppointmentProfit $p) => $p->revenue), 2);
        $supply = round((float) $appointments->sum(fn (AppointmentProfit $p) => $p->supplyCost), 2);
        $drug = round((float) $appointments->sum(fn (AppointmentProfit $p) => $p->drugCost), 2);
        $mileageCost = round((float) $appointments->sum(fn (AppointmentProfit $p) => $p->mileageCost), 2);
        $miles = round((float) $appointments->sum(fn (AppointmentProfit $p) => $p->mileage), 2);
        $profit = round($revenue - $supply - $drug - $mileageCost, 2);

        return [
            'appointments' => $appointments->count(),
            'revenue' => $revenue,
            'supply_cost' => $supply,
            'drug_cost' => $drug,
            'mileage' => $miles,
            'mileage_cost' => $mileageCost,
            'profit' => $profit,
        ];
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function window(Request $request): array
    {
        $from = $request->date('from');
        $to = $request->date('to');

        return [
            $from !== null ? CarbonImmutable::parse($from)->startOfDay() : null,
            $to !== null ? CarbonImmutable::parse($to)->endOfDay() : null,
        ];
    }
}
