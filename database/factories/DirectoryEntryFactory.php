<?php

namespace Database\Factories;

use App\Models\DirectoryEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DirectoryEntry>
 */
class DirectoryEntryFactory extends Factory
{
    protected $model = DirectoryEntry::class;

    public function definition(): array
    {
        return [
            'category' => $this->faker->randomElement(['vet', 'nutritionist', 'hoof_trimmer', 'ai_tech']),
            'name' => $this->faker->company(),
            'area' => $this->faker->city().', TX',
            'url' => $this->faker->url(),
            'notes' => $this->faker->sentence(),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
