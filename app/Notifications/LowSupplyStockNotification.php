<?php

namespace App\Notifications;

use App\Models\Supply;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Alerts staff that supplies have dropped to/below their reorder threshold so
 * Jeff isn't caught short mid-season (§5.6b). Queued so a stock check never
 * blocks the request that triggered it.
 *
 * @implements ShouldQueue
 */
class LowSupplyStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Supply>  $supplies
     */
    public function __construct(public $supplies) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Low supply stock — time to reorder')
            ->line('The following supplies are at or below their reorder threshold:');

        foreach ($this->supplies as $supply) {
            $mail->line(sprintf(
                '%s — %s %s on hand (threshold %s)',
                $supply->item,
                rtrim(rtrim((string) $supply->on_hand, '0'), '.'),
                $supply->unit ?? 'units',
                rtrim(rtrim((string) $supply->low_stock_threshold, '0'), '.'),
            ));
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'supplies' => $this->supplies
                ->map(fn (Supply $s) => [
                    'id' => $s->id,
                    'item' => $s->item,
                    'on_hand' => (float) $s->on_hand,
                    'low_stock_threshold' => (float) $s->low_stock_threshold,
                ])
                ->values()
                ->all(),
        ];
    }
}
