<?php

declare(strict_types=1);

namespace Tests\Feature\Cattle;

use App\Models\Cattle;
use App\Models\HealthRecord;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * Records permission matrix (§10b): clients CANNOT edit/delete staff-added
 * records; Jeff (staff) CAN edit client-added records, with the change
 * attributed to him. Both directions are covered.
 */
class HealthRecordPermissionTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /**
     * actor role, record author role, can the actor modify it?
     *
     * @return array<string, array{string, string, bool}>
     */
    public static function matrix(): array
    {
        return [
            'client on own record' => ['owner', 'owner', true],
            'client on staff record' => ['owner', 'staff', false],
            'staff on client record' => ['staff', 'owner', true],
            'staff on staff record' => ['staff', 'staff', true],
        ];
    }

    #[DataProvider('matrix')]
    public function test_update_and_delete_permission_matrix(string $actorRole, string $authorRole, bool $allowed): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $cattle = Cattle::factory()->for($team)->create();

        $actor = $actorRole === 'staff' ? $this->makeStaff(User::factory()->create()) : $owner;

        $record = HealthRecord::factory()->for($team)->create([
            'cattle_id' => $cattle->id,
            'added_role' => $authorRole,
        ]);

        $this->assertSame($allowed, $actor->can('update', $record));
        $this->assertSame($allowed, $actor->can('delete', $record));
    }

    public function test_client_cannot_update_staff_record_over_http(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $owner->forceFill(['current_team_id' => $team->id])->save();
        $cattle = Cattle::factory()->for($team)->create();

        $record = HealthRecord::factory()->for($team)->create([
            'cattle_id' => $cattle->id,
            'type' => 'treatment',
            'added_role' => 'staff',
        ]);

        $this->actingAs($owner)
            ->put(route('records.update', $record), ['type' => 'general'])
            ->assertForbidden();

        $this->assertSame('treatment', $record->fresh()->type);
    }

    public function test_client_cannot_delete_staff_record_over_http(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $owner->forceFill(['current_team_id' => $team->id])->save();
        $cattle = Cattle::factory()->for($team)->create();

        $record = HealthRecord::factory()->for($team)->create([
            'cattle_id' => $cattle->id,
            'added_role' => 'staff',
        ]);

        $this->actingAs($owner)
            ->delete(route('records.destroy', $record))
            ->assertForbidden();

        $this->assertNotSoftDeleted($record);
    }

    public function test_staff_edit_of_client_record_is_attributed(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $cattle = Cattle::factory()->for($team)->create();
        $staff = $this->makeStaff(User::factory()->create());

        $record = HealthRecord::factory()->for($team)->create([
            'cattle_id' => $cattle->id,
            'type' => 'general',
            'added_by' => $owner->id,
            'added_role' => 'owner',
        ]);

        $this->actingAs($staff)
            ->put(route('records.update', $record), ['type' => 'treatment'])
            ->assertRedirect();

        $record->refresh();
        $this->assertSame('treatment', $record->type);
        // Original author preserved; editor attributed to the staff member (§10b).
        $this->assertSame('owner', $record->added_role);
        $this->assertSame($owner->id, $record->added_by);
        $this->assertSame('staff', $record->edited_role);
        $this->assertSame($staff->id, $record->edited_by);
        $this->assertNotNull($record->edited_at);
    }

    public function test_client_creates_record_attributed_to_them(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $owner->forceFill(['current_team_id' => $team->id])->save();
        $cattle = Cattle::factory()->for($team)->create();

        $this->actingAs($owner)
            ->post(route('cattle.records.store', $cattle), [
                'type' => 'body_condition',
                'bcs_score' => 5,
            ])
            ->assertRedirect();

        $record = HealthRecord::query()->firstOrFail();
        $this->assertSame('owner', $record->added_role);
        $this->assertSame($owner->id, $record->added_by);
        $this->assertSame(5, $record->bcs_score);
    }

    public function test_body_condition_requires_a_bcs_score(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $owner->forceFill(['current_team_id' => $team->id])->save();
        $cattle = Cattle::factory()->for($team)->create();

        $this->actingAs($owner)
            ->post(route('cattle.records.store', $cattle), ['type' => 'body_condition'])
            ->assertSessionHasErrors('bcs_score');
    }
}
