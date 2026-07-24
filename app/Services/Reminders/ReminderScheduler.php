<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Enums\CattleStatus;
use App\Models\Cattle;
use App\Models\Reminder;
use App\Support\NurtureContent;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Central scheduling seam for the reminder engine (#246/#247). Every trigger
 * (breeding follow-ups, the calving rebreed loop, data-driven nudges) routes
 * through here so idempotency and the "don't schedule for a gone cow" rule are
 * enforced in one place:
 *
 *  - `dedupe_key` guarantees a re-fired trigger or a repeated daily sweep never
 *    schedules the same reminder twice.
 *  - A reminder is never created for an INACTIVE animal (§10b — marking a cow
 *    inactive stops her reminders; we also refuse to schedule new ones).
 *  - The email copy for content templates is enriched with a blog link so
 *    content does double duty (§5.7).
 */
class ReminderScheduler
{
    public function __construct(private NurtureContent $content) {}

    /**
     * Schedule a reminder against a remindable model. Returns the row (existing
     * or newly created), or null when scheduling was refused (inactive animal).
     *
     * @param  array<string, mixed>  $payload
     */
    public function schedule(
        Model $remindable,
        int $category,
        string $template,
        CarbonInterface $fireAt,
        string $dedupeKey,
        array $payload = [],
        string $recipientRole = 'owner',
    ): ?Reminder {
        if ($remindable instanceof Cattle && $remindable->status === CattleStatus::Inactive) {
            return null;
        }

        $teamId = $remindable->getAttribute('team_id');

        if ($teamId === null) {
            return null;
        }

        $payload = $this->withContent($template, $payload);

        return Reminder::firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'team_id' => $teamId,
                'remindable_type' => $remindable::class,
                'remindable_id' => $remindable->getKey(),
                'fire_at' => $fireAt,
                'category' => $category,
                'template' => $template,
                'recipient_role' => $recipientRole,
                'payload' => $payload === [] ? null : $payload,
                'status' => Reminder::STATUS_PENDING,
            ],
        );
    }

    /**
     * Merge in a blog link for content templates (those with a `pillar`), so
     * the nurture email can link to a matching post (§5.7).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withContent(string $template, array $payload): array
    {
        $pillar = config("reminders.templates.{$template}.pillar");

        if ($pillar === null || isset($payload['post_url'])) {
            return $payload;
        }

        return array_merge($payload, $this->content->forPillar((string) $pillar));
    }
}
