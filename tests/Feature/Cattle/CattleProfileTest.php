<?php

declare(strict_types=1);

namespace Tests\Feature\Cattle;

use App\Models\Cattle;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * Cattle profiles CRUD (§5.5, §10b). Full profile management; nothing hard-
 * deletes (soft-delete only); tenant isolation across teams.
 */
class CattleProfileTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function ownerWithTeam(): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        return [$owner, $team];
    }

    public function test_owner_creates_a_profile_scoped_to_their_team(): void
    {
        [$owner, $team] = $this->ownerWithTeam();

        $this->actingAs($owner)
            ->post(route('cattle.store'), [
                'reg_name' => 'Bessie',
                'animal_type' => 'heifer',
                'breed' => 'Angus',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cattle', [
            'reg_name' => 'Bessie',
            'animal_type' => 'heifer',
            'team_id' => $team->id,
        ]);
    }

    public function test_store_rejects_an_invalid_animal_type(): void
    {
        [$owner] = $this->ownerWithTeam();

        $this->actingAs($owner)
            ->post(route('cattle.store'), ['reg_name' => 'X', 'animal_type' => 'calf'])
            ->assertSessionHasErrors('animal_type');
    }

    public function test_destroy_soft_deletes_only(): void
    {
        [$owner, $team] = $this->ownerWithTeam();
        $cattle = Cattle::factory()->for($team)->create();

        $this->actingAs($owner)
            ->delete(route('cattle.destroy', $cattle))
            ->assertRedirect(route('cattle.index'));

        $this->assertSoftDeleted($cattle);
    }

    public function test_owner_cannot_view_another_teams_animal(): void
    {
        [$owner] = $this->ownerWithTeam();
        $otherCattle = Cattle::factory()->create(); // different team

        $this->actingAs($owner)
            ->get(route('cattle.show', $otherCattle))
            ->assertForbidden();
    }

    public function test_index_lists_only_the_current_teams_animals(): void
    {
        [$owner, $team] = $this->ownerWithTeam();
        Cattle::factory()->for($team)->create(['reg_name' => 'Mine']);
        Cattle::factory()->create(['reg_name' => 'Theirs']);

        $this->actingAs($owner)
            ->get(route('cattle.index'))
            ->assertInertia(fn ($page) => $page
                ->component('cattle/Index')
                ->has('cattle', 1)
                ->where('cattle.0.reg_name', 'Mine'));
    }
}
