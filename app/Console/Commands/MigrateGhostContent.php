<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Post;
use App\Models\Redirect;
use App\Support\GhostExport\GhostExportParser;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One-off content migration (spec §8, §10b). Parses a Ghost export, migrates
 * ONLY the listed cattle/AI education posts, assigns fresh publish dates,
 * downloads + re-hosts images (never hotlinks the old domain), and records
 * 301 redirects from the old URLs.
 *
 * NOT load-bearing after the run — a clearly one-off command with --dry-run.
 */
class MigrateGhostContent extends Command
{
    protected $signature = 'content:migrate-ghost
        {file : Path to the Ghost export JSON}
        {--old-base-url=https://homesteadherds.com : Old site base URL (redirect source + image resolution)}
        {--start-date= : Newest publish date to assign (default: today); posts step backwards from here}
        {--interval-days=4 : Days between assigned publish dates}
        {--dry-run : Parse and report only; write nothing, download nothing}';

    protected $description = 'One-off: migrate the listed cattle/AI posts from a Ghost export (§8).';

    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $this->error("Export file not found: {$path}");

            return self::FAILURE;
        }

        $export = json_decode((string) file_get_contents($path), true);

        if (! is_array($export)) {
            $this->error('Could not decode the export JSON.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $oldBaseUrl = (string) $this->option('old-base-url');
        $interval = max(1, (int) $this->option('interval-days'));
        $cursor = $this->option('start-date')
            ? Carbon::parse((string) $this->option('start-date'))
            : Carbon::now();

        $all = GhostExportParser::posts($export);
        $migrating = GhostExportParser::filter($all);

        $this->info(sprintf(
            '%s%d posts in export, %d match the cattle/AI allowlist, %d left with Homestead Herds.',
            $dryRun ? '[DRY RUN] ' : '',
            count($all),
            count($migrating),
            count($all) - count($migrating),
        ));

        $migrated = 0;

        foreach ($migrating as $raw) {
            $data = GhostExportParser::normalize($raw, $oldBaseUrl);
            $publishAt = $cursor->copy();
            $cursor = $cursor->copy()->subDays($interval);

            $this->line(sprintf(
                '  • %s  →  /blog/%s  (publish %s, %d image(s), redirect from %s)',
                $data['title'],
                $data['slug'],
                $publishAt->toDateString(),
                count($data['image_urls']),
                $data['old_url'],
            ));

            if ($dryRun) {
                continue;
            }

            $body = $data['body'];
            $featureImage = null;

            foreach ($data['image_urls'] as $index => $url) {
                $localPath = $this->rehostImage($url, $data['slug'], $index);

                if ($localPath === null) {
                    continue;
                }

                $body = str_replace($url, $localPath, $body);
                $featureImage ??= $localPath;
            }

            $category = $data['category_slug']
                ? Category::firstOrCreate(
                    ['slug' => $data['category_slug']],
                    ['name' => Str::headline($data['category_slug'])],
                )
                : null;

            $post = Post::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'category_id' => $category?->id,
                    'title' => $data['title'],
                    'excerpt' => $data['excerpt'],
                    'body' => $body,
                    'feature_image' => $featureImage,
                    'meta_title' => $data['meta_title'],
                    'meta_description' => $data['meta_description'],
                    'old_url' => $data['old_url'],
                    'published_at' => $publishAt,
                ],
            );

            Redirect::updateOrCreate(
                ['from_path' => $this->pathOf($data['old_url'])],
                ['post_id' => $post->id, 'to_url' => "/blog/{$post->slug}", 'status' => 301],
            );

            $migrated++;
        }

        $this->newLine();
        $this->info($dryRun
            ? 'Dry run complete — nothing was written.'
            : "Migrated {$migrated} post(s) with fresh dates, re-hosted images, and old-URL redirects.");

        return self::SUCCESS;
    }

    /** Download an image and store it on the public disk; return its public path. */
    private function rehostImage(string $url, string $slug, int $index): ?string
    {
        try {
            $response = Http::timeout(20)->get($url);

            if (! $response->successful()) {
                $this->warn("    ! could not fetch image ({$response->status()}): {$url}");

                return null;
            }

            $ext = pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'jpg';
            $path = "blog/{$slug}/{$index}.{$ext}";
            Storage::disk('public')->put($path, $response->body());

            return '/storage/'.$path;
        } catch (\Throwable $e) {
            $this->warn("    ! image download failed: {$url} ({$e->getMessage()})");

            return null;
        }
    }

    /** Reduce an absolute old URL to a leading-slash path for the redirect key. */
    private function pathOf(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;

        return '/'.trim($path, '/');
    }
}
