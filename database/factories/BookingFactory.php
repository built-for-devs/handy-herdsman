<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $team = Team::factory();

        return [
            'team_id' => $team,
            'service_id' => Service::factory()->standard(),
            'proposed_start' => now()->addDays(3),
            'status' => BookingStatus::Provisional->value,
            'requires_review' => true,
            'is_oncall' => false,
            'distance_fee_flag' => false,
            'is_cash' => false,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Confirmed->value,
            'requires_review' => false,
            'reviewed_at' => now(),
        ]);
    }
}
