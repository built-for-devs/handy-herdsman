<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Support\PriceRuleFormatter;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    /** Category slug → human label, for grouping the services page. */
    private const CATEGORY_LABELS = [
        'breeding' => 'Breeding & Repro',
        'calving' => 'Calving',
        'ranch_hand' => 'Cattle Handling / Ranch-Hand',
        'health' => 'Non-Vet Health Tasks',
        'consult' => 'Consult / Onboarding',
        'milking' => 'Milking',
    ];

    /**
     * Public services page — rendered entirely from the services table (§5.2).
     * NO hardcoded copy or prices. "Not offered" items are shown as trust
     * signals from config (§2, §5.2).
     */
    public function __invoke(): Response
    {
        $groups = Service::where('active', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category')
            ->map(fn (Collection $services, string $category) => [
                'category' => $category,
                'label' => self::CATEGORY_LABELS[$category] ?? ucfirst($category),
                'services' => $services->map(fn (Service $s) => [
                    'name' => $s->name,
                    'slug' => $s->slug,
                    'description' => $s->description,
                    'type' => $s->type,
                    'price' => PriceRuleFormatter::display($s->price_rule),
                ])->values(),
            ])
            ->values();

        return Inertia::render('marketing/Services', [
            'groups' => $groups,
            'notOffered' => config('marketing.not_offered'),
        ]);
    }
}
