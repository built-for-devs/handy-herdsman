<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\DirectoryEntry;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ResourceDirectoryController extends Controller
{
    /** Category slug → human label for the public directory (§5.8). */
    public const CATEGORY_LABELS = [
        'vet' => 'Veterinarians',
        'nutritionist' => 'Nutritionists',
        'hoof_trimmer' => 'Hoof Trimmers',
        'ai_tech' => 'Other AI Techs',
        'hauling' => 'Livestock Hauling',
        'supplies' => 'Feed & Supplies',
        'other' => 'Other Resources',
    ];

    /**
     * Public resource directory — staff-curated local resources, grouped by
     * category. SEO play: ranks for "cattle [service] near Waco" (§5.8).
     */
    public function __invoke(): Response
    {
        $groups = DirectoryEntry::where('active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category')
            ->map(fn (Collection $entries, string $category) => [
                'category' => $category,
                'label' => self::CATEGORY_LABELS[$category] ?? ucfirst($category),
                'entries' => $entries->map(fn (DirectoryEntry $e) => [
                    'name' => $e->name,
                    'area' => $e->area,
                    'url' => $e->url,
                    'notes' => $e->notes,
                ])->values(),
            ])
            ->values();

        return Inertia::render('marketing/ResourceDirectory', [
            'groups' => $groups,
            'meta' => [
                'title' => 'Central Texas Cattle Resource Directory — Handy Herdsman',
                'description' => 'Staff-curated local vets, nutritionists, hoof trimmers and other AI techs serving cattle owners around Waco and Valley Mills, TX.',
            ],
        ]);
    }
}
