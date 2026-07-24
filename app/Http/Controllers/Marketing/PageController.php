<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    /** About — Jeff's story + the honest vet-license line as a trust signal (§5.1). */
    public function about(): Response
    {
        return Inertia::render('marketing/About', [
            'notOffered' => config('marketing.not_offered'),
        ]);
    }

    /** Contact — includes the on-call text number (§5.1). */
    public function contact(): Response
    {
        return Inertia::render('marketing/Contact', [
            'contact' => config('marketing.contact'),
            'address' => config('marketing.address'),
            'serviceRangeMiles' => config('marketing.service_range_miles'),
        ]);
    }
}
