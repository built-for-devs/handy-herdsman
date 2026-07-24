<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = rtrim($this->faker->unique()->sentence(5), '.');

        return [
            'category_id' => Category::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => $this->faker->sentence(),
            'body' => '<p>'.$this->faker->paragraph().'</p>',
            'feature_image' => null,
            'meta_title' => $title,
            'meta_description' => $this->faker->sentence(),
            'old_url' => null,
            'published_at' => now()->subDays($this->faker->numberBetween(1, 200)),
        ];
    }

    /** Not yet published (draft or future-dated). */
    public function unpublished(): static
    {
        return $this->state(fn () => ['published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['published_at' => now()->addWeek()]);
    }
}
