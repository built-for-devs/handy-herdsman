<?php

namespace Tests\Feature\Marketing;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_published_posts(): void
    {
        Post::factory()->create(['title' => 'Published Post']);
        Post::factory()->unpublished()->create(['title' => 'Draft Post']);
        Post::factory()->scheduled()->create(['title' => 'Scheduled Post']);

        $this->get('/blog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('marketing/blog/Index')
                ->has('posts', 1)
                ->where('posts.0.title', 'Published Post')
                ->has('meta.title')
                ->has('meta.description')
            );
    }

    public function test_category_page_lists_its_published_posts(): void
    {
        $category = Category::factory()->create(['slug' => 'ai-protocol', 'name' => 'AI Protocol']);
        Post::factory()->for($category)->create(['title' => 'In Category']);
        Post::factory()->create(['title' => 'Other Category']);

        $this->get('/blog/category/ai-protocol')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('marketing/blog/Category')
                ->where('category.slug', 'ai-protocol')
                ->has('posts', 1)
                ->where('posts.0.title', 'In Category')
                ->has('meta.title')
            );
    }

    public function test_post_page_exposes_seo_meta_and_json_ld(): void
    {
        Post::factory()->create([
            'title' => 'SEO Post',
            'slug' => 'seo-post',
            'meta_title' => 'Custom Meta Title',
            'meta_description' => 'Custom meta description.',
        ]);

        $this->get('/blog/seo-post')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('marketing/blog/Show')
                ->where('post.meta_title', 'Custom Meta Title')
                ->where('meta.description', 'Custom meta description.')
                ->where('jsonLd.@type', 'BlogPosting')
                ->where('jsonLd.headline', 'SEO Post')
                ->has('jsonLd.datePublished')
            );
    }

    public function test_unpublished_post_returns_404(): void
    {
        Post::factory()->unpublished()->create(['slug' => 'hidden-post']);

        $this->get('/blog/hidden-post')->assertNotFound();
    }

    public function test_clean_slug_routing_uses_the_slug(): void
    {
        Post::factory()->create(['slug' => 'a-clean-readable-slug']);

        $this->get('/blog/a-clean-readable-slug')->assertOk();
    }
}
