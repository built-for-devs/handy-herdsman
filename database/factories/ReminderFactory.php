<?php

namespace Database\Factories;

use App\Models\Cattle;
use App\Models\Reminder;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'remindable_type' => Cattle::class,
            'remindable_id' => Cattle::factory(),
            'fire_at' => now()->addDays(3),
            'category' => 1,
            'channel' => 'both',
            'template' => 'reminder.generic',
            'recipient_role' => 'owner',
            'status' => 'pending',
        ];
    }

    /** Attach this reminder to a specific animal (polymorphic remindable). */
    public function forCattle(Cattle $cattle): static
    {
        return $this->state(fn () => [
            'team_id' => $cattle->team_id,
            'remindable_type' => $cattle::class,
            'remindable_id' => $cattle->id,
        ]);
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function category(int $category): static
    {
        return $this->state(fn () => ['category' => $category]);
    }

    public function template(string $template): static
    {
        return $this->state(fn () => ['template' => $template]);
    }

    public function fireAt(\DateTimeInterface $fireAt): static
    {
        return $this->state(fn () => ['fire_at' => $fireAt]);
    }
}
