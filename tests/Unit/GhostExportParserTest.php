<?php

namespace Tests\Unit;

use App\Support\GhostExport\GhostExportParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GhostExportParserTest extends TestCase
{
    /** @return array<string, mixed> */
    private function fixture(): array
    {
        $path = __DIR__.'/../Fixtures/ghost-export-sample.json';

        return json_decode((string) file_get_contents($path), true);
    }

    public function test_it_extracts_posts_from_the_export_shape(): void
    {
        $posts = GhostExportParser::posts($this->fixture());

        $this->assertCount(6, $posts);
    }

    public function test_it_filters_to_only_the_cattle_ai_allowlist(): void
    {
        $posts = GhostExportParser::filter(GhostExportParser::posts($this->fixture()));

        $slugs = array_column($posts, 'slug');

        $this->assertCount(3, $posts);
        $this->assertContains('how-to-recognize-when-your-cow-is-in-heat', $slugs);
        $this->assertContains('understanding-your-ai-protocol', $slugs);
        $this->assertContains('feed-your-cow-right-preparing-for-ai-success', $slugs);
    }

    public function test_it_leaves_milk_donkey_and_recipe_content_behind(): void
    {
        $posts = GhostExportParser::filter(GhostExportParser::posts($this->fixture()));
        $slugs = array_column($posts, 'slug');

        $this->assertNotContains('raw-milk-yogurt-recipe', $slugs);
        $this->assertNotContains('mini-donkey-for-sale', $slugs);
        $this->assertNotContains('herd-share-farm-club', $slugs);
    }

    /**
     * @param  array<string, mixed>  $post
     */
    #[DataProvider('migrationDecisionProvider')]
    public function test_should_migrate_decides_per_post(array $post, bool $expected): void
    {
        $this->assertSame($expected, GhostExportParser::shouldMigrate($post));
    }

    /** @return array<string, array{0: array<string, mixed>, 1: bool}> */
    public static function migrationDecisionProvider(): array
    {
        return [
            'allowlisted by slug' => [['slug' => 'understanding-your-ai-protocol', 'title' => 'x'], true],
            'allowlisted by title only' => [['slug' => 'p-42', 'title' => 'Understanding Your AI Protocol'], true],
            'blocked recipe even if slug-like' => [['slug' => 'cattle-ai-services', 'title' => 'Raw Milk Recipe'], false],
            'donkey content' => [['slug' => 'mini-donkey-for-sale', 'title' => 'Donkey'], false],
            'unrelated content' => [['slug' => 'something-else', 'title' => 'Something Else'], false],
        ];
    }

    public function test_it_normalizes_a_post_with_old_url_and_category(): void
    {
        $posts = GhostExportParser::filter(GhostExportParser::posts($this->fixture()));
        $heat = collect($posts)->firstWhere('slug', 'how-to-recognize-when-your-cow-is-in-heat');

        $data = GhostExportParser::normalize($heat, 'https://homesteadherds.com');

        $this->assertSame('https://homesteadherds.com/how-to-recognize-when-your-cow-is-in-heat/', $data['old_url']);
        $this->assertSame('heat-detection', $data['category_slug']);
        $this->assertSame('Recognizing Heat in Cattle', $data['meta_title']);
    }

    public function test_it_resolves_image_urls_for_rehosting(): void
    {
        $posts = GhostExportParser::filter(GhostExportParser::posts($this->fixture()));
        $heat = collect($posts)->firstWhere('slug', 'how-to-recognize-when-your-cow-is-in-heat');

        $urls = GhostExportParser::imageUrls($heat, 'https://homesteadherds.com');

        $this->assertContains('https://homesteadherds.com/content/images/2023/heat-hero.jpg', $urls);
        $this->assertContains('https://homesteadherds.com/content/images/2023/heat.jpg', $urls);
    }

    public function test_clean_html_strips_koenig_card_markup(): void
    {
        $html = '<!--kg-card-begin: html--><p class="kg-image">hi</p><!--kg-card-end: html-->';

        $this->assertSame('<p>hi</p>', GhostExportParser::cleanHtml($html));
    }
}
