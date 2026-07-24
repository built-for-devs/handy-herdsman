<?php

namespace Database\Factories;

use App\Models\SemenInventory;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SemenInventory>
 */
class SemenInventoryFactory extends Factory
{
    protected $model = SemenInventory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'sire' => fake()->lastName(),
            'breed' => fake()->randomElement(['Angus', 'Hereford', 'Brahman', 'Charolais']),
            'straws_count' => 0,
            'source' => SemenInventory::SOURCE_CLIENT,
            'location' => 'Tank A / Canister 1',
            'owned_by' => SemenInventory::OWNER_CLIENT,
            'with_ai' => false,
        ];
    }

    /** Client-sourced lot: the client ships us straws using our intake info. */
    public function clientSourced(): static
    {
        return $this->state(fn () => [
            'source' => SemenInventory::SOURCE_CLIENT,
            'intake_shipping_address' => fake()->streetAddress().', Valley Mills, TX',
            'tank_details' => 'MVE XC 20 dry shipper',
            'expected_arrival' => now()->addDays(5)->toDateString(),
        ]);
    }

    /** Jeff-sourced lot: Jeff orders on the client's behalf. */
    public function jeffSourced(): static
    {
        return $this->state(fn () => [
            'source' => SemenInventory::SOURCE_JEFF,
            'source_farm' => fake()->company().' Genetics',
            'bull_info' => fake()->lastName().' — reg #'.fake()->numerify('########'),
            'source_contact' => fake()->name().' '.fake()->phoneNumber(),
            'pay_to' => fake()->company(),
        ]);
    }

    /** Lot paired with an AI plan (storage free in year 1). */
    public function withAi(): static
    {
        return $this->state(fn () => ['with_ai' => true]);
    }
}
