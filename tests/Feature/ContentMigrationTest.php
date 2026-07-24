<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentMigrationTest extends TestCase
{
    use RefreshDatabase;

    private string $fixture = 'tests/Fixtures/ghost-export-sample.json';

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('content:migrate-ghost', ['file' => $this->fixture, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('redirects', 0);
    }

    public function test_it_migrates_only_allowlisted_posts_with_redirects_and_fresh_dates(): void
    {
        Storage::fake('public');
        Http::fake(fn () => Http::response('fake-image-bytes', 200));

        $this->artisan('content:migrate-ghost', ['file' => $this->fixture])
            ->assertSuccessful();

        // Only the 3 cattle/AI posts migrate; milk/donkey/herd-share are left behind.
        $this->assertDatabaseCount('posts', 3);
        $this->assertDatabaseHas('posts', ['slug' => 'understanding-your-ai-protocol']);
        $this->assertDatabaseMissing('posts', ['slug' => 'raw-milk-yogurt-recipe']);
        $this->assertDatabaseMissing('posts', ['slug' => 'mini-donkey-for-sale']);

        // Fresh publish dates (not the 2021 export dates).
        $post = Post::where('slug', 'understanding-your-ai-protocol')->first();
        $this->assertNotNull($post->published_at);
        $this->assertTrue($post->published_at->year >= now()->year);

        // Redirect recorded from the old URL path.
        $this->assertDatabaseHas('redirects', [
            'from_path' => '/how-to-recognize-when-your-cow-is-in-heat',
            'status' => 301,
        ]);
    }

    public function test_it_rehosts_images_locally_instead_of_hotlinking(): void
    {
        Storage::fake('public');
        Http::fake(fn () => Http::response('fake-image-bytes', 200));

        $this->artisan('content:migrate-ghost', ['file' => $this->fixture])->assertSuccessful();

        $post = Post::where('slug', 'how-to-recognize-when-your-cow-is-in-heat')->first();

        $this->assertNotNull($post->feature_image);
        $this->assertStringStartsWith('/storage/blog/', $post->feature_image);
        $this->assertStringNotContainsString('homesteadherds.com', (string) $post->body);
        Storage::disk('public')->assertExists('blog/how-to-recognize-when-your-cow-is-in-heat/0.jpg');
    }

    public function test_migrated_old_url_serves_a_301_redirect(): void
    {
        Redirect::create([
            'from_path' => '/legacy-heat-post',
            'to_url' => '/blog/how-to-recognize-when-your-cow-is-in-heat',
            'status' => 301,
        ]);

        $this->get('/legacy-heat-post')
            ->assertStatus(301)
            ->assertRedirect('/blog/how-to-recognize-when-your-cow-is-in-heat');
    }
}
