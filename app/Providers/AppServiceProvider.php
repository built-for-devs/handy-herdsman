<?php

namespace App\Providers;

use App\Events\CattleCalved;
use App\Events\CattleDeactivated;
use App\Events\VisitCompleted;
use App\Listeners\CancelPendingRemindersForCattle;
use App\Listeners\ScheduleRebreedLoop;
use App\Listeners\ScheduleVisitFollowUps;
use App\Models\Cattle;
use App\Models\HealthRecord;
use App\Models\Media;
use App\Models\Team;
use App\Models\VisitCompletion;
use App\Notifications\Channels\SentDmChannel;
use App\Observers\CattleObserver;
use App\Observers\VisitCompletionObserver;
use App\Policies\CattlePolicy;
use App\Policies\HealthRecordPolicy;
use App\Policies\MediaPolicy;
use App\Services\Billing\CashierPaymentGateway;
use App\Services\Billing\PaymentGateway;
use App\Services\Geocoding\Geocoder;
use App\Services\Geocoding\GoogleGeocoder;
use App\Services\Geocoding\NullGeocoder;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
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

        // Charge-on-confirm goes through Cashier only (spec §5.6). Bound behind
        // an interface so tests fake the gateway and never hit real Stripe.
        $this->app->bind(PaymentGateway::class, CashierPaymentGateway::class);
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
        // the actual completed timestamp and flags any conflict (§10b, 1.3), and
        // auto-promotes the client to `active` on their first completed visit.
        VisitCompletion::observe(VisitCompletionObserver::class);

        // Register the config-driven sent.dm SMS channel so notifications can
        // route to `sentdm` (spec §5.7 / §5.4 — informal-proposal send).
        Notification::resolved(function (ChannelManager $service) {
            $service->extend('sentdm', fn ($app) => $app->make(SentDmChannel::class));
        });

        // Vet read-only scoping + records permissions (spec §4, §10b).
        Gate::policy(Cattle::class, CattlePolicy::class);
        Gate::policy(HealthRecord::class, HealthRecordPolicy::class);
        Gate::policy(Media::class, MediaPolicy::class);

        // Marking a cow inactive stops all her pending reminders immediately
        // (§10b — Cattle status). The observer fires the event; M9 also plugs
        // its own listeners into this same hook.
        Cattle::observe(CattleObserver::class);
        Event::listen(CattleDeactivated::class, CancelPendingRemindersForCattle::class);

        // M9 reminder engine (§5.7): a first calving starts the post-calving
        // rebreed loop; a completed visit schedules the around-a-breeding
        // follow-ups and any body-condition nutrition nudge.
        Event::listen(CattleCalved::class, ScheduleRebreedLoop::class);
        Event::listen(VisitCompleted::class, ScheduleVisitFollowUps::class);
    }
}
