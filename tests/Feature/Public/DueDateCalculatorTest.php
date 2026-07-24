<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use Carbon\CarbonImmutable;
use Database\Seeders\GestationConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Spec §5.4b, issue 2.6 — the public Due Date Calculator. Gestation math is the
 * M1 GestationService's job (covered by GestationServiceTest); these assert the
 * endpoint delegates and always returns a range plus milestones.
 */
class DueDateCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GestationConfigSeeder::class);
    }

    public function test_guests_can_view_the_calculator_with_the_seeded_breed_list(): void
    {
        $this->get(route('calculators.due-date'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/DueDateCalculator')
                ->where('breeds', fn ($breeds) => in_array('Angus', collect($breeds)->all(), true)
                    && ! in_array('__default__', collect($breeds)->all(), true))
            );
    }

    /**
     * breeding date + breed (+ type) => expected gestation days and due date.
     *
     * @return array<string, array{string, ?string, ?string, int}>
     */
    public static function breedCases(): array
    {
        return [
            'known breed Jersey' => ['2026-01-01', 'Jersey', null, 279],
            'known breed Brangus' => ['2026-01-01', 'Brangus', null, 290],
            'unknown breed falls back to 283' => ['2026-01-01', 'Wagyu-cross', null, 283],
            'blank breed falls back to 283' => ['2026-01-01', '', null, 283],
            'heifer applies earlier offset' => ['2026-01-01', 'Angus', 'heifer', 282],
        ];
    }

    #[DataProvider('breedCases')]
    public function test_calculate_returns_a_range_and_expected_due_date(
        string $breedingDate,
        ?string $breed,
        ?string $animalType,
        int $expectedDays,
    ): void {
        $expectedDue = CarbonImmutable::parse($breedingDate)->startOfDay()->addDays($expectedDays);

        $this->post(route('calculators.due-date.calculate'), array_filter([
            'breeding_date' => $breedingDate,
            'breed' => $breed,
            'animal_type' => $animalType,
        ], fn ($value) => $value !== null))
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/DueDateCalculator')
                ->where('result.gestation_days', $expectedDays)
                ->where('result.estimated_due_date', $expectedDue->format('M j, Y'))
                ->where('result.window_start', $expectedDue->subDays(5)->format('M j, Y'))
                ->where('result.window_end', $expectedDue->addDays(5)->format('M j, Y'))
                ->has('result.milestones', 6)
                ->where('result.caveat', fn (string $c) => str_contains($c, 'estimate')
                    && str_contains($c, 'bull calves'))
            );
    }

    public function test_calculate_validates_input(): void
    {
        $this->post(route('calculators.due-date.calculate'), [
            'breeding_date' => 'not-a-date',
        ])->assertSessionHasErrors(['breeding_date']);
    }
}
