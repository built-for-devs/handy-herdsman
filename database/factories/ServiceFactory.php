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

    /** The CIDR 10-day sync plan — the 3-visit protocol scheduler (§3). */
    public function syncPlan(): static
    {
        return $this->state(fn () => [
            'name' => 'AI — Basic/Sync Plan',
            'slug' => 'ai-basic-sync-plan',
            'category' => 'breeding',
            'type' => 'protocol',
            'price_rule' => ['model' => 'flat', 'price' => 300, 'farm_calls' => 3, 'included_cows' => 2, 'per_additional_cow' => 100],
        ]);
    }

    /** The Natural plan — a single timed breeding farm call (§5.2). */
    public function naturalPlan(): static
    {
        return $this->state(fn () => [
            'name' => 'AI — Natural Plan',
            'slug' => 'ai-natural-plan',
            'category' => 'breeding',
            'type' => 'protocol',
            'price_rule' => ['model' => 'flat', 'price' => 100, 'farm_calls' => 1],
        ]);
    }

    /** An expedited text-now on-call service (standing heat / emergency, §5.5). */
    public function oncall(): static
    {
        return $this->state(fn () => [
            'name' => 'On-call heat breeding',
            'slug' => 'on-call-heat-breeding',
            'category' => 'breeding',
            'type' => 'oncall',
            'price_rule' => ['model' => 'flat', 'price' => 100],
        ]);
    }

    /** A single-visit standard service (§5.5). */
    public function standard(): static
    {
        return $this->state(fn () => [
            'category' => 'consult',
            'type' => 'standard',
            'price_rule' => ['model' => 'flat', 'price' => 75],
        ]);
    }

    /** A per-head standard service with a single-visit minimum (§5.2b, §10b). */
    public function perHead(int $perHead = 10, int $visitMinimum = 50): static
    {
        return $this->state(fn () => [
            'category' => 'health',
            'type' => 'standard',
            'price_rule' => ['model' => 'per_head', 'per_head' => $perHead, 'visit_minimum' => $visitMinimum],
        ]);
    }
}
