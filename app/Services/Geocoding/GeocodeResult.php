<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

/**
 * The outcome of a geocode attempt. Coordinates are null when the address
 * could not be resolved or no provider key is configured (spec §5.10).
 */
final readonly class GeocodeResult
{
    public function __construct(
        public ?float $lat = null,
        public ?float $lng = null,
    ) {}

    public function found(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    public static function empty(): self
    {
        return new self(null, null);
    }
}
