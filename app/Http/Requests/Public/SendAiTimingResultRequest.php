<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\Enums\AnimalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Input for emailing/texting an AI-timing result to a visitor (spec §5.4 —
 * informal proposal). Re-sends the timing inputs so the result is recomputed
 * server-side from the trusted M1 engine, and requires at least one contact
 * channel (email and/or phone).
 */
class SendAiTimingResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'visit1_at' => ['required', 'date'],
            'animal_type' => ['required', new Enum(AnimalType::class)],
            'email' => ['nullable', 'required_without:phone', 'email'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:32'],
        ];
    }

    public function animalType(): AnimalType
    {
        return AnimalType::from((string) $this->validated('animal_type'));
    }
}
