<?php

declare(strict_types=1);

namespace App\Services\ForSale;

use App\Models\Cattle;
use App\Models\Media;

/**
 * Turns a for-sale animal into a light listing that exposes ONLY the profile
 * fields the seller chose to share (spec §5.9). A field surfaces only when it
 * is BOTH in the config shareable catalogue AND in the animal's
 * `for_sale_shared_fields` selection — every other profile attribute stays
 * private. This is the single choke point for what a listing reveals, so the
 * board can never leak an un-shared field.
 */
class ForSaleListingPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Cattle $cattle): array
    {
        return [
            'id' => $cattle->id,
            'fields' => $this->sharedFields($cattle),
            'photos' => $this->photos($cattle),
            'listed_by_role' => $cattle->for_sale_listed_by_role,
        ];
    }

    /**
     * The chosen-and-shareable fields, in the config's display order.
     *
     * @return list<array{key: string, label: string, value: string}>
     */
    public function sharedFields(Cattle $cattle): array
    {
        $chosen = $cattle->for_sale_shared_fields ?? [];
        $fields = [];

        /** @var array<string, string> $catalogue */
        $catalogue = config('for_sale.shareable_fields');

        foreach ($catalogue as $key => $label) {
            if (! in_array($key, $chosen, true)) {
                continue;
            }

            $value = $this->displayValue($cattle, $key);

            if ($value === null || $value === '') {
                continue;
            }

            $fields[] = ['key' => $key, 'label' => $label, 'value' => $value];
        }

        return $fields;
    }

    private function displayValue(Cattle $cattle, string $key): ?string
    {
        return match ($key) {
            'reg_name' => $cattle->reg_name,
            'herd_number' => $cattle->herd_number,
            'breed' => $cattle->breed,
            'animal_type' => $cattle->animal_type?->value,
            'dob' => $cattle->dob?->toDateString(),
            'has_calved' => $cattle->has_calved ? 'Yes' : 'No',
            'a2a2' => $cattle->a2a2 ? 'Yes' : 'No',
            'notes' => $cattle->notes,
            default => null,
        };
    }

    /**
     * @return list<array{id: int, url: string, caption: string|null}>
     */
    private function photos(Cattle $cattle): array
    {
        if (! $cattle->relationLoaded('media')) {
            $cattle->load(['media' => fn ($query) => $query->orderByDesc('taken_at')->orderByDesc('id')]);
        }

        return $cattle->media
            ->map(fn (Media $media) => [
                'id' => $media->id,
                'url' => route('cattle.media.show', $media->id),
                'caption' => $media->caption,
            ])
            ->values()
            ->all();
    }
}
