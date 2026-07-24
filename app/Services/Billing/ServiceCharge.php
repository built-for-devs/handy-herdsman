<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Service;

/**
 * One billable service performed on a trip, paired with its headcount (spec
 * §5.2b, §6.6). Several charges can share ONE visit — the {@see FeeCalculator}
 * then applies a single per-visit minimum across all of them, never one per
 * service (§10b — Money).
 */
final readonly class ServiceCharge
{
    /**
     * @param  array<string, mixed>  $priceRule  editable `services.price_rule`
     */
    public function __construct(
        public array $priceRule,
        public int $headcount = 1,
        public ?string $label = null,
    ) {}

    public static function forService(Service $service, int $headcount = 1): self
    {
        return new self(
            priceRule: (array) $service->price_rule,
            headcount: $headcount,
            label: $service->name,
        );
    }

    public function model(): string
    {
        return (string) ($this->priceRule['model'] ?? 'flat');
    }

    public function heads(): int
    {
        return max(1, $this->headcount);
    }
}
