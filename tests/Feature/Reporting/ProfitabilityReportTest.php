<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\RateConfig;
use App\Models\Service;
use App\Models\Supply;
use App\Models\Team;
use App\Models\Visit;
use App\Models\VisitCompletion;
use App\Services\Reporting\ProfitabilityReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cost & profitability reporting (§5.6c, #248). Every number is derived from
 * already-captured records: revenue from payments, supply/drug cost from the
 * completion usage map × unit costs, mileage cost from the mileage rate.
 */
class ProfitabilityReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateConfig::create([
            'key' => 'mileage_cost_per_mile',
            'value' => ['price' => 0.5],
            'group' => 'fees',
        ]);
    }

    public function test_profit_is_revenue_minus_supplies_drugs_and_mileage(): void
    {
        $team = Team::factory()->create();

        // A non-prescription supply ($10) and a prescription drug ($5).
        $cidr = Supply::factory()->create(['unit_cost' => 10, 'is_prescription' => false]);
        $lutalyse = Supply::factory()->create(['unit_cost' => 5, 'is_prescription' => true]);

        $service = Service::factory()->create(['name' => 'Sync AI', 'type' => 'protocol']);
        $booking = Booking::factory()->for($team)->confirmed()->create(['service_id' => $service->id]);

        $visit = Visit::factory()->for($team)->create([
            'booking_id' => $booking->id,
            'status' => 'completed',
            'completed_at' => CarbonImmutable::parse('2026-05-10'),
        ]);

        VisitCompletion::create([
            'visit_id' => $visit->id,
            'completed_at' => CarbonImmutable::parse('2026-05-10'),
            'supplies_used' => [$cidr->id => 1, $lutalyse->id => 2], // 1×$10 supply + 2×$5 drug
            'mileage' => 20,
        ]);

        Payment::create([
            'team_id' => $team->id,
            'booking_id' => $booking->id,
            'total' => 300,
            'method' => 'card',
            'status' => 'paid',
        ]);

        $appointment = app(ProfitabilityReport::class)->perAppointment()->firstOrFail();

        // revenue 300 − (supplies 10 + drugs 10 + mileage 20×$0.50 = 10) = 270.
        $this->assertSame(300.0, $appointment->revenue);
        $this->assertSame(10.0, $appointment->supplyCost);
        $this->assertSame(10.0, $appointment->drugCost);
        $this->assertSame(20.0, $appointment->mileage);
        $this->assertSame(10.0, $appointment->mileageCost);
        $this->assertSame(30.0, $appointment->cogs());
        $this->assertSame(270.0, $appointment->profit());
    }

    public function test_pending_and_refunded_payments_are_not_counted_as_revenue(): void
    {
        $team = Team::factory()->create();
        $booking = Booking::factory()->for($team)->create();

        Payment::create(['team_id' => $team->id, 'booking_id' => $booking->id, 'total' => 100, 'method' => 'card', 'status' => 'pending']);
        Payment::create(['team_id' => $team->id, 'booking_id' => $booking->id, 'total' => 200, 'method' => 'card', 'status' => 'refunded']);
        Payment::create(['team_id' => $team->id, 'booking_id' => $booking->id, 'total' => 300, 'method' => 'cash', 'status' => 'owed']);

        $appointment = app(ProfitabilityReport::class)->perAppointment()->firstOrFail();

        // Only the owed (cash, earned) payment counts.
        $this->assertSame(300.0, $appointment->revenue);
    }

    public function test_per_service_type_and_per_client_aggregate_profit(): void
    {
        $team = Team::factory()->create(['name' => 'Bar-K Ranch']);
        $sync = Service::factory()->create(['name' => 'Sync AI', 'type' => 'protocol']);
        $consult = Service::factory()->create(['name' => 'Consult', 'type' => 'standard']);

        $this->completedBooking($team, $sync, revenue: 300, mileage: 0);
        $this->completedBooking($team, $sync, revenue: 200, mileage: 0);
        $this->completedBooking($team, $consult, revenue: 75, mileage: 0);

        $report = app(ProfitabilityReport::class);

        $byService = $report->perServiceType()->keyBy('service_name');
        $this->assertSame(500.0, $byService['Sync AI']['revenue']);
        $this->assertSame(2, $byService['Sync AI']['appointments']);
        $this->assertSame(75.0, $byService['Consult']['revenue']);

        $byClient = $report->perClient()->firstOrFail();
        $this->assertSame('Bar-K Ranch', $byClient['client_name']);
        $this->assertSame(575.0, $byClient['revenue']);
        $this->assertSame(575.0, $byClient['profit']); // no costs seeded
    }

    public function test_mileage_totals_aggregate_by_period(): void
    {
        $team = Team::factory()->create();

        $this->completedVisit($team, CarbonImmutable::parse('2026-05-03'), 15);
        $this->completedVisit($team, CarbonImmutable::parse('2026-05-20'), 25);
        $this->completedVisit($team, CarbonImmutable::parse('2026-06-01'), 40);

        $byMonth = app(ProfitabilityReport::class)->mileageTotalsByPeriod('month')->keyBy('period');

        $this->assertSame(40.0, $byMonth['2026-05']['miles']);   // 15 + 25
        $this->assertSame(20.0, $byMonth['2026-05']['deduction']); // 40 × $0.50
        $this->assertSame(2, $byMonth['2026-05']['visits']);
        $this->assertSame(40.0, $byMonth['2026-06']['miles']);

        $byYear = app(ProfitabilityReport::class)->mileageTotalsByPeriod('year')->firstOrFail();
        $this->assertSame('2026', $byYear['period']);
        $this->assertSame(80.0, $byYear['miles']);
        $this->assertSame(40.0, $byYear['deduction']);
    }

    private function completedBooking(Team $team, Service $service, float $revenue, float $mileage): void
    {
        $booking = Booking::factory()->for($team)->create(['service_id' => $service->id]);
        $visit = Visit::factory()->for($team)->create([
            'booking_id' => $booking->id,
            'status' => 'completed',
            'completed_at' => CarbonImmutable::parse('2026-05-15'),
        ]);
        VisitCompletion::create([
            'visit_id' => $visit->id,
            'completed_at' => CarbonImmutable::parse('2026-05-15'),
            'mileage' => $mileage,
        ]);
        Payment::create(['team_id' => $team->id, 'booking_id' => $booking->id, 'total' => $revenue, 'method' => 'card', 'status' => 'paid']);
    }

    private function completedVisit(Team $team, CarbonImmutable $at, float $mileage): void
    {
        $visit = Visit::factory()->for($team)->create(['status' => 'completed', 'completed_at' => $at]);
        VisitCompletion::create(['visit_id' => $visit->id, 'completed_at' => $at, 'mileage' => $mileage]);
    }
}
