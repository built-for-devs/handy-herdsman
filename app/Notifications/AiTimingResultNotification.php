<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Messages\SentDmMessage;
use App\Support\Calculators\AiTimingResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The emailable/textable AI-timing result — an INFORMAL PROPOSAL a visitor
 * sends to themselves (spec §5.4, issue 2.5). Queued so a slow provider never
 * blocks the request; each channel degrades gracefully when unconfigured.
 */
class AiTimingResultNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AiTimingResult $result) {}

    /**
     * Route to whichever contact channels the visitor actually supplied.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (! empty($notifiable->routeNotificationFor('mail', $this))) {
            $channels[] = 'mail';
        }

        if (! empty($notifiable->routeNotificationFor('sentdm', $this))) {
            $channels[] = 'sentdm';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Your AI timing plan — Handy Herdsman')
            ->greeting('Your AI timing plan')
            ->line("Here is the timed-AI schedule for your {$this->result->animalType}. "
                .'All times are shown in '.$this->result->timezone.'.');

        foreach ($this->result->visits as $visit) {
            $line = "**{$visit['title']}** — {$visit['when']}";

            if ($visit['window'] !== null) {
                $line .= " (acceptable window: {$visit['window']})";
            }

            $mail->line($line)->line($visit['explanation']);
        }

        return $mail
            ->line('This is an informal proposal to help you plan — not a confirmed booking.')
            ->action('Book with Jeff', url('/calculators/ai-timing'))
            ->line('Timing is everything with AI. Reach out and we will get you on the calendar.');
    }

    public function toSentDm(object $notifiable): SentDmMessage
    {
        return new SentDmMessage($this->result->smsSummary());
    }
}
