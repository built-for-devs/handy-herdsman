<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Messages\SentDmMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The on-call pager (spec §6.5). When a client submits an on-call request the
 * app IMMEDIATELY SMS-alerts Jeff — a standing-heat window is ~6–12h and a
 * calving emergency is minutes-to-hours, so delivery cannot be the weak link.
 *
 * Deliberately NOT queued: it is sent synchronously so the alert goes out the
 * instant the request is created (the SMS channel itself degrades gracefully
 * when no provider key is configured).
 */
class OnCallPagerNotification extends Notification
{
    public function __construct(
        public Booking $booking,
        public string $summary,
        public string $guidance,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['sentdm'];

        if (! empty($notifiable->routeNotificationFor('mail', $this))) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toSentDm(object $notifiable): SentDmMessage
    {
        return new SentDmMessage(
            sprintf('ON-CALL: %s. %s Open the app to respond.', $this->summary, $this->guidance)
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('On-call request — Handy Herdsman')
            ->line($this->summary)
            ->line($this->guidance)
            ->action('Respond in the app', url('/admin/bookings'));
    }
}
