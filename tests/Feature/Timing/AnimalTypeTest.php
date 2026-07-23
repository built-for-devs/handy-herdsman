<?php

declare(strict_types=1);

namespace Tests\Feature\Timing;

use App\Enums\AgeStage;
use App\Enums\AnimalType;
use App\Exceptions\BreedingEligibilityException;
use App\Models\Cattle;
use App\Models\Service;
use App\Models\Team;
use App\Models\User;
use App\Services\Protocol\BreedingEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Spec §10b (Animal type & protocol eligibility).
 */
class AnimalTypeTest extends TestCase
{
    use RefreshDatabase;

    private function makeCattle(AnimalType $type, ?string $dob = null): Cattle
    {
        $team = Team::create([
            'owner_id' => User::factory()->create()->id,
            'name' => 'Test Ranch',
        ]);

        return Cattle::create([
            'team_id' => $team->id,
            'reg_name' => 'Bessie',
            'animal_type' => $type->value,
            'dob' => $dob,
            'status' => 'active',
        ]);
    }

    private function breedingService(): Service
    {
        return Service::create([
            'name' => 'AI — Basic/Sync Plan',
            'slug' => 'ai-basicsync-plan',
            'category' => 'breeding',
            'type' => 'protocol',
            'price_rule' => [],
        ]);
    }

    #[DataProvider('ineligibleTypes')]
    public function test_bulls_and_steers_are_blocked_from_breeding_with_a_clear_message(AnimalType $type): void
    {
        $cattle = $this->makeCattle($type);
        $service = $this->breedingService();
        $gate = new BreedingEligibility;

        $this->assertFalse($gate->isEligible($cattle, $service));

        try {
            $gate->assertEligible($cattle, $service);
            $this->fail('Expected a BreedingEligibilityException.');
        } catch (BreedingEligibilityException $e) {
            $this->assertStringContainsString($type->label(), $e->getMessage());
            $this->assertStringContainsString('heifers and cows', $e->getMessage());
        }
    }

    public static function ineligibleTypes(): array
    {
        return ['bull' => [AnimalType::Bull], 'steer' => [AnimalType::Steer]];
    }

    #[DataProvider('eligibleTypes')]
    public function test_heifers_and_cows_are_allowed_on_a_breeding_service(AnimalType $type): void
    {
        $cattle = $this->makeCattle($type);

        $this->assertTrue((new BreedingEligibility)->isEligible($cattle, $this->breedingService()));
    }

    public static function eligibleTypes(): array
    {
        return ['heifer' => [AnimalType::Heifer], 'cow' => [AnimalType::Cow]];
    }

    public function test_non_breeding_service_is_never_blocked_even_for_a_bull(): void
    {
        $bull = $this->makeCattle(AnimalType::Bull);
        $weighing = Service::create([
            'name' => 'Weighing',
            'slug' => 'weighing',
            'category' => 'health',
            'type' => 'standard',
            'price_rule' => [],
        ]);

        $this->assertTrue((new BreedingEligibility)->isEligible($bull, $weighing));
    }

    public function test_on_call_heat_breeding_is_a_breeding_service_via_config(): void
    {
        $bull = $this->makeCattle(AnimalType::Bull);
        $oncall = Service::create([
            'name' => 'On-call heat breeding',
            'slug' => 'on-call-heat-breeding',
            'category' => 'breeding',
            'type' => 'oncall',
            'price_rule' => [],
        ]);

        $this->assertTrue($oncall->breedsAnimal());
        $this->assertFalse((new BreedingEligibility)->isEligible($bull, $oncall));
    }

    public function test_heifer_auto_promotes_to_cow_on_first_calving_not_by_age(): void
    {
        // Years old but never calved — still a heifer by physiology.
        $heifer = $this->makeCattle(AnimalType::Heifer, dob: '2021-01-01');

        $this->assertSame(AnimalType::Heifer, $heifer->animal_type);

        $heifer->recordCalving();

        $this->assertSame(AnimalType::Cow, $heifer->fresh()->animal_type);
        $this->assertTrue($heifer->fresh()->has_calved);
    }

    public function test_a_cow_stays_a_cow_when_she_calves_again(): void
    {
        $cow = $this->makeCattle(AnimalType::Cow);
        $cow->recordCalving();

        $this->assertSame(AnimalType::Cow, $cow->fresh()->animal_type);
    }

    public function test_age_stage_labels_derive_from_dob_at_boundaries(): void
    {
        $now = CarbonImmutable::parse('2026-06-01');

        $this->assertSame(AgeStage::Calf, AgeStage::fromDob($now->subMonths(5), $now));      // <6mo
        $this->assertSame(AgeStage::Weanling, AgeStage::fromDob($now->subMonths(8), $now));  // 6-12mo
        $this->assertSame(AgeStage::Adult, AgeStage::fromDob($now->subMonths(18), $now));    // >12mo
    }

    public function test_display_label_combines_age_stage_with_type(): void
    {
        $this->assertSame('heifer calf', AgeStage::Calf->label(AnimalType::Heifer));
        $this->assertSame('bull calf', AgeStage::Calf->label(AnimalType::Bull));
        $this->assertSame('weanling', AgeStage::Weanling->label(AnimalType::Heifer));
        $this->assertSame('cow', AgeStage::Adult->label(AnimalType::Cow));
    }
}
