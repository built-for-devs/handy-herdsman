<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Team;

/**
 * Cashier-backed {@see PaymentGateway} (spec §5.6). Uses the prebuilt Cashier
 * path only — `createSetupIntent`, `updateDefaultPaymentMethod`, `charge` — with
 * no invoicing and no tax (taxes are disabled globally in AppServiceProvider,
 * §10b — Money). The Team is the Billable customer (§4, M0).
 */
class CashierPaymentGateway implements PaymentGateway
{
    public function isConfigured(): bool
    {
        return filled(config('cashier.secret'));
    }

    public function createSetupIntent(Team $team): array
    {
        if (! $this->isConfigured()) {
            return ['client_secret' => null, 'id' => null, 'publishable_key' => null];
        }

        $team->createOrGetStripeCustomer();
        $intent = $team->createSetupIntent();

        return [
            'client_secret' => $intent->client_secret,
            'id' => $intent->id,
            'publishable_key' => config('cashier.key'),
        ];
    }

    public function storePaymentMethod(Team $team, string $paymentMethodId): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $team->createOrGetStripeCustomer();
        $team->updateDefaultPaymentMethod($paymentMethodId);
    }

    public function charge(Team $team, int $amountInCents, ?string $paymentMethodId, array $options = []): ChargeResult
    {
        $team->createOrGetStripeCustomer();

        // Off-session: the card was captured earlier at booking, so Jeff/the
        // system charges without the client present (§5.5).
        $options = array_merge(['off_session' => true], $options);

        $payment = $paymentMethodId !== null
            ? $team->charge($amountInCents, $paymentMethodId, $options)
            : $team->charge($amountInCents, $team->defaultPaymentMethod()?->id, $options);

        return new ChargeResult(
            successful: true,
            chargeId: $payment->id,
            paymentMethodId: $paymentMethodId ?? $team->defaultPaymentMethod()?->id,
        );
    }
}
