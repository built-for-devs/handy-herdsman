<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Jobs\GeocodeClient;
use App\Models\Client;
use App\Services\Geocoding\Geocoder;
use App\Services\Geocoding\GoogleGeocoder;
use App\Services\Geocoding\NullGeocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Config-driven geocoder abstraction (spec §5.10, §9). Never hardcodes a key;
 * without one the coordinates stay null (address stored, geocode queued).
 */
class GeocodeClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_null_driver_is_used_when_no_key_is_configured(): void
    {
        config(['geocoding.driver' => 'google', 'geocoding.google.key' => null]);

        $this->assertInstanceOf(NullGeocoder::class, app(Geocoder::class));
    }

    public function test_google_driver_is_used_when_a_key_is_present(): void
    {
        config(['geocoding.driver' => 'google', 'geocoding.google.key' => 'test-key']);

        $this->assertInstanceOf(GoogleGeocoder::class, app(Geocoder::class));
    }

    public function test_job_leaves_coordinates_null_without_a_key(): void
    {
        config(['geocoding.driver' => 'google', 'geocoding.google.key' => null]);
        $client = Client::factory()->create(['lat' => null, 'lng' => null]);

        (new GeocodeClient($client->id))->handle(app(Geocoder::class));

        $this->assertNull($client->fresh()->lat);
        $this->assertNull($client->fresh()->lng);
    }

    public function test_job_caches_coordinates_when_the_provider_resolves(): void
    {
        config(['geocoding.driver' => 'google', 'geocoding.google.key' => 'test-key']);

        Http::fake([
            '*' => Http::response([
                'results' => [
                    ['geometry' => ['location' => ['lat' => 31.6585, 'lng' => -97.4712]]],
                ],
            ]),
        ]);

        $client = Client::factory()->create(['lat' => null, 'lng' => null]);

        (new GeocodeClient($client->id))->handle(app(Geocoder::class));

        $this->assertEqualsWithDelta(31.6585, (float) $client->fresh()->lat, 0.0001);
        $this->assertEqualsWithDelta(-97.4712, (float) $client->fresh()->lng, 0.0001);
    }
}
