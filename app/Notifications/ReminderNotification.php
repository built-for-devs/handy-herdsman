<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\MessageChannel;
use App\Jobs\SendReminder;
use App\Models\Reminder;
use App\Notifications\Channels\SentDmChannel;
use App\Notifications\Messages\SentDmMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The generic reminder/nurture message (#245). One notification renders every
 * template — the copy comes from `config('reminders.templates')` (config over
 * code) and the delivery channel is resolved per-category from the client's
 * prefs + staff override + SMS consent, then passed in here.
 *
 * Sent synchronously from within the queued {@see SendReminder} job,
 * so it is deliberately NOT ShouldQueue itself (the job is the queued unit).
 * Each concrete channel still degrades gracefully when its provider key is
 * missing (see {@see SentDmChannel}).
 */
class ReminderNotification extends Notification
{
    public function __construct(
        public Reminder $reminder,
        public MessageChannel $channel,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if ($this->channel->includesEmail() && ! empty($notifiable->routeNotificationFor('mail', $this))) {
            $channels[] = 'mail';
        }

        if ($this->channel->includesText() && ! empty($notifiable->routeNotificationFor('sentdm', $this))) {
            $channels[] = 'sentdm';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $template = $this->template();
        $payload = $this->reminder->payload ?? [];

        $mail = (new MailMessage)
            ->subject((string) ($template['subject'] ?? 'A reminder from Handy Herdsman'))
            ->line((string) ($template['line'] ?? ''));

        if (! empty($template['cta_label']) && ! empty($template['cta_path'])) {
            $mail->action((string) $template['cta_label'], url((string) $template['cta_path']));
        }

        // Nurture emails pull from the blog so content does double duty (§5.7).
        if (! empty($payload['post_title']) && ! empty($payload['post_url'])) {
            $mail->line('Related reading: ['.$payload['post_title'].']('.$payload['post_url'].')');
        }

        return $mail;
    }

    public function toSentDm(object $notifiable): SentDmMessage
    {
        $template = $this->template();

        return new SentDmMessage((string) ($template['sms'] ?? $template['line'] ?? 'Handy Herdsman reminder'));
    }

    /**
     * @return array<string, mixed>
     */
    private function template(): array
    {
        return (array) config('reminders.templates.'.$this->reminder->template, []);
    }
}
