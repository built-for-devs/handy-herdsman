<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\Enums\AnimalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Input for the public Due Date Calculator (spec §5.4b). Breed is a free-string
 * lookup against `gestation_config` (unknown/crossbreed falls back to the
 * configured default), so it is not constrained to the seeded list. Animal
 * type is optional and only heifer applies the earlier-calving offset.
 */
class CalculateDueDateRequest extends FormRequest
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
            'breeding_date' => ['required', 'date'],
            'breed' => ['nullable', 'string', 'max:255'],
            'animal_type' => ['nullable', new Enum(AnimalType::class)],
        ];
    }

    public function animalType(): ?AnimalType
    {
        $value = $this->validated('animal_type');

        return $value ? AnimalType::from((string) $value) : null;
    }
}
