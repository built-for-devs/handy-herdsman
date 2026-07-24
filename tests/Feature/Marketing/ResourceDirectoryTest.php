<?php

namespace Tests\Feature\Marketing;

use App\Models\DirectoryEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResourceDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_directory_shows_active_entries_grouped_by_category(): void
    {
        DirectoryEntry::factory()->create(['category' => 'vet', 'name' => 'Good Vet', 'active' => true]);
        DirectoryEntry::factory()->inactive()->create(['category' => 'vet', 'name' => 'Hidden Vet']);

        $this->get('/resources/directory')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('marketing/ResourceDirectory')
                ->has('groups', 1)
                ->where('groups.0.category', 'vet')
                ->where('groups.0.label', 'Veterinarians')
                ->has('groups.0.entries', 1)
                ->where('groups.0.entries.0.name', 'Good Vet')
                ->has('meta.title')
            );
    }
}
