<?php

declare(strict_types=1);

namespace App\Services\Billing;

/**
 * The outcome of charging a stored card through Cashier (spec §5.6). Kept
 * gateway-agnostic so tests can fake it without touching Stripe (§10b — Money).
 */
final readonly class ChargeResult
{
    public function __construct(
        public bool $successful,
        public ?string $chargeId = null,
        public ?string $paymentMethodId = null,
    ) {}
}
