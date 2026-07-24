<?php

namespace App\Providers;

use App\Events\CattleDeactivated;
use App\Listeners\CancelPendingRemindersForCattle;
use App\Models\Cattle;
use App\Models\HealthRecord;
use App\Models\Media;
use App\Models\Team;
use App\Models\VisitCompletion;
use App\Observers\CattleObserver;
use App\Observers\VisitCompletionObserver;
use App\Policies\CattlePolicy;
use App\Policies\HealthRecordPolicy;
use App\Policies\MediaPolicy;
use App\Services\Geocoding\Geocoder;
use App\Services\Geocoding\GoogleGeocoder;
use App\Services\Geocoding\NullGeocoder;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Config-driven geocoder (spec §5.10, §9): use Google when a key is
        // present, otherwise the null driver so onboarding never hardcodes a
        // key and never fails when one is absent.
        $this->app->singleton(Geocoder::class, function ($app): Geocoder {
            $key = config('geocoding.google.key');

            if (config('geocoding.driver') === 'google' && filled($key)) {
                return new GoogleGeocoder(
                    $app->make(HttpClient::class),
                    (string) $key,
                    (string) config('geocoding.google.endpoint'),
                );
            }

            return new NullGeocoder;
        });
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

        // Vet read-only scoping + records permissions (spec §4, §10b).
        Gate::policy(Cattle::class, CattlePolicy::class);
        Gate::policy(HealthRecord::class, HealthRecordPolicy::class);
        Gate::policy(Media::class, MediaPolicy::class);

        // Auto-promote a client to `active` on their first completed visit (§10b).
        VisitCompletion::observe(VisitCompletionObserver::class);

        // Marking a cow inactive stops all her pending reminders immediately
        // (§10b — Cattle status). The observer fires the event; M9 also plugs
        // its own listeners into this same hook.
        Cattle::observe(CattleObserver::class);
        Event::listen(CattleDeactivated::class, CancelPendingRemindersForCattle::class);
    }
}
