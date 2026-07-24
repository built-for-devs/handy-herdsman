<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Team;
use App\Services\Billing\ChargeResult;
use App\Services\Billing\PaymentGateway;

/**
 * In-memory {@see PaymentGateway} for tests — records what would have been sent
 * to Stripe without ever hitting the network (spec §8.1 — fake Stripe, no live
 * keys). Cashier/Stripe is never touched.
 */
class FakePaymentGateway implements PaymentGateway
{
    public bool $configured = true;

    /** @var list<string> */
    public array $storedMethods = [];

    /** @var list<array{team_id:int, amount:int, payment_method:?string, options:array}> */
    public array $charges = [];

    public bool $shouldFailCharge = false;

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function createSetupIntent(Team $team): array
    {
        if (! $this->configured) {
            return ['client_secret' => null, 'id' => null, 'publishable_key' => null];
        }

        return [
            'client_secret' => 'seti_fake_secret',
            'id' => 'seti_fake',
            'publishable_key' => 'pk_test_fake',
        ];
    }

    public function storePaymentMethod(Team $team, string $paymentMethodId): void
    {
        $this->storedMethods[] = $paymentMethodId;
    }

    public function charge(Team $team, int $amountInCents, ?string $paymentMethodId, array $options = []): ChargeResult
    {
        if ($this->shouldFailCharge) {
            throw new \RuntimeException('Card declined (fake).');
        }

        $this->charges[] = [
            'team_id' => $team->id,
            'amount' => $amountInCents,
            'payment_method' => $paymentMethodId,
            'options' => $options,
        ];

        return new ChargeResult(
            successful: true,
            chargeId: 'ch_fake_'.count($this->charges),
            paymentMethodId: $paymentMethodId,
        );
    }

    public function chargeCount(): int
    {
        return count($this->charges);
    }
}
