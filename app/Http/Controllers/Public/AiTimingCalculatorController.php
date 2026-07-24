<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Calculators\SendAiTimingResult;
use App\Enums\AnimalType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\CalculateAiTimingRequest;
use App\Http\Requests\Public\SendAiTimingResultRequest;
use App\Services\Protocol\ProtocolTimingService;
use App\Support\Calculators\AiTimingResult;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public (unauthenticated) AI Timing Calculator — the primary lead magnet
 * (spec §5.4, issue 2.5). Delegates ALL timing math to the M1
 * {@see ProtocolTimingService}; this controller only shapes input/output.
 */
class AiTimingCalculatorController extends Controller
{
    public function __construct(private readonly ProtocolTimingService $timing) {}

    public function show(): Response
    {
        return Inertia::render('public/AiTimingCalculator', [
            'animalTypes' => $this->animalTypeOptions(),
        ]);
    }

    public function calculate(CalculateAiTimingRequest $request): Response
    {
        $animalType = $request->animalType();
        $visit1At = CarbonImmutable::parse((string) $request->validated('visit1_at'));

        return Inertia::render('public/AiTimingCalculator', [
            'animalTypes' => $this->animalTypeOptions(),
            'input' => [
                'visit1_at' => (string) $request->validated('visit1_at'),
                'animal_type' => $animalType->value,
            ],
            'ineligible' => $this->ineligibleMessage($animalType),
            'result' => $animalType->canBeBred()
                ? AiTimingResult::fromSchedule(
                    $this->timing->scheduleFromVisit1($visit1At, $animalType),
                    $animalType,
                )->toArray()
                : null,
        ]);
    }

    public function send(SendAiTimingResultRequest $request, SendAiTimingResult $action): RedirectResponse
    {
        $animalType = $request->animalType();

        abort_unless($animalType->canBeBred(), 422);

        $visit1At = CarbonImmutable::parse((string) $request->validated('visit1_at'));

        $result = AiTimingResult::fromSchedule(
            $this->timing->scheduleFromVisit1($visit1At, $animalType),
            $animalType,
        );

        $action->handle(
            $result,
            $request->validated('email'),
            $request->validated('phone'),
        );

        return back()->with('status', 'Your AI timing plan is on its way. Check your email or phone shortly.');
    }

    private function ineligibleMessage(AnimalType $animalType): ?string
    {
        if ($animalType->canBeBred()) {
            return null;
        }

        return "A {$animalType->label()} can't receive AI/breeding services — only "
            .'heifers and cows can be bred. Pick a heifer or cow to see the timing plan.';
    }

    /**
     * @return list<array{value: string, label: string, can_be_bred: bool}>
     */
    private function animalTypeOptions(): array
    {
        return array_map(fn (AnimalType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'can_be_bred' => $type->canBeBred(),
        ], AnimalType::cases());
    }
}
