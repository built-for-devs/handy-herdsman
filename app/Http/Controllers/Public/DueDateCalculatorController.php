<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\CalculateDueDateRequest;
use App\Models\GestationConfig;
use App\Services\Gestation\GestationService;
use App\Support\Calculators\DueDateResult;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public (unauthenticated) Due Date / Gestation Calculator (spec §5.4b, issue
 * 2.6). Delegates ALL gestation math to the M1 {@see GestationService}; the
 * breed list is seeded config data and unknown/crossbreed falls back to the
 * configured default (283d).
 */
class DueDateCalculatorController extends Controller
{
    public function __construct(private readonly GestationService $gestation) {}

    public function show(): Response
    {
        return Inertia::render('public/DueDateCalculator', [
            'breeds' => $this->breeds(),
        ]);
    }

    public function calculate(CalculateDueDateRequest $request): Response
    {
        $breedingDate = CarbonImmutable::parse((string) $request->validated('breeding_date'));
        $breed = $request->validated('breed');

        $estimate = $this->gestation->estimate(
            $breedingDate,
            $breed !== '' ? $breed : null,
            $request->animalType(),
        );

        return Inertia::render('public/DueDateCalculator', [
            'breeds' => $this->breeds(),
            'input' => [
                'breeding_date' => (string) $request->validated('breeding_date'),
                'breed' => $breed,
                'animal_type' => $request->animalType()?->value,
            ],
            'result' => DueDateResult::fromEstimate($estimate)->toArray(),
        ]);
    }

    /**
     * The seeded breed list (spec §5.4b table), excluding the internal
     * default/heifer-offset rows. Unknown/crossbreed is handled by the service.
     *
     * @return list<string>
     */
    private function breeds(): array
    {
        return GestationConfig::query()
            ->whereNotIn('breed', [GestationConfig::DEFAULT_KEY, GestationConfig::HEIFER_OFFSET_KEY])
            ->orderBy('breed')
            ->pluck('breed')
            ->all();
    }
}
