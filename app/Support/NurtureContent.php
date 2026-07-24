<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Category;
use App\Models\Post;

/**
 * Nurture emails pull from the blog so content does double duty (§5.7, §5.3).
 * Given a template's `pillar`, this finds a recent published post in that
 * pillar/category and returns the payload snippet (title + url) the reminder
 * email links to. Returns an empty array when no matching post exists, so the
 * message still sends — just without a "read more" link.
 */
final class NurtureContent
{
    /**
     * @return array{post_title: string, post_url: string}|array{}
     */
    public function forPillar(?string $pillar): array
    {
        if ($pillar === null || $pillar === '') {
            return [];
        }

        $post = $this->latestPostForPillar($pillar) ?? $this->latestPost();

        if ($post === null) {
            return [];
        }

        return [
            'post_title' => (string) $post->title,
            'post_url' => url('/blog/'.$post->slug),
        ];
    }

    private function latestPostForPillar(string $pillar): ?Post
    {
        $categoryId = Category::query()
            ->where('name', 'like', '%'.$pillar.'%')
            ->orWhere('slug', 'like', '%'.str_replace(' ', '-', $pillar).'%')
            ->value('id');

        if ($categoryId === null) {
            return null;
        }

        return Post::query()
            ->published()
            ->where('category_id', $categoryId)
            ->latest('published_at')
            ->first();
    }

    private function latestPost(): ?Post
    {
        return Post::query()->published()->latest('published_at')->first();
    }
}
