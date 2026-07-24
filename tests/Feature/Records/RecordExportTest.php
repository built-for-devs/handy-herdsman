<?php

declare(strict_types=1);

namespace Tests\Feature\Records;

use App\Models\Cattle;
use App\Models\HealthRecord;
use App\Models\Media;
use App\Models\Team;
use App\Models\User;
use App\Services\Records\RecordExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * Data export (§229, §10b). Clients export their records anytime; the export is
 * scoped strictly to the requesting client's team — never another client's data.
 */
class RecordExportTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_exporter_scopes_to_the_given_team_only(): void
    {
        $team = Team::factory()->create();
        $mine = Cattle::factory()->for($team)->create(['reg_name' => 'Mine']);
        HealthRecord::factory()->for($team)->create(['cattle_id' => $mine->id, 'type' => 'vaccination']);
        Media::factory()->forCattle($mine)->create();

        $otherTeam = Team::factory()->create();
        $theirs = Cattle::factory()->for($otherTeam)->create(['reg_name' => 'Theirs']);
        HealthRecord::factory()->for($otherTeam)->create(['cattle_id' => $theirs->id]);
        Media::factory()->forCattle($theirs)->create();

        $data = app(RecordExporter::class)->forTeam($team);

        $this->assertCount(1, $data['cattle']);
        $this->assertSame('Mine', $data['cattle'][0]['reg_name']);
        $this->assertCount(1, $data['health_records']);
        $this->assertCount(1, $data['media']);
        $this->assertSame($team->id, $data['team']['id']);
    }

    public function test_json_download_returns_only_the_requesting_teams_data(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $owner->forceFill(['current_team_id' => $team->id])->save();
        Cattle::factory()->for($team)->create(['reg_name' => 'Mine']);

        $otherTeam = Team::factory()->create();
        Cattle::factory()->for($otherTeam)->create(['reg_name' => 'Theirs']);

        $response = $this->actingAs($owner)->get(route('records.export.download', ['format' => 'json']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
        $payload = json_decode($response->streamedContent(), true);

        $this->assertCount(1, $payload['cattle']);
        $this->assertSame('Mine', $payload['cattle'][0]['reg_name']);
    }

    public function test_csv_download_returns_a_zip(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $owner->forceFill(['current_team_id' => $team->id])->save();
        Cattle::factory()->for($team)->create();

        $response = $this->actingAs($owner)->get(route('records.export.download', ['format' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/zip');
    }

    public function test_user_without_a_team_cannot_export(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);

        $this->actingAs($user)
            ->get(route('records.export.download', ['format' => 'json']))
            ->assertForbidden();
    }

    public function test_artisan_command_writes_a_team_scoped_json_export(): void
    {
        Storage::fake('local');

        $team = Team::factory()->create();
        Cattle::factory()->for($team)->create(['reg_name' => 'Mine']);
        Cattle::factory()->create(['reg_name' => 'Theirs']); // other team

        $this->artisan('records:export', ['team' => $team->id])->assertSuccessful();

        $files = Storage::disk('local')->allFiles("exports/team-{$team->id}");
        $this->assertCount(1, $files);

        $payload = json_decode(Storage::disk('local')->get($files[0]), true);
        $this->assertCount(1, $payload['cattle']);
        $this->assertSame('Mine', $payload['cattle'][0]['reg_name']);
    }
}
