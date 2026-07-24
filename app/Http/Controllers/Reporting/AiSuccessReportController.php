<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Services\Reporting\AiSuccessRateReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * AI success-rate reporting (spec §5.6c, issue #249). BCS-vs-conception is the
 * headline. Staff (Jeff) see every client; a client sees only their own herd —
 * scope is decided from the staff flag, never a client-supplied team id.
 */
class AiSuccessReportController extends Controller
{
    public function index(Request $request, AiSuccessRateReport $report): Response
    {
        $user = $request->user();
        $isStaff = $user->isStaff();
        $teamId = $isStaff ? null : $user->current_team_id;

        return Inertia::render('reports/AiSuccess', [
            'isStaff' => $isStaff,
            'overall' => $report->overall($teamId),
            'bcsVsConception' => $report->bcsVsConception($teamId)->values(),
            'bySire' => $report->conceptionRateBy('sire', $teamId)->values(),
            'byBreed' => $report->conceptionRateBy('breed', $teamId)->values(),
            'byAnimalType' => $report->conceptionRateBy('animal_type', $teamId)->values(),
            'byProtocolType' => $report->conceptionRateBy('protocol_type', $teamId)->values(),
            'bySeason' => $report->conceptionRateBy('season', $teamId)->values(),
            'byClient' => $isStaff ? $report->conceptionRateBy('client', $teamId)->values() : [],
            'bcsTrend' => $report->bcsTrend($teamId)->values(),
            'clientValue' => $isStaff ? $report->clientValue($teamId)->values() : [],
        ]);
    }
}
