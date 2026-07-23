<?php

namespace App\Providers;

use App\Models\Team;
use App\Models\VisitCompletion;
use App\Observers\VisitCompletionObserver;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The Team is the tenant and the Stripe customer — bill per client
        // account, not per user (spec §4, §5.6).
        Cashier::useCustomerModel(Team::class);

        // No Stripe invoicing and no tax handling anywhere (spec §5.6, §10b).
        Cashier::calculateTaxes(false);

        // Completing a Visit 2 automatically recomputes its Visit 3 window from
        // the actual completed timestamp and flags any conflict (§10b, 1.3).
        VisitCompletion::observe(VisitCompletionObserver::class);
    }
}
