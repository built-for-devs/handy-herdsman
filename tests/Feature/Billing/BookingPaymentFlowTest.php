<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Service;
use App\Services\Billing\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsBookings;
use Tests\Concerns\InteractsWithRoles;
use Tests\Support\FakePaymentGateway;
use Tests\TestCase;

/**
 * #242 8.1 — the charge-on-confirm flow end-to-end over HTTP: an established
 * client's card is charged the moment their auto-confirmed booking lands; a
 * first-timer is charged only when staff approve; cash is never charged.
 */
class BookingPaymentFlowTest extends TestCase
{
    use BuildsBookings, InteractsWithRoles, RefreshDatabase;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedBookingConfig();
        Notification::fake();

        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    public function test_established_client_card_booking_charges_on_auto_confirm(): void
    {
        [$team, $owner] = $this->clientTeam('active');
        $cattle = $this->cattleFor($team, 'cow', 1);
        $service = Service::factory()->standard()->create();

        $this->actingAs($owner)->post(route('bookings.store'), [
            'service_id' => $service->id,
            'cattle_ids' => $cattle->pluck('id')->all(),
            'proposed_start' => $this->nextWorkingV1(10)->toIso8601String(),
            'payment_method_id' => 'pm_card_123',
        ])->assertRedirect(route('bookings.index'));

        $booking = Booking::where('team_id', $team->id)->firstOrFail();
        $this->assertSame(BookingStatus::Confirmed->value, $booking->status);

        $this->assertSame(['pm_card_123'], $this->gateway->storedMethods);
        $this->assertSame(1, $this->gateway->chargeCount());

        $payment = Payment::where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame(PaymentMethod::Card->value, $payment->method);
        $this->assertSame(PaymentStatus::Paid->value, $payment->status);
        $this->assertNotNull($payment->charged_at);
    }

    public function test_first_time_client_is_charged_only_when_staff_confirm(): void
    {
        [$team, $owner] = $this->clientTeam('new');
        $cattle = $this->cattleFor($team, 'cow', 1);
        $service = Service::factory()->standard()->create();

        $this->actingAs($owner)->post(route('bookings.store'), [
            'service_id' => $service->id,
            'cattle_ids' => $cattle->pluck('id')->all(),
            'proposed_start' => $this->nextWorkingV1(10)->toIso8601String(),
            'payment_method_id' => 'pm_card_123',
        ])->assertRedirect(route('bookings.index'));

        $booking = Booking::where('team_id', $team->id)->firstOrFail();
        $this->assertSame(BookingStatus::Provisional->value, $booking->status);

        // Card captured, but not charged while provisional.
        $this->assertSame(0, $this->gateway->chargeCount());
        $payment = Payment::where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame(PaymentStatus::Pending->value, $payment->status);

        // Staff approves → the charge fires.
        $this->actingAs($this->staffUser())
            ->post(route('admin.bookings.confirm', $booking))
            ->assertRedirect();

        $this->assertSame(1, $this->gateway->chargeCount());
        $this->assertSame(PaymentStatus::Paid->value, $payment->fresh()->status);
    }

    public function test_cash_booking_records_owed_and_never_charges(): void
    {
        [$team, $owner] = $this->clientTeam('active');
        $cattle = $this->cattleFor($team, 'cow', 1);
        $service = Service::factory()->standard()->create();

        $this->actingAs($owner)->post(route('bookings.store'), [
            'service_id' => $service->id,
            'cattle_ids' => $cattle->pluck('id')->all(),
            'proposed_start' => $this->nextWorkingV1(10)->toIso8601String(),
            'is_cash' => true,
        ])->assertRedirect(route('bookings.index'));

        $booking = Booking::where('team_id', $team->id)->firstOrFail();
        $this->assertTrue($booking->is_cash);

        $this->assertSame(0, $this->gateway->chargeCount());
        $payment = Payment::where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame(PaymentMethod::Cash->value, $payment->method);
        $this->assertSame(PaymentStatus::Owed->value, $payment->status);
    }
}
