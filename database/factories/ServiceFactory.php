<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'category' => 'breeding',
            'type' => 'protocol',
            'price_rule' => ['model' => 'flat', 'price' => 100],
            'requires_first_time_review' => true,
            'requires_containment' => true,
            'active' => true,
            'sort_order' => 0,
        ];
    }
}
