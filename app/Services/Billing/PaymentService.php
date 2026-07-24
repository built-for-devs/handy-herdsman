<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * Charge-on-confirm orchestration (spec §5.5, §5.6, §8.1 / #242). A card is
 * captured at booking (stored on the Team via Cashier) and a `pending` payment
 * is recorded; the charge fires only when the booking reaches `confirmed`. Cash
 * bookings skip the charge and record an `owed` payment Jeff settles in person.
 * Totals come from {@see FeeCalculator} — no tax is ever added (§10b — Money).
 */
class PaymentService
{
    public function __construct(
        private FeeCalculator $fees,
        private PaymentGateway $gateway,
    ) {}

    /**
     * Record the payment intent at booking time. Card: store the method on the
     * Team and open a `pending` payment. Cash: open an `owed` payment, no charge.
     */
    public function recordBookingPayment(Booking $booking, ?string $paymentMethodId = null): Payment
    {
        $breakdown = $this->fees->forBooking($booking);

        if ($booking->is_cash) {
            return $this->createPayment($booking, $breakdown, PaymentMethod::Cash, PaymentStatus::Owed);
        }

        if ($paymentMethodId !== null && $this->gateway->isConfigured()) {
            $this->gateway->storePaymentMethod($booking->team, $paymentMethodId);
        }

        return $this->createPayment(
            $booking,
            $breakdown,
            PaymentMethod::Card,
            PaymentStatus::Pending,
            $paymentMethodId,
        );
    }

    /**
     * Fire the charge when a booking reaches `confirmed` (spec §5.5). Idempotent
     * — an already-paid or cash (owed) booking is left untouched. A failed charge
     * marks the payment `failed` rather than losing the booking.
     */
    public function settleConfirmedBooking(Booking $booking): ?Payment
    {
        if ($booking->bookingStatus() !== BookingStatus::Confirmed) {
            return null;
        }

        $payment = $booking->payments()->latest('id')->first()
            ?? $this->recordBookingPayment($booking);

        // Cash is settled manually; a card already charged is left as-is.
        if ($payment->method === PaymentMethod::Cash->value) {
            return $payment;
        }

        if ($payment->status === PaymentStatus::Paid->value) {
            return $payment;
        }

        $cents = (int) round(((float) $payment->total) * 100);

        if ($cents <= 0) {
            $payment->forceFill([
                'status' => PaymentStatus::Paid->value,
                'charged_at' => now(),
            ])->save();

            return $payment;
        }

        try {
            $result = $this->gateway->charge(
                $booking->team,
                $cents,
                $payment->stripe_payment_method_id,
                ['metadata' => ['booking_id' => (string) $booking->id]],
            );

            $payment->forceFill([
                'status' => PaymentStatus::Paid->value,
                'charged_at' => now(),
                'stripe_payment_method_id' => $result->paymentMethodId ?? $payment->stripe_payment_method_id,
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('Booking charge failed', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);

            $payment->forceFill(['status' => PaymentStatus::Failed->value])->save();
        }

        return $payment;
    }

    private function createPayment(
        Booking $booking,
        FeeBreakdown $breakdown,
        PaymentMethod $method,
        PaymentStatus $status,
        ?string $paymentMethodId = null,
    ): Payment {
        return Payment::create([
            'team_id' => $booking->team_id,
            'booking_id' => $booking->id,
            'line_items' => $breakdown->toArray(),
            'total' => $breakdown->total(),
            'method' => $method->value,
            'stripe_payment_method_id' => $paymentMethodId,
            'status' => $status->value,
        ]);
    }
}
