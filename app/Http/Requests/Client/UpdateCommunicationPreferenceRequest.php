<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Enums\MessageChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Client communication preferences & consent (spec §5.7).
 *
 * Each of the 4 categories takes email/text/both — at least one channel is
 * always on because "none" is not an option. SMS consent is captured
 * SEPARATELY per category and is what actually gates outbound texts.
 */
class UpdateCommunicationPreferenceRequest extends FormRequest
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
        $rules = [
            'channel_prefs' => ['required', 'array'],
            'sms_consent' => ['array'],
        ];

        foreach (array_keys(config('reminders.categories')) as $category) {
            // Every category must resolve to a valid channel (never "none").
            $rules["channel_prefs.{$category}"] = ['required', Rule::enum(MessageChannel::class)];
            $rules["sms_consent.{$category}"] = ['boolean'];
        }

        return $rules;
    }
}
