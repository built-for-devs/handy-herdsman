<?php

namespace Database\Factories;

use App\Models\Supply;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supply>
 */
class SupplyFactory extends Factory
{
    protected $model = Supply::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item' => fake()->unique()->randomElement([
                'CIDR', 'GnRH dose', 'Prostaglandin dose', 'AI sleeve', 'Needle 18g', 'Ear tag',
            ]).' '.fake()->unique()->numberBetween(1, 9999),
            'category' => fake()->randomElement(['drug', 'consumable', 'hardware']),
            'unit' => fake()->randomElement(['each', 'dose', 'ml']),
            'on_hand' => 100,
            'low_stock_threshold' => 10,
            'unit_cost' => fake()->randomFloat(2, 0.5, 25),
            'is_prescription' => false,
            'notes' => null,
        ];
    }

    /** Currently at/below its reorder threshold. */
    public function low(): static
    {
        return $this->state(fn () => ['on_hand' => 5, 'low_stock_threshold' => 10]);
    }
}
