<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Coming soon" shells for Resources pages that a later milestone fills in, so
 * the nav/Resources dropdown is complete now (issue 2.1). The Cattle-for-Sale
 * board (§5.9) is a separate milestone; the AI Timing (2.5) and Due Date (2.6)
 * calculators are now live and served by their own controllers.
 */
class PlaceholderController extends Controller
{
    public function cattleForSale(): Response
    {
        return $this->render(
            'Cattle for Sale',
            'A bulletin board of cattle our clients have listed for sale. Listings are coming soon.',
        );
    }

    private function render(string $title, string $blurb): Response
    {
        return Inertia::render('marketing/ComingSoon', [
            'title' => $title,
            'blurb' => $blurb,
        ]);
    }
}
