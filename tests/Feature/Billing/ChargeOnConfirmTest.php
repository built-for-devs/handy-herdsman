<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Team;
use App\Services\Billing\PaymentGateway;
use App\Services\Billing\PaymentService;
use Database\Seeders\RateConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePaymentGateway;
use Tests\TestCase;

/**
 * #242 8.1 — Cashier & charge-on-confirm. A card is captured at booking and
 * charged when the booking reaches `confirmed`; cash skips the charge and marks
 * owed. Stripe is faked — no live keys, no network (§5.5, §5.6, §10b — Money).
 */
class ChargeOnConfirmTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RateConfigSeeder::class);

        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    private function service(): PaymentService
    {
        return app(PaymentService::class);
    }

    private function booking(array $attributes = []): Booking
    {
        $team = Team::factory()->create();
        $service = Service::factory()->syncPlan()->create();

        return Booking::factory()->create(array_merge([
            'team_id' => $team->id,
            'service_id' => $service->id,
        ], $attributes));
    }

    public function test_capturing_a_card_at_booking_stores_the_method_and_opens_a_pending_payment(): void
    {
        $booking = $this->booking();

        $payment = $this->service()->recordBookingPayment($booking, 'pm_card_123');

        $this->assertSame(['pm_card_123'], $this->gateway->storedMethods);
        $this->assertSame(PaymentMethod::Card->value, $payment->method);
        $this->assertSame(PaymentStatus::Pending->value, $payment->status);
        $this->assertSame('pm_card_123', $payment->stripe_payment_method_id);
        $this->assertNull($payment->charged_at);
        // $300 plan — no distance fee on this booking.
        $this->assertSame('300.00', $payment->total);
        $this->assertSame(0, $this->gateway->chargeCount());
    }

    public function test_reaching_confirmed_charges_the_captured_card(): void
    {
        $booking = $this->booking(['status' => 'confirmed', 'distance_fee_flag' => true]);
        $this->service()->recordBookingPayment($booking, 'pm_card_123');

        $payment = $this->service()->settleConfirmedBooking($booking);

        $this->assertSame(1, $this->gateway->chargeCount());
        // $300 + $30 distance → 33000 cents.
        $this->assertSame(33000, $this->gateway->charges[0]['amount']);
        $this->assertSame('pm_card_123', $this->gateway->charges[0]['payment_method']);
        $this->assertSame(PaymentStatus::Paid->value, $payment->status);
        $this->assertNotNull($payment->charged_at);
    }

    public function test_provisional_booking_is_not_charged_until_confirmed(): void
    {
        $booking = $this->booking(['status' => 'provisional']);
        $this->service()->recordBookingPayment($booking, 'pm_card_123');

        $this->assertNull($this->service()->settleConfirmedBooking($booking));
        $this->assertSame(0, $this->gateway->chargeCount());
    }

    public function test_cash_booking_skips_the_charge_and_marks_amount_owed(): void
    {
        $booking = $this->booking(['status' => 'confirmed', 'is_cash' => true]);

        $payment = $this->service()->recordBookingPayment($booking, null);
        $this->assertSame(PaymentMethod::Cash->value, $payment->method);
        $this->assertSame(PaymentStatus::Owed->value, $payment->status);

        $settled = $this->service()->settleConfirmedBooking($booking);

        $this->assertSame(0, $this->gateway->chargeCount());
        $this->assertSame(PaymentStatus::Owed->value, $settled->status);
        $this->assertNull($settled->charged_at);
        $this->assertSame('300.00', $payment->total);
    }

    public function test_confirming_twice_charges_only_once(): void
    {
        $booking = $this->booking(['status' => 'confirmed']);
        $this->service()->recordBookingPayment($booking, 'pm_card_123');

        $this->service()->settleConfirmedBooking($booking);
        $this->service()->settleConfirmedBooking($booking->fresh());

        $this->assertSame(1, $this->gateway->chargeCount());
    }

    public function test_a_failed_charge_marks_the_payment_failed_without_losing_the_booking(): void
    {
        $this->gateway->shouldFailCharge = true;

        $booking = $this->booking(['status' => 'confirmed']);
        $this->service()->recordBookingPayment($booking, 'pm_card_123');

        $payment = $this->service()->settleConfirmedBooking($booking);

        $this->assertSame(PaymentStatus::Failed->value, $payment->status);
        $this->assertNull($payment->charged_at);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    }

    public function test_no_tax_is_added_to_the_charged_total(): void
    {
        $booking = $this->booking(['status' => 'confirmed', 'distance_fee_flag' => true]);
        $this->service()->recordBookingPayment($booking, 'pm_card_123');
        $this->service()->settleConfirmedBooking($booking);

        // The charged cents equal exactly plan + distance — no tax component.
        $sumOfLines = collect($booking->payments()->first()->line_items)->sum('amount');
        $this->assertSame(330.0, round((float) $sumOfLines, 2));
        $this->assertSame(33000, $this->gateway->charges[0]['amount']);
    }
}
