<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Client onboarding (spec §4, §5.6). Email (identity) and phone (operational)
 * are both required; the service agreement and liability waiver must be
 * explicitly accepted and are timestamped on the client record.
 */
class StoreOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],

            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:64'],
            'postal_code' => ['required', 'string', 'max:16'],

            // Both must be affirmatively accepted (§5.6).
            'accept_agreement' => ['accepted'],
            'accept_waiver' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'accept_agreement.accepted' => 'You must accept the service agreement to continue.',
            'accept_waiver.accepted' => 'You must accept the liability waiver to continue.',
        ];
    }
}
