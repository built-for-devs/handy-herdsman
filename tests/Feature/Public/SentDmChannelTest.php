<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Notifications\Channels\SentDmChannel;
use App\Notifications\Messages\SentDmMessage;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Spec §5.7 / §5.4 — the config-driven sent.dm SMS channel. Sends when
 * credentials are configured; gracefully logs (never errors) when they are not.
 */
class SentDmChannelTest extends TestCase
{
    private function notification(): Notification
    {
        return new class extends Notification
        {
            public function toSentDm(object $notifiable): SentDmMessage
            {
                return new SentDmMessage('Your AI timing plan');
            }
        };
    }

    public function test_it_posts_to_the_provider_when_configured(): void
    {
        config()->set('services.sentdm.key', 'test-key');
        config()->set('services.sentdm.endpoint', 'https://api.sent.dm/v1/messages');
        Http::fake();

        (new SentDmChannel)->send(
            (new AnonymousNotifiable)->route('sentdm', '+15125551234'),
            $this->notification(),
        );

        Http::assertSent(fn ($request) => $request->url() === 'https://api.sent.dm/v1/messages'
            && $request['to'] === '+15125551234'
            && $request['text'] === 'Your AI timing plan');
    }

    public function test_it_logs_and_does_not_error_when_unconfigured(): void
    {
        config()->set('services.sentdm.key', null);
        Http::fake();
        Log::spy();

        (new SentDmChannel)->send(
            (new AnonymousNotifiable)->route('sentdm', '+15125551234'),
            $this->notification(),
        );

        Http::assertNothingSent();
        Log::shouldHaveReceived('info')->once();
    }
}
