<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    /** Blog index — published posts + pillar/category list, SEO meta (§5.3). */
    public function index(): Response
    {
        $posts = Post::published()
            ->with('category:id,name,slug')
            ->latest('published_at')
            ->get(['id', 'category_id', 'title', 'slug', 'excerpt', 'feature_image', 'published_at']);

        return Inertia::render('marketing/blog/Index', [
            'posts' => $posts->map(fn (Post $p) => $this->summary($p))->values(),
            'categories' => Category::orderBy('name')->get(['name', 'slug', 'description']),
            'meta' => [
                'title' => 'Cattle AI & Herd Education — Handy Herdsman Blog',
                'description' => 'Practical, no-nonsense education on heat detection, AI protocols, cattle nutrition, health testing and calving from a Central Texas AI tech.',
            ],
        ]);
    }

    /** Pillar/category page — posts within one blog pillar (§5.3). */
    public function category(Category $category): Response
    {
        $posts = $category->posts()->published()
            ->latest('published_at')
            ->get(['id', 'category_id', 'title', 'slug', 'excerpt', 'feature_image', 'published_at']);

        return Inertia::render('marketing/blog/Category', [
            'category' => $category->only(['name', 'slug', 'description']),
            'posts' => $posts->map(fn (Post $p) => $this->summary($p))->values(),
            'meta' => [
                'title' => $category->name.' — Handy Herdsman Blog',
                'description' => $category->description ?: "Articles on {$category->name} from Handy Herdsman.",
            ],
        ]);
    }

    /** Individual post — clean slug, meta tags, Article JSON-LD schema (§5.3). */
    public function show(Post $post): Response|RedirectResponse
    {
        abort_if($post->published_at === null || $post->published_at->isFuture(), 404);

        $post->load('category:id,name,slug');

        return Inertia::render('marketing/blog/Show', [
            'post' => [
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'body' => $post->body,
                'feature_image' => $post->feature_image,
                'published_at' => $post->published_at?->toIso8601String(),
                'category' => $post->category?->only(['name', 'slug']),
                'meta_title' => $post->meta_title ?: $post->title,
                'meta_description' => $post->meta_description,
            ],
            'meta' => [
                'title' => ($post->meta_title ?: $post->title).' — Handy Herdsman',
                'description' => $post->meta_description ?: $post->excerpt,
            ],
            'jsonLd' => $this->articleSchema($post),
        ]);
    }

    /** @return array<string, mixed> */
    private function summary(Post $post): array
    {
        return [
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'feature_image' => $post->feature_image,
            'published_at' => $post->published_at?->toIso8601String(),
            'category' => $post->category?->only(['name', 'slug']),
        ];
    }

    /** @return array<string, mixed> */
    private function articleSchema(Post $post): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post->title,
            'description' => $post->meta_description ?: $post->excerpt,
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => $post->updated_at?->toIso8601String(),
            'image' => $post->feature_image ? url($post->feature_image) : null,
            'url' => route('blog.show', $post->slug),
            'mainEntityOfPage' => route('blog.show', $post->slug),
            'author' => ['@type' => 'Organization', 'name' => config('marketing.brand')],
            'publisher' => ['@type' => 'Organization', 'name' => config('marketing.brand')],
        ]);
    }
}
