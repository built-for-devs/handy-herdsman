<?php

namespace Database\Seeders;

use App\Models\DirectoryEntry;
use Illuminate\Database\Seeder;

/**
 * Starter local resource directory (§5.8). Staff curate this from the admin;
 * these seed rows give the public listing (and the "hoof trimming → directory"
 * trust-signal handoff) something to show on day one. Placeholder contacts.
 */
class DirectoryEntrySeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            ['vet', 'Brazos Valley Large Animal Vet', 'Waco, TX', 'https://example.com/brazos-vet', 'Large-animal practice for anything requiring a licensed vet.'],
            ['hoof_trimmer', 'Central Texas Hoof Care', 'McGregor, TX', 'https://example.com/ct-hoof', 'Mobile hoof trimming — the trimming we assess but do not perform.'],
            ['nutritionist', 'Heart of Texas Livestock Nutrition', 'Valley Mills, TX', 'https://example.com/hot-nutrition', 'Ration balancing and mineral programs.'],
            ['ai_tech', 'Bosque County AI Services', 'Clifton, TX', 'https://example.com/bosque-ai', 'Another AI tech for dates we cannot cover.'],
        ];

        foreach ($entries as [$category, $name, $area, $url, $notes]) {
            DirectoryEntry::updateOrCreate(
                ['name' => $name],
                compact('category', 'area', 'url', 'notes') + ['active' => true],
            );
        }
    }
}
