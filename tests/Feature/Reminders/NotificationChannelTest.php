<?php

declare(strict_types=1);

namespace Tests\Feature\Reminders;

use App\Enums\MessageChannel;
use App\Jobs\SendReminder;
use App\Models\Cattle;
use App\Models\Client;
use App\Models\Reminder;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\ReminderNotification;
use App\Support\ReminderChannelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * Notification channels (#245, §5.7). The delivery channel is resolved per
 * category from the client's prefs, the staff override (which wins), and SMS
 * consent (text degrades to email without it). Recipients follow §10b: owner
 * gets everything, opted-in members do too, vets never. Category 4 (promotional)
 * stays email-only unless SMS is explicitly opted in (TCPA-safe).
 */
class NotificationChannelTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    private function resolver(): ReminderChannelResolver
    {
        return app(ReminderChannelResolver::class);
    }

    public function test_resolver_defaults_to_email_when_no_client_on_record(): void
    {
        $team = Team::factory()->create();

        $this->assertSame(MessageChannel::Email, $this->resolver()->forTeam($team, 1));
    }

    public function test_text_preference_with_consent_resolves_to_text(): void
    {
        $team = Team::factory()->create();
        $client = Client::factory()->for($team)->create([
            'channel_prefs' => [3 => MessageChannel::Text->value],
        ]);
        $client->grantSmsConsent(3);
        $client->save();

        $this->assertSame(MessageChannel::Text, $this->resolver()->forTeam($team, 3));
    }

    public function test_staff_override_wins_over_client_preference(): void
    {
        $team = Team::factory()->create();
        $client = Client::factory()->for($team)->create([
            'channel_prefs' => [3 => MessageChannel::Email->value],
            'staff_channel_override' => [3 => MessageChannel::Text->value],
        ]);
        $client->grantSmsConsent(3);
        $client->save();

        $this->assertSame(MessageChannel::Text, $this->resolver()->forTeam($team, 3));
    }

    public function test_sms_is_suppressed_without_category_consent(): void
    {
        $team = Team::factory()->create();
        Client::factory()->for($team)->create([
            'channel_prefs' => [3 => MessageChannel::Both->value],
            'consent_sms' => [],
        ]);

        // "both" degrades to email-only — a phone number is not consent (§5.7).
        $this->assertSame(MessageChannel::Email, $this->resolver()->forTeam($team, 3));
    }

    public function test_send_job_notifies_the_owner_over_both_channels_with_consent(): void
    {
        Notification::fake();

        $owner = User::factory()->create(['phone' => '254-555-0100']);
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $client = Client::factory()->for($team)->create([
            'channel_prefs' => [3 => MessageChannel::Both->value],
        ]);
        $client->grantSmsConsent(3);
        $client->save();

        $reminder = $this->queuedReminderFor($team, 3);

        (new SendReminder($reminder->id))->handle($this->resolver());

        Notification::assertSentTo(
            $owner,
            ReminderNotification::class,
            fn ($notification, array $channels) => in_array('mail', $channels, true)
                && in_array('sentdm', $channels, true),
        );

        $this->assertSame(Reminder::STATUS_SENT, $reminder->fresh()->status);
    }

    public function test_send_job_suppresses_sms_without_consent(): void
    {
        Notification::fake();

        $owner = User::factory()->create(['phone' => '254-555-0100']);
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        Client::factory()->for($team)->create([
            'channel_prefs' => [3 => MessageChannel::Both->value],
            'consent_sms' => [],
        ]);

        $reminder = $this->queuedReminderFor($team, 3);

        (new SendReminder($reminder->id))->handle($this->resolver());

        Notification::assertSentTo(
            $owner,
            ReminderNotification::class,
            fn ($notification, array $channels) => $channels === ['mail'],
        );
    }

    public function test_send_job_excludes_the_vet(): void
    {
        Notification::fake();

        $owner = User::factory()->create(['phone' => '254-555-0100']);
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        Client::factory()->for($team)->create();

        $vet = User::factory()->create(['email' => 'vet@example.com']);
        TeamInvitation::factory()->for($team)->accepted()->vet()->create([
            'email' => 'vet@example.com',
            'accepted_by' => $vet->id,
        ]);

        $reminder = $this->queuedReminderFor($team, 3);

        (new SendReminder($reminder->id))->handle($this->resolver());

        Notification::assertSentTo($owner, ReminderNotification::class);
        Notification::assertNotSentTo($vet, ReminderNotification::class);
    }

    private function queuedReminderFor(Team $team, int $category): Reminder
    {
        $cattle = Cattle::factory()->for($team)->create();

        return Reminder::factory()
            ->forCattle($cattle)
            ->category($category)
            ->template('rebreed_booking')
            ->status(Reminder::STATUS_QUEUED)
            ->create();
    }
}
