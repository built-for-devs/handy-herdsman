<?php

declare(strict_types=1);

namespace App\Services\Billing;

/**
 * A single, transparent line on a booking total (spec §6.6, §10b — Money).
 * Every amount is derived from `rate_config` / `services.price_rule` — never
 * hardcoded — and never carries sales tax (§10b).
 */
final readonly class FeeLine
{
    public function __construct(
        public string $code,
        public string $label,
        public float $amount,
    ) {}

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'label' => $this->label,
            'amount' => $this->amount,
        ];
    }
}
