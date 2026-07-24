<?php

declare(strict_types=1);

namespace Tests\Feature\Team;

use App\Models\Cattle;
use App\Models\HealthRecord;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * Vet read-only scoping (spec §4, §10b). A vet sees only the client-selected
 * profile and/or record types, and can never write.
 */
class VetReadOnlyScopeTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function vetFor(Team $team, array $scope): User
    {
        $vet = User::factory()->create();
        TeamInvitation::factory()->for($team)->accepted()->vet($scope)->create([
            'email' => $vet->email,
            'accepted_by' => $vet->id,
        ]);

        return $vet;
    }

    public function test_owner_has_full_access(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $cattle = Cattle::factory()->for($team)->create();

        $this->assertTrue($owner->can('view', $cattle));
        $this->assertTrue($owner->can('update', $cattle));
        $this->assertTrue($owner->can('delete', $cattle));
    }

    public function test_staff_bypasses_tenant_scoping(): void
    {
        $staff = $this->makeStaff(User::factory()->create());
        $team = Team::factory()->create();
        $cattle = Cattle::factory()->for($team)->create();

        $this->assertTrue($staff->can('view', $cattle));
        $this->assertTrue($staff->can('update', $cattle));
    }

    public function test_vet_with_profile_scope_can_view_cattle(): void
    {
        $team = Team::factory()->create();
        $cattle = Cattle::factory()->for($team)->create();
        $vet = $this->vetFor($team, ['profile' => true, 'record_types' => []]);

        $this->assertTrue($vet->can('view', $cattle));
    }

    public function test_vet_without_profile_scope_cannot_view_cattle(): void
    {
        $team = Team::factory()->create();
        $cattle = Cattle::factory()->for($team)->create();
        $vet = $this->vetFor($team, ['profile' => false, 'record_types' => ['vaccination']]);

        $this->assertFalse($vet->can('view', $cattle));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function recordScopeProvider(): array
    {
        return [
            'in-scope type is visible' => ['vaccination', true],
            'out-of-scope type is hidden' => ['treatment', false],
        ];
    }

    #[DataProvider('recordScopeProvider')]
    public function test_vet_only_sees_records_in_the_selected_scope(string $type, bool $canView): void
    {
        $team = Team::factory()->create();
        $cattle = Cattle::factory()->for($team)->create();
        $record = HealthRecord::factory()->for($team)->create([
            'cattle_id' => $cattle->id,
            'type' => $type,
        ]);

        $vet = $this->vetFor($team, ['profile' => true, 'record_types' => ['vaccination']]);

        $this->assertSame($canView, $vet->can('view', $record));
    }

    public function test_vet_can_never_write(): void
    {
        $team = Team::factory()->create();
        $cattle = Cattle::factory()->for($team)->create();
        $record = HealthRecord::factory()->for($team)->create(['cattle_id' => $cattle->id, 'type' => 'vaccination']);
        $vet = $this->vetFor($team, ['profile' => true, 'record_types' => ['vaccination']]);

        $this->assertFalse($vet->can('create', $cattle));
        $this->assertFalse($vet->can('update', $cattle));
        $this->assertFalse($vet->can('delete', $cattle));
        $this->assertFalse($vet->can('update', $record));
        $this->assertFalse($vet->can('delete', $record));
    }
}
