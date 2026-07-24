<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Seeder;

/**
 * Blog pillars/categories (§5.3) + a couple of starter posts so the education
 * engine has content before the Ghost migration runs. The migration
 * (content:migrate-ghost) upserts real posts by slug on top of these.
 */
class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $pillars = [
            ['heat-detection', 'Heat Detection', 'Spotting standing heat — the make-or-break skill for natural-plan AI timing.'],
            ['ai-protocol', 'AI Protocol', 'How the CIDR sync protocol works and why the visit windows are fixed.'],
            ['nutrition', 'Nutrition', 'The #1 cause of AI failure. Body condition and feed programs that settle cows.'],
            ['health-testing', 'Health & Testing', 'Essential cattle health testing and non-vet health tasks for Central Texas herds.'],
            ['calving', 'Calving', 'Prep, timing and newborn-calf care around the due date.'],
        ];

        foreach ($pillars as [$slug, $name, $description]) {
            Category::updateOrCreate(['slug' => $slug], compact('name', 'description'));
        }

        $nutrition = Category::where('slug', 'nutrition')->first();
        $heat = Category::where('slug', 'heat-detection')->first();

        Post::updateOrCreate(
            ['slug' => 'feed-your-cow-right-preparing-for-ai-success'],
            [
                'category_id' => $nutrition?->id,
                'title' => 'Feed Your Cow Right: Preparing for AI Success',
                'excerpt' => 'Body condition is the single biggest lever on conception. Here is how to get a cow to breeding weight.',
                'body' => '<p>Overweight and underweight cows breed poorly. Aim for a body condition score of 5–6 at breeding...</p>',
                'meta_title' => 'Feed Your Cow Right: Preparing for AI Success',
                'meta_description' => 'How body condition scoring and a solid feed program set your cow up to settle on the first AI attempt.',
                'published_at' => now()->subDays(10),
            ]
        );

        Post::updateOrCreate(
            ['slug' => 'how-to-recognize-when-your-cow-is-in-heat'],
            [
                'category_id' => $heat?->id,
                'title' => 'How to Recognize When Your Cow Is in Heat',
                'excerpt' => 'Standing to be mounted is the one sure sign. Here are the secondary signs to watch for.',
                'body' => '<p>The single reliable sign of standing heat is that the cow stands to be mounted...</p>',
                'meta_title' => 'How to Recognize When Your Cow Is in Heat',
                'meta_description' => 'A practical guide to spotting standing heat so your natural-plan AI timing is right.',
                'published_at' => now()->subDays(4),
            ]
        );
    }
}
