<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\MessageChannel;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Communication preferences & consent (spec §5.7). SMS consent is separate and
 * timestamped; a phone number is not permission to auto-text. Each category is
 * email/text/both and at least one channel is always on.
 */
class CommunicationConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_defaults_follow_config(): void
    {
        $client = Client::factory()->create(['channel_prefs' => Client::defaultChannelPrefs()]);

        // Cat 1 defaults to both, cat 4 to email-only (§5.7).
        $this->assertSame(MessageChannel::Both, $client->channelForCategory(1));
        $this->assertSame(MessageChannel::Email, $client->channelForCategory(4));
    }

    public function test_sms_consent_is_separate_and_timestamped(): void
    {
        $client = Client::factory()->create(['consent_sms' => []]);

        $this->assertFalse($client->hasSmsConsent(1));

        $at = Carbon::parse('2026-07-01 12:00:00');
        $client->grantSmsConsent(1, $at);
        $client->save();

        $this->assertTrue($client->fresh()->hasSmsConsent(1));
        $this->assertTrue($at->equalTo($client->fresh()->smsConsentAt(1)));
    }

    public function test_a_phone_number_alone_does_not_permit_texts(): void
    {
        // Client has a phone but has NOT consented to SMS for category 1.
        $client = Client::factory()->create([
            'phone' => '254-555-0100',
            'channel_prefs' => ['1' => 'both'],
            'consent_sms' => [],
        ]);

        // The stated preference is "both", but without consent it degrades to email.
        $this->assertSame(MessageChannel::Both, $client->channelForCategory(1));
        $this->assertSame(MessageChannel::Email, $client->resolvedChannel(1));
    }

    public function test_granting_sms_consent_unlocks_the_text_channel(): void
    {
        $client = Client::factory()->create([
            'channel_prefs' => ['1' => 'both'],
            'consent_sms' => [],
        ]);

        $client->grantSmsConsent(1);

        $this->assertSame(MessageChannel::Both, $client->resolvedChannel(1));
        $this->assertTrue($client->resolvedChannel(1)->includesText());
    }

    public function test_revoking_sms_consent_removes_the_text_channel_again(): void
    {
        $client = Client::factory()->create([
            'channel_prefs' => ['2' => 'text'],
            'consent_sms' => [],
        ]);

        $client->grantSmsConsent(2);
        $this->assertTrue($client->resolvedChannel(2)->includesText());

        $client->revokeSmsConsent(2);
        // A text-only preference with no consent falls back to email.
        $this->assertSame(MessageChannel::Email, $client->resolvedChannel(2));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function channelProvider(): array
    {
        return [
            'email' => ['email'],
            'text' => ['text'],
            'both' => ['both'],
        ];
    }

    #[DataProvider('channelProvider')]
    public function test_every_category_choice_keeps_at_least_one_channel_on(string $channel): void
    {
        $client = Client::factory()->create([
            'channel_prefs' => ['1' => $channel],
            'consent_sms' => ['1' => now()->toIso8601String()],
        ]);

        $resolved = $client->resolvedChannel(1);

        $this->assertTrue(
            $resolved->includesEmail() || $resolved->includesText(),
            'At least one channel must always remain on.'
        );
    }

    public function test_staff_override_wins_over_client_preference(): void
    {
        $client = Client::factory()->create([
            'channel_prefs' => ['3' => 'email'],
            'consent_sms' => ['3' => now()->toIso8601String()],
        ]);

        $client->setStaffChannelOverride(['3' => 'text'], 'Never checks email — text him.');
        $client->save();

        $this->assertSame(MessageChannel::Text, $client->channelForCategory(3));
        $this->assertSame('Never checks email — text him.', $client->fresh()->staff_channel_override_note);
    }
}
