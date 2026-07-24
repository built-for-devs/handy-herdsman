<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Pregnancy-check lifecycle (spec §10b — Preg check results). Blood draws start
 * `pending` (awaiting the lab); `open`/`bred`/`recheck` are the final states.
 * Downstream nurture fires ONLY on a final state, never on `pending`.
 */
enum PregCheckState: string
{
    case Pending = 'pending';
    case Open = 'open';
    case Bred = 'bred';
    case Recheck = 'recheck';

    /** A final (definitive) result — the point at which nurture may fire. */
    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }

    /** The three states Jeff can record as a definitive result. */
    public static function finalStates(): array
    {
        return [self::Open, self::Bred, self::Recheck];
    }
}
