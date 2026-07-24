<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\RateConfig;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    /**
     * FAQ page (§5.1, §5.2b). Includes straw-cost REFERENCE ranges from
     * rate_config, clearly labeled as industry reference — explicitly NOT our
     * prices (the client pays their own source).
     */
    public function __invoke(): Response
    {
        $faqs = Faq::where('active', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category')
            ->map(fn (Collection $items, string $category) => [
                'category' => $category,
                'items' => $items->map(fn (Faq $f) => [
                    'question' => $f->question,
                    'answer' => $f->answer,
                ])->values(),
            ])
            ->values();

        return Inertia::render('marketing/Faq', [
            'faqs' => $faqs,
            'strawCostReference' => RateConfig::value('straw_cost_reference'),
            'strawCostDisclaimer' => 'These are industry reference ranges to help you budget — the client pays their own semen source directly. They are NOT Handy Herdsman prices or line items.',
        ]);
    }
}
