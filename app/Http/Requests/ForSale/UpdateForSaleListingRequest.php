<?php

declare(strict_types=1);

namespace App\Http\Requests\ForSale;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Toggle an animal on/off the for-sale board and pick which profile fields are
 * shared (spec §5.9). Authorization is handled by the CattlePolicy in the
 * controller — owners/members manage their own herd, staff manage any animal.
 */
class UpdateForSaleListingRequest extends FormRequest
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
        $shareable = array_keys(config('for_sale.shareable_fields'));

        return [
            'for_sale' => ['required', 'boolean'],
            'shared_fields' => ['array'],
            'shared_fields.*' => [Rule::in($shareable)],
        ];
    }
}
