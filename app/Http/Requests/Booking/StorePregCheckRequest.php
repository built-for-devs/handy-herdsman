<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Enums\PregCheckMethod;
use App\Enums\PregCheckState;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Record a preg check performed at a visit (#241, §10b). Blood → pending draw
 * (optional +fee lab confirmation); palpation → immediate, so a final result is
 * required at the visit. Staff-only.
 */
class StorePregCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cattle_id' => ['required', 'integer', 'exists:cattle,id'],
            'visit_id' => ['nullable', 'integer', 'exists:visits,id'],
            'method' => ['required', Rule::enum(PregCheckMethod::class)],
            'lab_requested' => ['boolean'],
            'state' => ['nullable', Rule::enum(PregCheckState::class)],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('method') !== PregCheckMethod::Palpation->value) {
                return;
            }

            $state = $this->input('state');
            $isFinal = $state !== null && PregCheckState::from($state)->isFinal();

            if (! $isFinal) {
                $validator->errors()->add('state', 'Palpation is definitive — record open, bred, or recheck.');
            }
        });
    }
}
