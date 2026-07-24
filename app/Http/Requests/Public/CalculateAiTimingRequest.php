<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\Enums\AnimalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Input for the public AI Timing Calculator (spec §5.4). Any animal type is
 * accepted so bulls/steers can be surfaced with friendly ineligibility copy
 * (the controller handles that) rather than being rejected at validation.
 */
class CalculateAiTimingRequest extends FormRequest
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
        ];
    }

    public function animalType(): AnimalType
    {
        return AnimalType::from((string) $this->validated('animal_type'));
    }
}
