<?php

declare(strict_types=1);

namespace Tests\Feature\Cattle;

use App\Models\Cattle;
use App\Models\Media;
use App\Models\Team;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * Photo/media uploads (§228, §10b): clients upload to profiles; staff upload at
 * appointments and may link the photo to a visit. Media anchors to the animal
 * and stores metadata (uploader, taken_at, visit_id). Staff-uploaded media is
 * Jeff's record — clients cannot delete it.
 */
class MediaUploadTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake(config('records.media_disk'));
    }

    public function test_client_uploads_a_photo_to_an_animal(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $owner->forceFill(['current_team_id' => $team->id])->save();
        $cattle = Cattle::factory()->for($team)->create();

        $this->actingAs($owner)
            ->post(route('cattle.media.store', $cattle), [
                'photo' => UploadedFile::fake()->image('bessie.jpg'),
                'caption' => 'For sale listing',
            ])
            ->assertRedirect();

        $media = Media::query()->firstOrFail();
        $this->assertSame($cattle->id, $media->mediable_id);
        $this->assertSame(Cattle::class, $media->mediable_type);
        $this->assertNull($media->visit_id);
        $this->assertSame('owner', $media->uploaded_role);
        $this->assertSame($owner->id, $media->uploaded_by);
        $this->assertNotNull($media->taken_at);
        Storage::disk(config('records.media_disk'))->assertExists($media->path);
    }

    public function test_staff_upload_links_the_photo_to_a_visit(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $cattle = Cattle::factory()->for($team)->create();
        $visit = Visit::factory()->for($team)->create(['cattle_id' => $cattle->id]);
        $staff = $this->makeStaff(User::factory()->create());
        $staff->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($staff)
            ->post(route('cattle.media.store', $cattle), [
                'photo' => UploadedFile::fake()->image('healing.jpg'),
                'visit_id' => $visit->id,
            ])
            ->assertRedirect();

        $media = Media::query()->firstOrFail();
        $this->assertSame($visit->id, $media->visit_id);
        $this->assertSame('staff', $media->uploaded_role);
        $this->assertTrue($media->visit->is($visit));
    }

    public function test_client_cannot_delete_staff_uploaded_media(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $owner->forceFill(['current_team_id' => $team->id])->save();
        $cattle = Cattle::factory()->for($team)->create();

        $media = Media::factory()->forCattle($cattle)->create(['uploaded_role' => 'staff']);

        $this->actingAs($owner)
            ->delete(route('cattle.media.destroy', $media))
            ->assertForbidden();

        $this->assertNotSoftDeleted($media);
    }

    public function test_client_can_delete_their_own_media(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $owner->forceFill(['current_team_id' => $team->id])->save();
        $cattle = Cattle::factory()->for($team)->create();

        $media = Media::factory()->forCattle($cattle)->create(['uploaded_role' => 'owner']);

        $this->actingAs($owner)
            ->delete(route('cattle.media.destroy', $media))
            ->assertRedirect();

        $this->assertSoftDeleted($media);
    }

    public function test_visit_from_another_team_cannot_be_linked(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $owner->forceFill(['current_team_id' => $team->id])->save();
        $cattle = Cattle::factory()->for($team)->create();

        $otherVisit = Visit::factory()->create(); // different team

        $this->actingAs($owner)
            ->post(route('cattle.media.store', $cattle), [
                'photo' => UploadedFile::fake()->image('x.jpg'),
                'visit_id' => $otherVisit->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, Media::query()->count());
    }

    public function test_outsider_cannot_view_media(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $cattle = Cattle::factory()->for($team)->create();
        $media = Media::factory()->forCattle($cattle)->create(['uploaded_role' => 'owner']);

        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('cattle.media.show', $media))
            ->assertForbidden();
    }
}
