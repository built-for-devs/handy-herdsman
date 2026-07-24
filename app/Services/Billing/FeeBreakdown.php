<?php

declare(strict_types=1);

namespace App\Services\Billing;

/**
 * The itemised result of {@see FeeCalculator} (spec §6.6, §10b — Money). Holds
 * every line that makes up a booking total; the total is simply their sum, with
 * NO sales tax ever added (§10b). Serialises straight into `payments.line_items`.
 */
final readonly class FeeBreakdown
{
    /**
     * @param  list<FeeLine>  $lines
     */
    public function __construct(
        public array $lines,
        public bool $visitMinimumApplied = false,
    ) {}

    public function total(): float
    {
        return round(array_sum(array_map(fn (FeeLine $l) => $l->amount, $this->lines)), 2);
    }

    /** Amount charged in the smallest currency unit (cents) for Stripe/Cashier. */
    public function totalInCents(): int
    {
        return (int) round($this->total() * 100);
    }

    public function amountFor(string $code): float
    {
        return round(array_sum(array_map(
            fn (FeeLine $l) => $l->code === $code ? $l->amount : 0.0,
            $this->lines,
        )), 2);
    }

    public function distanceFee(): float
    {
        return $this->amountFor('distance_fee');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(fn (FeeLine $l) => $l->toArray(), $this->lines);
    }
}
