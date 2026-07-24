<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Enums\PregCheckState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Jeff records the definitive lab result on a pending blood check (#241, §10b).
 * Only staff — clients never enter results. The result must be a final state.
 */
class RecordPregCheckResultRequest extends FormRequest
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
            'state' => ['required', Rule::in(array_map(
                fn (PregCheckState $s) => $s->value,
                PregCheckState::finalStates(),
            ))],
        ];
    }
}
