<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Coming soon" shells for Resources pages that a later milestone fills in, so
 * the nav/Resources dropdown is complete now (issue 2.1). The AI Timing (2.5)
 * and Due Date (2.6) calculators depend on the M1 timing engine and are built
 * separately; the Cattle-for-Sale board (§5.9) is a separate milestone.
 */
class PlaceholderController extends Controller
{
    public function aiTiming(): Response
    {
        return $this->render(
            'AI Timing Calculator',
            'Pick your first-visit date and animal type to see your V2/V3 breeding windows and a plain-English visit plan. This tool is on its way.',
        );
    }

    public function dueDate(): Response
    {
        return $this->render(
            'Due Date / Gestation Calculator',
            'Enter a breeding date and breed to estimate the calving window and prep milestones. This tool is on its way.',
        );
    }

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
