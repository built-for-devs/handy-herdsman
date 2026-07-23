<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Protocol;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a protocol's Visit 3 window is recomputed from Visit 2's actual
 * completed timestamp (§10b — Timing math). When `$conflictReason` is non-null
 * the new window falls outside working hours and must surface to Jeff for a
 * manual decision — a protocol past V1 cannot simply be auto-moved.
 *
 * The reminders/notification layer (M9) listens for this; M1 just guarantees
 * the recompute-and-flag happens automatically.
 */
class Visit3Recomputed
{
    use Dispatchable;

    public function __construct(
        public Protocol $protocol,
        public ?string $conflictReason = null,
    ) {}

    public function hasConflict(): bool
    {
        return $this->conflictReason !== null;
    }
}
