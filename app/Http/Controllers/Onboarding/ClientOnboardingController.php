<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Enums\ClientStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\StoreOnboardingRequest;
use App\Jobs\GeocodeClient;
use App\Models\Client;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\PermissionRegistrar;

/**
 * Client onboarding (spec §4, §5.6). Creates the client's team (tenant),
 * assigns the owner role, captures the timestamped service-agreement +
 * liability-waiver acceptance, and queues a one-time geocode of the address.
 */
class ClientOnboardingController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('onboarding/Client', [
            'agreementVersion' => config('app.name').' Service Agreement',
        ]);
    }

    public function store(StoreOnboardingRequest $request): RedirectResponse
    {
        $user = $request->user();

        $client = DB::transaction(function () use ($request, $user): Client {
            $team = Team::create([
                'owner_id' => $user->id,
                'name' => $request->string('contact_name').' Ranch',
            ]);

            // Assign the owner role within this team's Spatie context (§4).
            app(PermissionRegistrar::class)->setPermissionsTeamId($team->id);
            $user->assignRole('client_owner');

            $user->forceFill(['current_team_id' => $team->id])->save();

            $client = new Client([
                'team_id' => $team->id,
                'contact_name' => $request->string('contact_name'),
                'email' => $request->string('email'),
                'phone' => $request->string('phone'),
                'address_line1' => $request->string('address_line1'),
                'address_line2' => $request->input('address_line2'),
                'city' => $request->string('city'),
                'state' => $request->string('state'),
                'postal_code' => $request->string('postal_code'),
                'status' => ClientStatus::New,
                'channel_prefs' => Client::defaultChannelPrefs(),
                'consent_sms' => [],
                'last_activity_at' => now(),
            ]);

            // Timestamp the agreement + waiver acceptance (§5.6).
            $client->acceptAgreementAndWaiver();
            $client->save();

            return $client;
        });

        // Geocode the address once, off-request (§5.10). No-op without a key.
        GeocodeClient::dispatch($client->id);

        return to_route('dashboard');
    }
}
