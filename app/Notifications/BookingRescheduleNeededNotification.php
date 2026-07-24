<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Messages\SentDmMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a client that a newly-added blackout collides with their booking and
 * proposes alternative slots to confirm (spec §6.3). Sent when Jeff blacks out
 * days that already had bookings on them.
 *
 * @param  list<string>  $proposals  Human-readable alternative slots.
 */
class BookingRescheduleNeededNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $proposals
     */
    public function __construct(
        public Booking $booking,
        public array $proposals,
    ) {}

    /**
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

        return $channels === [] ? ['mail'] : $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('We need to reschedule your visit — Handy Herdsman')
            ->line('Jeff has become unavailable on your booked date, so we need to reschedule.');

        if ($this->proposals !== []) {
            $mail->line('Here are some alternative times that keep your protocol timing:');

            foreach ($this->proposals as $proposal) {
                $mail->line('• '.$proposal);
            }
        }

        return $mail->line('Please reply or open the app to confirm a new time.');
    }

    public function toSentDm(object $notifiable): SentDmMessage
    {
        $first = $this->proposals[0] ?? 'a new time';

        return new SentDmMessage(
            "Handy Herdsman: we need to reschedule your visit. Suggested: {$first}. Open the app to confirm."
        );
    }
}
