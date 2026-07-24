<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Cattle;
use App\Models\Client;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\AvailabilitySeeder;
use Database\Seeders\RateConfigSeeder;
use Database\Seeders\ServiceAreaSeeder;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shared scaffolding for M6 booking tests: seeds the editable config the
 * scheduler reads and builds client teams / staff / cattle.
 */
trait BuildsBookings
{
    protected function seedBookingConfig(): void
    {
        $this->seed([
            AvailabilitySeeder::class,
            ServiceAreaSeeder::class,
            RateConfigSeeder::class,
        ]);
    }

    /**
     * A team with a client profile in the given status and an owner user.
     *
     * @return array{0: Team, 1: User, 2: Client}
     */
    protected function clientTeam(string $status = 'active', array $clientAttributes = []): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        $client = Client::factory()->for($team)->create(array_merge(
            ['status' => $status],
            $clientAttributes,
        ));

        return [$team, $owner, $client];
    }

    protected function staffUser(): User
    {
        $user = User::factory()->create([
            'is_staff' => true,
            'phone' => '254-555-0100',
            'email_verified_at' => now(),
        ]);

        // EnsureStaff gates admin screens on the Spatie 'staff' role (global,
        // team-null context), separate from the is_staff bypass flag.
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId(1);
        $user->assignRole('staff');
        $registrar->setPermissionsTeamId($previous);

        return $user;
    }

    /**
     * @return Collection<int, Cattle>
     */
    protected function cattleFor(Team $team, string $animalType = 'cow', int $count = 1)
    {
        return Cattle::factory()->count($count)->for($team)->create([
            'animal_type' => $animalType,
            'has_calved' => $animalType === 'cow',
        ]);
    }

    /** A weekday afternoon instant so a whole protocol chain stays bookable. */
    protected function nextWorkingV1(int $hour = 16): CarbonImmutable
    {
        $tz = (string) config('protocol.timezone');
        $date = CarbonImmutable::now($tz)->addDays(3)->setTime($hour, 0);

        // Avoid Sunday (0) and Monday-following DST edge irrelevant here.
        while (in_array($date->dayOfWeek, [CarbonImmutable::SUNDAY], true)) {
            $date = $date->addDay();
        }

        return $date;
    }
}
