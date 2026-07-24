<?php

namespace Database\Factories;

use App\Models\Cattle;
use App\Models\Media;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mediable_type' => Cattle::class,
            'mediable_id' => Cattle::factory(),
            'uploaded_role' => 'owner',
            'path' => 'media/'.fake()->uuid().'.jpg',
            'caption' => fake()->optional()->sentence(),
            'taken_at' => now(),
        ];
    }

    /** Anchor the media to a specific animal. */
    public function forCattle(Cattle $cattle): static
    {
        return $this->state(fn () => [
            'mediable_type' => $cattle::class,
            'mediable_id' => $cattle->id,
        ]);
    }

    /** Link the media to a visit (staff appointment upload). */
    public function forVisit(Visit $visit): static
    {
        return $this->state(fn () => ['visit_id' => $visit->id]);
    }
}
