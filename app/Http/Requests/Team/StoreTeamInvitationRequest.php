<?php

declare(strict_types=1);

namespace App\Http\Requests\Team;

use App\Enums\TeamRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Owner invites a member (full access) or a vet (read-only, client-selected
 * scope) (spec §4, §10b). A vet invitation must carry a scope: profile access
 * and/or specific record types.
 */
class StoreTeamInvitationRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::enum(TeamRole::class)],

            // Vet read scope — only meaningful for a vet invitation (§4).
            'vet_scope' => ['nullable', 'array', 'required_if:role,vet'],
            'vet_scope.profile' => ['boolean'],
            'vet_scope.record_types' => ['array'],
            'vet_scope.record_types.*' => [
                Rule::in(config('clients.vet_scopeable_record_types')),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('role') !== TeamRole::Vet->value) {
                return;
            }

            $profile = (bool) $this->input('vet_scope.profile', false);
            $types = (array) $this->input('vet_scope.record_types', []);

            // A vet with no profile access and no record types can see nothing.
            if (! $profile && $types === []) {
                $validator->errors()->add(
                    'vet_scope',
                    'Grant the vet at least profile access or one record type.'
                );
            }
        });
    }
}
