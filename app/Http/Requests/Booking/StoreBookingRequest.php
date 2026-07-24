<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Enums\OnCallKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a client-submitted booking (spec §6.1). The heavy business rules
 * (timing, availability, distance, mixed groups) live in the booking services;
 * this only checks the request shape.
 */
class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentTeam !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')],
            'cattle_ids' => ['required', 'array', 'min:1'],
            'cattle_ids.*' => ['integer'],
            'proposed_start' => ['nullable', 'date'],
            'oncall_kind' => ['nullable', Rule::enum(OnCallKind::class)],
            'heat_observed_at' => ['nullable', 'date'],
            'is_cash' => ['boolean'],
            // A Stripe PaymentMethod id captured client-side via Elements /
            // SetupIntent (spec §5.6). Absent for cash bookings.
            'payment_method_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
