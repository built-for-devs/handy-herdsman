<?php

declare(strict_types=1);

namespace App\Http\Requests\Cattle;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update a health record (§5.5, §10b). Authorization (clients cannot touch
 * staff-added records; Jeff can, attributed) is enforced by the policy in the
 * controller — this only validates shape.
 */
class UpdateHealthRecordRequest extends FormRequest
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
            'type' => ['sometimes', 'required', Rule::in(array_keys(config('records.types')))],
            'payload' => ['nullable', 'array'],
            'bcs_score' => [
                'nullable',
                'integer',
                'between:'.config('records.bcs.min').','.config('records.bcs.max'),
            ],
            'recorded_at' => ['nullable', 'date'],
        ];
    }
}
