<?php

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'contact_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('254-###-####'),
            'address_line1' => fake()->streetAddress(),
            'city' => 'Valley Mills',
            'state' => 'TX',
            'postal_code' => '76689',
            'status' => ClientStatus::New,
            'channel_prefs' => Client::defaultChannelPrefs(),
            'consent_sms' => [],
            'last_activity_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => ClientStatus::Active]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => ClientStatus::Inactive]);
    }
}
