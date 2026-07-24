<?php

declare(strict_types=1);

namespace App\Http\Requests\Cattle;

use App\Enums\CattleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Create a cattle profile (§5.5, §10b). animal_type is one of the four stored
 * types — "calf"/"weanling" are display labels derived from dob, never stored.
 */
class StoreCattleRequest extends FormRequest
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
            'reg_name' => ['nullable', 'string', 'max:255'],
            'herd_number' => ['nullable', 'string', 'max:64'],
            'dob' => ['nullable', 'date'],
            'breed' => ['nullable', 'string', 'max:255'],
            'animal_type' => ['required', Rule::in(['heifer', 'cow', 'bull', 'steer'])],
            'has_calved' => ['boolean'],
            'status' => ['nullable', new Enum(CattleStatus::class)],
            'a2a2' => ['nullable', 'boolean'],
            'for_sale' => ['boolean'],
            'for_sale_shared_fields' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
