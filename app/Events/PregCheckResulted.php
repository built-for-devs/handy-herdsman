<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\PregCheck;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired the moment a pregnancy check reaches a FINAL state (open|bred|recheck)
 * — never on `pending` (§10b — Preg check results). M7 schedules the concrete
 * nurture reminders; this event is the seam M9's engine listens on for any
 * additional downstream messaging.
 */
class PregCheckResulted
{
    use Dispatchable;

    public function __construct(public PregCheck $pregCheck) {}
}
