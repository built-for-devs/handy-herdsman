<?php

namespace Tests\Feature\Admin;

use App\Models\DirectoryEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DirectoryEntryTest extends TestCase
{
    use RefreshDatabase;

    private function staffUser(): User
    {
        // Global staff role (team_id null), assigned under a concrete team
        // context so the model_has_roles pivot gets its required team_id.
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        Role::findOrCreate('staff', 'web');

        $user = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);
        $user->assignRole('staff');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return $user;
    }

    public function test_guests_cannot_reach_the_admin_directory(): void
    {
        $this->get('/admin/directory')->assertRedirect('/login');
    }

    public function test_non_staff_users_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/directory')
            ->assertForbidden();
    }

    public function test_staff_can_list_entries(): void
    {
        DirectoryEntry::factory()->create(['name' => 'Listed Resource']);

        $this->actingAs($this->staffUser())
            ->get('/admin/directory')
            ->assertOk();
    }

    public function test_staff_can_create_an_entry(): void
    {
        $this->actingAs($this->staffUser())
            ->post('/admin/directory', [
                'category' => 'nutritionist',
                'name' => 'New Nutritionist',
                'area' => 'Waco, TX',
                'url' => 'https://example.com',
                'notes' => 'Great rations.',
                'active' => true,
            ])
            ->assertRedirect('/admin/directory');

        $this->assertDatabaseHas('directory_entries', ['name' => 'New Nutritionist']);
    }

    public function test_create_validates_the_category(): void
    {
        $this->actingAs($this->staffUser())
            ->post('/admin/directory', ['category' => 'not-a-category', 'name' => 'X'])
            ->assertSessionHasErrors('category');
    }

    public function test_staff_can_update_an_entry(): void
    {
        $entry = DirectoryEntry::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->staffUser())
            ->put("/admin/directory/{$entry->id}", [
                'category' => $entry->category,
                'name' => 'Updated Name',
                'active' => true,
            ])
            ->assertRedirect('/admin/directory');

        $this->assertDatabaseHas('directory_entries', ['id' => $entry->id, 'name' => 'Updated Name']);
    }

    public function test_destroy_soft_deletes_and_restore_brings_it_back(): void
    {
        $entry = DirectoryEntry::factory()->create();
        $staff = $this->staffUser();

        $this->actingAs($staff)->delete("/admin/directory/{$entry->id}")->assertRedirect('/admin/directory');
        $this->assertSoftDeleted('directory_entries', ['id' => $entry->id]);

        $this->actingAs($staff)->put("/admin/directory/{$entry->id}/restore")->assertRedirect('/admin/directory');
        $this->assertDatabaseHas('directory_entries', ['id' => $entry->id, 'deleted_at' => null]);
    }
}
