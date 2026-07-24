<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Models\SemenInventory;
use App\Models\Visit;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Appointment completion form submit (#239, §5.5). Staff-only (route is behind
 * EnsureStaff). BCS 1-9 per animal is REQUIRED on breeding-related visits — the
 * submit is blocked without it. A chosen semen lot must belong to the visit's
 * team (tenant isolation, §4).
 */
class StoreVisitCompletionRequest extends FormRequest
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
        $min = (int) config('records.bcs.min', 1);
        $max = (int) config('records.bcs.max', 9);

        return [
            'completed_at' => ['required', 'date'],
            'procedure_confirmed' => ['accepted'],
            'semen_inventory_id' => ['nullable', 'integer'],
            'straws_used' => ['nullable', 'integer', 'min:0'],
            'straws_wasted' => ['nullable', 'integer', 'min:0'],
            'supplies_used' => ['nullable', 'array'],
            'supplies_used.*' => ['numeric', 'min:0'],
            'mileage' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'animals' => ['nullable', 'array'],
            'animals.*.cattle_id' => ['required', 'integer'],
            'animals.*.bcs_score' => ['nullable', 'integer', "between:{$min},{$max}"],
            'animals.*.note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Visit $visit */
            $visit = $this->route('visit');

            $this->assertSemenLotBelongsToTeam($validator, $visit);
            $this->assertBcsPresentOnBreedingVisits($validator, $visit);
        });
    }

    private function assertSemenLotBelongsToTeam(Validator $validator, Visit $visit): void
    {
        $lotId = $this->input('semen_inventory_id');

        if ($lotId === null) {
            return;
        }

        $exists = SemenInventory::query()
            ->whereKey($lotId)
            ->where('team_id', $visit->team_id)
            ->exists();

        if (! $exists) {
            $validator->errors()->add('semen_inventory_id', 'That semen lot is not in this client\'s custody.');
        }
    }

    /**
     * BCS is required per animal on breeding-related visits. Every animal the
     * visit covers must carry a score, or the submit is blocked (§5.5).
     */
    private function assertBcsPresentOnBreedingVisits(Validator $validator, Visit $visit): void
    {
        if (! $visit->isBreedingRelated()) {
            return;
        }

        $required = $visit->animals()->pluck('id');
        $scored = collect($this->input('animals', []))
            ->filter(fn ($a) => ($a['bcs_score'] ?? null) !== null && $a['bcs_score'] !== '')
            ->pluck('cattle_id')
            ->map(fn ($id) => (int) $id);

        $missing = $required->reject(fn ($id) => $scored->contains($id));

        if ($missing->isNotEmpty()) {
            $validator->errors()->add(
                'animals',
                'A Body Condition Score (1–9) is required for every animal on a breeding visit.'
            );
        }
    }
}
