<?php

declare(strict_types=1);

namespace App\Http\Controllers\ForSale;

use App\Http\Controllers\Controller;
use App\Models\Cattle;
use App\Services\ForSale\ForSaleListingPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The clients-only cattle-for-sale bulletin board (spec §5.9). Every listing
 * is visible across authenticated clients (a shared board, §5.9) — there is NO
 * public/unauthenticated route and NO transaction flow. Each listing exposes
 * only the fields the seller chose to share, via {@see ForSaleListingPresenter}.
 */
class ForSaleBoardController extends Controller
{
    public function __construct(private readonly ForSaleListingPresenter $presenter) {}

    public function index(Request $request): Response
    {
        $listings = Cattle::query()
            ->listedForSale()
            ->with(['media' => fn ($query) => $query->orderByDesc('taken_at')->orderByDesc('id')])
            ->latest('updated_at')
            ->get()
            ->map(fn (Cattle $cattle) => $this->presenter->present($cattle))
            ->values();

        return Inertia::render('for-sale/Index', [
            'listings' => $listings,
        ]);
    }

    public function show(Request $request, Cattle $cattle): Response
    {
        abort_unless($cattle->for_sale, 404);

        $cattle->load(['media' => fn ($query) => $query->orderByDesc('taken_at')->orderByDesc('id')]);

        return Inertia::render('for-sale/Show', [
            'listing' => $this->presenter->present($cattle),
        ]);
    }
}
