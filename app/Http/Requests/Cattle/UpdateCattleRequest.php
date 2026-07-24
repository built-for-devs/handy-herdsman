<?php

declare(strict_types=1);

namespace App\Http\Requests\Cattle;

use App\Enums\CattleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Update a cattle profile (§5.5, §10b). Setting status to `inactive` triggers
 * the reminder-cancellation hook via the model observer.
 */
class UpdateCattleRequest extends FormRequest
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
            'animal_type' => ['sometimes', 'required', Rule::in(['heifer', 'cow', 'bull', 'steer'])],
            'has_calved' => ['boolean'],
            'status' => ['sometimes', 'required', new Enum(CattleStatus::class)],
            'a2a2' => ['nullable', 'boolean'],
            'for_sale' => ['boolean'],
            'for_sale_shared_fields' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
