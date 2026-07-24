<?php

declare(strict_types=1);

namespace App\Http\Requests\Cattle;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a health record (§5.5, §6, §10b). Body-condition entries require a BCS
 * score within the configured 1-9 scale; every type accepts a JSON payload.
 */
class StoreHealthRecordRequest extends FormRequest
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
            'type' => ['required', Rule::in(array_keys(config('records.types')))],
            'payload' => ['nullable', 'array'],
            'bcs_score' => [
                Rule::requiredIf(fn () => $this->input('type') === 'body_condition'),
                'nullable',
                'integer',
                'between:'.config('records.bcs.min').','.config('records.bcs.max'),
            ],
            'recorded_at' => ['nullable', 'date'],
            'visit_id' => ['nullable', 'integer', 'exists:visits,id'],
        ];
    }
}
