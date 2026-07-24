<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Cattle;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired the first time an animal is recorded as having calved (§10b — a heifer
 * becomes a cow on her first calving). The seam the reminder engine plugs into
 * to start the post-calving rebreed loop — the core recurring-revenue loop
 * (§5.7).
 */
class CattleCalved
{
    use Dispatchable;

    public function __construct(
        public readonly Cattle $cattle,
        public readonly CarbonInterface $calvedAt,
    ) {}
}
