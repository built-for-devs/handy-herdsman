<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Models\Cattle;
use App\Models\HealthRecord;
use App\Models\PregCheck;
use App\Models\Protocol;
use App\Models\SemenInventory;
use App\Models\Service;
use App\Models\Team;
use App\Models\Visit;
use App\Models\VisitCompletion;
use App\Services\Reporting\AiSuccessRateReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI success-rate reporting (§5.6c, #249). Conception rate = settled (bred) ÷
 * evaluated (bred + open); BCS-vs-conception is the headline. Every figure is
 * derived from final preg-check results matched to the breeding that produced
 * them plus the BCS recorded on that breeding visit.
 */
class AiSuccessRateReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_conception_rate_by_sire_breed_and_cow_vs_heifer(): void
    {
        $team = Team::factory()->create();

        // Sire "Rocket" (Angus) settles both cows; sire "Dud" settles neither.
        $this->breeding($team, sire: 'Rocket', breed: 'Angus', animalType: 'cow', settled: true);
        $this->breeding($team, sire: 'Rocket', breed: 'Angus', animalType: 'cow', settled: true);
        $this->breeding($team, sire: 'Dud', breed: 'Hereford', animalType: 'heifer', settled: false);
        $this->breeding($team, sire: 'Dud', breed: 'Hereford', animalType: 'heifer', settled: false);

        $report = app(AiSuccessRateReport::class);

        $overall = $report->overall();
        $this->assertSame(4, $overall['evaluated']);
        $this->assertSame(2, $overall['settled']);
        $this->assertSame(0.5, $overall['conception_rate']);

        $bySire = $report->conceptionRateBy('sire')->keyBy('group');
        $this->assertSame(1.0, $bySire['Rocket']['conception_rate']);
        $this->assertSame(0.0, $bySire['Dud']['conception_rate']);

        $byBreed = $report->conceptionRateBy('breed')->keyBy('group');
        $this->assertSame(1.0, $byBreed['Angus']['conception_rate']);
        $this->assertSame(0.0, $byBreed['Hereford']['conception_rate']);

        $byType = $report->conceptionRateBy('animal_type')->keyBy('group');
        $this->assertSame(1.0, $byType['cow']['conception_rate']);
        $this->assertSame(0.0, $byType['heifer']['conception_rate']);
    }

    public function test_bcs_bucket_comparison_produces_the_headline_metric(): void
    {
        $team = Team::factory()->create();

        // BCS 7+ (over-conditioned): 3 of 4 settle → 75%.
        $this->breeding($team, sire: 'A', breed: 'Angus', animalType: 'cow', settled: true, bcs: 7);
        $this->breeding($team, sire: 'A', breed: 'Angus', animalType: 'cow', settled: true, bcs: 8);
        $this->breeding($team, sire: 'A', breed: 'Angus', animalType: 'cow', settled: true, bcs: 7);
        $this->breeding($team, sire: 'A', breed: 'Angus', animalType: 'cow', settled: false, bcs: 7);

        // BCS 5–6 (target): 1 of 4 settle → 25%.
        $this->breeding($team, sire: 'A', breed: 'Angus', animalType: 'cow', settled: true, bcs: 5);
        $this->breeding($team, sire: 'A', breed: 'Angus', animalType: 'cow', settled: false, bcs: 6);
        $this->breeding($team, sire: 'A', breed: 'Angus', animalType: 'cow', settled: false, bcs: 5);
        $this->breeding($team, sire: 'A', breed: 'Angus', animalType: 'cow', settled: false, bcs: 6);

        $buckets = app(AiSuccessRateReport::class)->bcsVsConception()->keyBy('bucket');

        $this->assertSame(0.75, $buckets['7plus']['conception_rate']);
        $this->assertSame(0.25, $buckets['5-6']['conception_rate']);
        $this->assertGreaterThan(
            $buckets['5-6']['conception_rate'],
            $buckets['7plus']['conception_rate'],
            'BCS 7+ should out-settle BCS 5–6 in this sample — the headline argument.',
        );
    }

    public function test_protocol_type_sync_vs_natural_is_reported(): void
    {
        $team = Team::factory()->create();

        $this->breeding($team, sire: 'A', breed: 'Angus', animalType: 'cow', settled: true, planType: 'sync');
        $this->breeding($team, sire: 'A', breed: 'Angus', animalType: 'cow', settled: false, planType: 'natural');

        $byProtocol = app(AiSuccessRateReport::class)->conceptionRateBy('protocol_type')->keyBy('group');

        $this->assertSame(1.0, $byProtocol['sync']['conception_rate']);
        $this->assertSame(0.0, $byProtocol['natural']['conception_rate']);
    }

    public function test_recheck_results_are_excluded_from_the_conception_rate(): void
    {
        $team = Team::factory()->create();

        $this->breeding($team, sire: 'A', breed: 'Angus', animalType: 'cow', settled: true);
        $this->breeding($team, sire: 'A', breed: 'Angus', animalType: 'cow', state: 'recheck');

        $overall = app(AiSuccessRateReport::class)->overall();

        $this->assertSame(1, $overall['evaluated']); // the recheck is not evaluated
        $this->assertSame(1, $overall['recheck']);
        $this->assertSame(1.0, $overall['conception_rate']);
    }

    /**
     * Seed one complete breeding → final-preg-check outcome.
     */
    private function breeding(
        Team $team,
        string $sire,
        string $breed,
        string $animalType,
        bool $settled = false,
        ?int $bcs = null,
        string $planType = 'sync',
        ?string $state = null,
    ): PregCheck {
        $bredAt = CarbonImmutable::parse('2026-04-01');

        $cattle = Cattle::factory()->for($team)->create(['animal_type' => $animalType, 'breed' => $breed]);
        $lot = SemenInventory::factory()->for($team)->create(['sire' => $sire, 'breed' => $breed]);
        $protocol = Protocol::factory()->for($team)->create([
            'plan_type' => $planType,
            'animal_type' => $animalType,
            'service_id' => Service::factory(),
        ]);

        $visit = Visit::factory()->for($team)->create([
            'cattle_id' => $cattle->id,
            'protocol_id' => $protocol->id,
            'type' => 'v3',
            'status' => 'completed',
            'completed_at' => $bredAt,
        ]);

        VisitCompletion::create([
            'visit_id' => $visit->id,
            'completed_at' => $bredAt,
            'semen_inventory_id' => $lot->id,
            'straws_used' => 1,
        ]);

        if ($bcs !== null) {
            HealthRecord::create([
                'team_id' => $team->id,
                'cattle_id' => $cattle->id,
                'visit_id' => $visit->id,
                'type' => 'body_condition',
                'bcs_score' => $bcs,
                'recorded_at' => $bredAt,
                'added_role' => 'staff',
            ]);
        }

        return PregCheck::create([
            'team_id' => $team->id,
            'cattle_id' => $cattle->id,
            'visit_id' => $visit->id,
            'method' => 'palpation',
            'state' => $state ?? ($settled ? 'bred' : 'open'),
            'result_recorded_at' => $bredAt->addDays(45),
        ]);
    }
}
