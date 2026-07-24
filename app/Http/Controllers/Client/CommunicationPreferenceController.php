<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdateCommunicationPreferenceRequest;
use App\Models\Client;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Client communication preferences & consent (spec §5.7). The owner sets the
 * per-category channel and the separate, timestamped SMS consent for their
 * client record.
 */
class CommunicationPreferenceController extends Controller
{
    public function edit(Request $request): Response
    {
        $client = $this->client($request);

        $consent = [];
        foreach (array_keys(config('reminders.categories')) as $category) {
            $consent[$category] = $client->hasSmsConsent($category);
        }

        return Inertia::render('settings/Communications', [
            'categories' => $this->categoriesPayload(),
            'channelPrefs' => array_replace(
                Client::defaultChannelPrefs(),
                $client->channel_prefs ?? [],
            ),
            'smsConsent' => $consent,
            'staffOverride' => $client->staff_channel_override,
            'staffOverrideNote' => $client->staff_channel_override_note,
        ]);
    }

    public function update(UpdateCommunicationPreferenceRequest $request): RedirectResponse
    {
        $client = $this->client($request);

        $client->channel_prefs = $request->input('channel_prefs');

        foreach (array_keys(config('reminders.categories')) as $category) {
            $granted = (bool) $request->input("sms_consent.{$category}", false);

            if ($granted && ! $client->hasSmsConsent($category)) {
                $client->grantSmsConsent($category); // timestamped now
            } elseif (! $granted) {
                $client->revokeSmsConsent($category);
            }
        }

        $client->save();

        return back();
    }

    /** @return array<int, array<string, mixed>> */
    private function categoriesPayload(): array
    {
        $payload = [];

        foreach (config('reminders.categories') as $number => $category) {
            $payload[] = [
                'number' => $number,
                'key' => $category['key'],
                'label' => $category['label'],
                'default_channel' => $category['default_channel'],
                'bypasses_quiet_hours' => $category['bypasses_quiet_hours'],
            ];
        }

        return $payload;
    }

    private function client(Request $request): Client
    {
        /** @var Team|null $team */
        $team = $request->user()->currentTeam;

        abort_if($team === null, HttpResponse::HTTP_FORBIDDEN);
        abort_unless(
            $team->owner_id === $request->user()->id || $request->user()->isStaff(),
            HttpResponse::HTTP_FORBIDDEN,
        );

        $client = $team->client;

        abort_if($client === null, HttpResponse::HTTP_NOT_FOUND);

        return $client;
    }
}
