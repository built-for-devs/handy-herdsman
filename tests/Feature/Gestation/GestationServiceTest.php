<?php

declare(strict_types=1);

namespace Tests\Feature\Gestation;

use App\Enums\AnimalType;
use App\Services\Gestation\GestationService;
use Carbon\CarbonImmutable;
use Database\Seeders\GestationConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec §5.4b. Config-driven, breed-table-based due-date estimator. Always a
 * range/estimate, never a hard date.
 */
class GestationServiceTest extends TestCase
{
    use RefreshDatabase;

    private GestationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GestationConfigSeeder::class);
        $this->service = new GestationService;
    }

    public function test_uses_seeded_breed_gestation_length(): void
    {
        $this->assertSame(283, $this->service->gestationDaysFor('Angus'));
        $this->assertSame(279, $this->service->gestationDaysFor('Jersey'));
    }

    public function test_falls_back_to_283_default_for_unknown_or_crossbreed(): void
    {
        $this->assertSame(283, $this->service->gestationDaysFor('Wagyu-cross'));
        $this->assertSame(283, $this->service->gestationDaysFor(null));
    }

    public function test_applies_heifer_offset_but_not_for_cows(): void
    {
        // Seeded heifer offset is -1 day (heifers calve earlier).
        $this->assertSame(282, $this->service->gestationDaysFor('Angus', AnimalType::Heifer));
        $this->assertSame(283, $this->service->gestationDaysFor('Angus', AnimalType::Cow));
    }

    public function test_returns_a_range_around_the_due_date_never_a_hard_date(): void
    {
        $bred = CarbonImmutable::parse('2026-01-01');
        $estimate = $this->service->estimate($bred, 'Angus', AnimalType::Cow);

        $this->assertSame('2026-10-11', $estimate->estimatedDueDate->toDateString());
        $this->assertSame('2026-10-06', $estimate->windowStart->toDateString());
        $this->assertSame('2026-10-16', $estimate->windowEnd->toDateString());
        $this->assertStringContainsString('estimate', $estimate->caveat());
    }

    public function test_computes_calving_prep_milestones_off_the_due_date(): void
    {
        $bred = CarbonImmutable::parse('2026-01-01');
        $estimate = $this->service->estimate($bred, 'Angus', AnimalType::Cow);
        $due = $estimate->estimatedDueDate;

        $this->assertSame($due->subDays(28)->toDateString(), $estimate->milestones['minus_4wk']->toDateString());
        $this->assertSame($due->subDays(14)->toDateString(), $estimate->milestones['minus_2wk']->toDateString());
        $this->assertSame($due->subDay()->toDateString(), $estimate->milestones['minus_1d']->toDateString());
        $this->assertSame($due->subDays(60)->toDateString(), $estimate->milestones['dry_off']->toDateString());
    }

    public function test_exposes_a_stored_point_due_date_for_reminders(): void
    {
        $bred = CarbonImmutable::parse('2026-01-01');

        $this->assertSame(
            '2026-10-11',
            $this->service->storedDueDate($bred, 'Angus', AnimalType::Cow)->toDateString()
        );
    }
}
