<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Enums\MessageChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff channel override for a client (spec §5.7). Jeff can override a
 * client's channel prefs ("never checks email — text him everything") with a
 * required note explaining why. The client's own settings stay intact
 * underneath; the override wins.
 */
class UpdateStaffChannelOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaff();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Null / empty clears the override.
            'override' => ['nullable', 'array'],
            'override.*' => [Rule::enum(MessageChannel::class)],
            // A note is required whenever an override is actually set.
            'note' => ['nullable', 'string', 'max:1000', 'required_with:override'],
        ];
    }
}
