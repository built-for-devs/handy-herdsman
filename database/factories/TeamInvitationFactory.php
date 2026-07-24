<?php

namespace Database\Factories;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamInvitation>
 */
class TeamInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => TeamRole::Member,
            'token' => TeamInvitation::generateToken(),
            'status' => 'pending',
            'vet_scope' => null,
        ];
    }

    public function member(): static
    {
        return $this->state(fn () => ['role' => TeamRole::Member, 'vet_scope' => null]);
    }

    public function vet(array $scope = ['profile' => true, 'record_types' => []]): static
    {
        return $this->state(fn () => ['role' => TeamRole::Vet, 'vet_scope' => $scope]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => 'accepted', 'accepted_at' => now()]);
    }
}
