<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Team;

/**
 * The seam between the payment flow and Cashier/Stripe (spec §5.6). Concrete
 * impl {@see CashierPaymentGateway} uses ONLY Cashier — no custom Stripe SDK
 * work, no invoicing, no tax (§10b — Money). Tests bind a fake so charges never
 * hit real Stripe.
 */
interface PaymentGateway
{
    /**
     * Whether Stripe is configured for this environment. When false the flow
     * degrades gracefully to cash-only (spec §5.6 — guard when keys absent).
     */
    public function isConfigured(): bool;

    /**
     * A SetupIntent to capture a card at booking via Stripe Elements.
     *
     * @return array{client_secret: ?string, id: ?string, publishable_key: ?string}
     */
    public function createSetupIntent(Team $team): array;

    /** Store a captured payment method as the team's default (spec §5.6). */
    public function storePaymentMethod(Team $team, string $paymentMethodId): void;

    /** Charge the stored card when the booking reaches `confirmed` (§5.5). */
    public function charge(Team $team, int $amountInCents, ?string $paymentMethodId, array $options = []): ChargeResult;
}
