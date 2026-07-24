<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Client;
use App\Services\Geocoding\Geocoder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Geocodes a client's address once and caches lat/lng (spec §5.10). When no
 * provider key is configured the null geocoder returns nothing and the
 * coordinates stay null — re-running this job after a key is added completes
 * the geocode. Idempotent and safe to re-dispatch.
 */
class GeocodeClient implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $clientId) {}

    public function handle(Geocoder $geocoder): void
    {
        $client = Client::find($this->clientId);

        if ($client === null) {
            return;
        }

        $address = $client->fullAddress();

        if ($address === null) {
            return;
        }

        $result = $geocoder->geocode($address);

        if (! $result->found()) {
            return;
        }

        $client->forceFill([
            'lat' => $result->lat,
            'lng' => $result->lng,
        ])->save();
    }
}
