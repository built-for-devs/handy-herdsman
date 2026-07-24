<?php

declare(strict_types=1);

namespace App\Http\Requests\Cattle;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload a photo to an animal (§228, §10b). Clients upload for-sale photos;
 * staff upload at appointments and may link the photo to a visit. Stored on the
 * config-driven media disk so cloud storage swaps in later without code change.
 */
class StoreMediaRequest extends FormRequest
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
            'photo' => ['required', 'image', 'max:10240'], // 10 MB
            'caption' => ['nullable', 'string', 'max:255'],
            'taken_at' => ['nullable', 'date'],
            'visit_id' => ['nullable', 'integer', 'exists:visits,id'],
        ];
    }
}
