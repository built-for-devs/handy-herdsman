<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitation email for a team member or vet (spec §4, §5.7). Queued and sent
 * via the mail channel (Resend in production, behind Laravel's notification
 * abstraction). This is a transactional invite, not an automated client
 * notification, so it is sent directly to the invited address.
 */
class TeamInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly TeamInvitation $invitation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $team = $this->invitation->team;
        $url = route('team-invitations.show', ['token' => $this->invitation->token]);

        return (new MailMessage)
            ->subject("You've been invited to {$team->name} on Handy Herdsman")
            ->greeting('Hello!')
            ->line("You've been invited to join {$team->name} as {$this->invitation->role->label()}.")
            ->action('Accept invitation', $url)
            ->line('If you did not expect this invitation, you can ignore this email.');
    }
}
