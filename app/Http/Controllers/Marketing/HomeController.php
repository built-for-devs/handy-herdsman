<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Service;
use App\Support\PriceRuleFormatter;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Home — brand intro, prominent AI Timing Calculator placement (the primary
     * lead magnet), a few featured services and latest posts (§5.1).
     */
    public function __invoke(): Response
    {
        $featured = Service::where('active', true)
            ->whereIn('category', ['breeding', 'calving'])
            ->orderBy('sort_order')
            ->take(6)
            ->get()
            ->map(fn (Service $s) => [
                'name' => $s->name,
                'slug' => $s->slug,
                'description' => $s->description,
                'price' => PriceRuleFormatter::display($s->price_rule),
            ]);

        $posts = Post::published()
            ->latest('published_at')
            ->take(3)
            ->get(['title', 'slug', 'excerpt', 'published_at']);

        return Inertia::render('marketing/Home', [
            'brand' => config('marketing.brand'),
            'tagline' => config('marketing.tagline'),
            'featuredServices' => $featured,
            'latestPosts' => $posts,
        ]);
    }
}
