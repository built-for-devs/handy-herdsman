<?php

namespace App\Support\GhostExport;

use Illuminate\Support\Str;

/**
 * Pure parser for a Ghost export JSON (spec §8, §10b). Isolated from all I/O so
 * the filter/transform logic is unit-testable against a small fixture without
 * touching the network or filesystem — the migration command does the actual
 * downloading/seeding.
 *
 * Migrate ONLY the listed cattle/AI education posts; leave milk/dairy/donkey/
 * recipe/herd-share content behind with Homestead Herds.
 */
class GhostExportParser
{
    /**
     * Slugs of the posts to migrate (§8). Matched against each post's own slug
     * and against a slug derived from its title, so either shape works.
     *
     * @var list<string>
     */
    public const ALLOWED_SLUGS = [
        'how-to-recognize-when-your-cow-is-in-heat',
        'feed-your-cow-right-preparing-for-ai-success',
        'understanding-your-ai-protocol',
        'live-bulls-vs-artificial-insemination',
        'cattle-ai-services',
        'cattle-breeding-via-ai-services',
        'ketosis-in-milk-cows',
        'essential-cattle-health-testing-guide-for-central-texas',
        'faq',
    ];

    /**
     * Substrings that mark a post as belonging to Homestead Herds, never
     * migrated even if it slips past the allowlist (defense in depth, §8).
     *
     * @var list<string>
     */
    public const BLOCKED_KEYWORDS = [
        'raw-milk', 'raw milk', 'recipe', 'donkey', 'herd-share', 'herd share',
        'farm-club', 'farm club', 'cheese', 'yogurt', 'butter', 'for-sale', 'for sale',
    ];

    /** Keyword → blog pillar/category slug, for auto-assigning a category (§5.3). */
    private const CATEGORY_KEYWORDS = [
        'heat' => 'heat-detection',
        'protocol' => 'ai-protocol',
        'insemination' => 'ai-protocol',
        'breeding' => 'ai-protocol',
        'feed' => 'nutrition',
        'ketosis' => 'nutrition',
        'nutrition' => 'nutrition',
        'testing' => 'health-testing',
        'health' => 'health-testing',
        'calving' => 'calving',
        'calf' => 'calving',
    ];

    /**
     * Extract the raw posts array from a decoded Ghost export.
     *
     * @param  array<string, mixed>  $export
     * @return list<array<string, mixed>>
     */
    public static function posts(array $export): array
    {
        $db = $export['db'][0]['data']['posts'] ?? $export['data']['posts'] ?? $export['posts'] ?? [];

        return array_values(array_filter($db, 'is_array'));
    }

    /**
     * Whether a Ghost post should be migrated to Handy Herdsman.
     *
     * @param  array<string, mixed>  $post
     */
    public static function shouldMigrate(array $post): bool
    {
        $slug = (string) ($post['slug'] ?? '');
        $title = (string) ($post['title'] ?? '');
        $haystack = Str::lower($slug.' '.$title);

        foreach (self::BLOCKED_KEYWORDS as $blocked) {
            if (str_contains($haystack, $blocked)) {
                return false;
            }
        }

        $candidateSlugs = array_filter([$slug, Str::slug($title)]);

        foreach ($candidateSlugs as $candidate) {
            if (in_array($candidate, self::ALLOWED_SLUGS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filter a list of Ghost posts down to the ones we migrate.
     *
     * @param  list<array<string, mixed>>  $posts
     * @return list<array<string, mixed>>
     */
    public static function filter(array $posts): array
    {
        return array_values(array_filter($posts, [self::class, 'shouldMigrate']));
    }

    /**
     * Normalize a Ghost post into the shape used to seed a Post row. Does NOT
     * download images — it only reports which image URLs need re-hosting.
     *
     * @param  array<string, mixed>  $post
     * @param  string  $oldBaseUrl  old site base, e.g. https://homesteadherds.com
     * @return array{title:string,slug:string,excerpt:?string,body:string,meta_title:?string,meta_description:?string,old_url:string,category_slug:?string,image_urls:list<string>}
     */
    public static function normalize(array $post, string $oldBaseUrl): array
    {
        $title = trim((string) ($post['title'] ?? 'Untitled'));
        $slug = (string) ($post['slug'] ?? Str::slug($title));
        // Resolve Ghost's __GHOST_URL__ placeholder to absolute URLs so inline
        // image src values match imageUrls() and get rewritten to local paths.
        $html = str_replace('__GHOST_URL__', rtrim($oldBaseUrl, '/'), self::cleanHtml((string) ($post['html'] ?? '')));

        $images = self::imageUrls($post, $oldBaseUrl);

        return [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => self::nullableString($post['custom_excerpt'] ?? null),
            'body' => $html,
            'meta_title' => self::nullableString($post['meta_title'] ?? null) ?? $title,
            'meta_description' => self::nullableString($post['meta_description'] ?? $post['custom_excerpt'] ?? null),
            'old_url' => self::oldUrl($oldBaseUrl, $slug),
            'category_slug' => self::categorySlug($title.' '.$slug),
            'image_urls' => $images,
        ];
    }

    /** Old public path for a slug, used as the redirect source (§10b). */
    public static function oldUrl(string $oldBaseUrl, string $slug): string
    {
        return rtrim($oldBaseUrl, '/').'/'.ltrim($slug, '/').'/';
    }

    /**
     * All image URLs referenced by a post (feature image + inline <img>), so
     * the migration can download + re-host them instead of hotlinking (§10b).
     *
     * @param  array<string, mixed>  $post
     * @return list<string>
     */
    public static function imageUrls(array $post, string $oldBaseUrl): array
    {
        $urls = [];

        if (! empty($post['feature_image'])) {
            $urls[] = self::absolute((string) $post['feature_image'], $oldBaseUrl);
        }

        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', (string) ($post['html'] ?? ''), $m)) {
            foreach ($m[1] as $src) {
                $urls[] = self::absolute($src, $oldBaseUrl);
            }
        }

        return array_values(array_unique($urls));
    }

    /** Resolve Ghost's __GHOST_URL__ placeholder and root-relative paths. */
    public static function absolute(string $url, string $oldBaseUrl): string
    {
        $base = rtrim($oldBaseUrl, '/');
        $url = str_replace('__GHOST_URL__', '', $url);

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return $base.'/'.ltrim($url, '/');
    }

    /** Strip Ghost/Koenig editor wrapper markup, leaving clean content HTML. */
    public static function cleanHtml(string $html): string
    {
        // Drop Koenig card wrapper comments and figure kg-card scaffolding.
        $html = preg_replace('/<!--kg-card-(begin|end):[^>]*-->/', '', $html) ?? $html;
        $html = preg_replace('/\s(class|data-[a-z-]+)="[^"]*kg-[^"]*"/i', '', $html) ?? $html;

        return trim($html);
    }

    private static function categorySlug(string $haystack): ?string
    {
        $haystack = Str::lower($haystack);

        foreach (self::CATEGORY_KEYWORDS as $needle => $slug) {
            if (str_contains($haystack, $needle)) {
                return $slug;
            }
        }

        return null;
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' || $value === null ? null : (string) $value;
    }
}
