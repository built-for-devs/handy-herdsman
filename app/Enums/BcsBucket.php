<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Body Condition Score buckets for the BCS-vs-conception headline report
 * (spec §5.6c). The 1–9 BCS scale is a fixed domain constant (the one thing
 * §5.6c allows to be hardcoded): 5–6 is the breeding target, below is thin,
 * 7+ is over-conditioned — the "fat cows don't breed well" argument.
 */
enum BcsBucket: string
{
    case Low = 'lt5';      // under-conditioned (BCS 1–4)
    case Target = '5-6';   // ideal breeding condition
    case High = '7plus';   // over-conditioned (BCS 7–9)

    public static function forScore(int $score): self
    {
        return match (true) {
            $score <= 4 => self::Low,
            $score <= 6 => self::Target,
            default => self::High,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Low => 'BCS < 5 (thin)',
            self::Target => 'BCS 5–6 (target)',
            self::High => 'BCS 7+ (over-conditioned)',
        };
    }

    /** Display order, thin → over-conditioned. */
    public function order(): int
    {
        return match ($this) {
            self::Low => 0,
            self::Target => 1,
            self::High => 2,
        };
    }
}
