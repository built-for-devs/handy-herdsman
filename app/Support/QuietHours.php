<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Quiet-hours window for outbound messages (spec §5.7, §10b). Non-urgent
 * messages defer to the next allowed window; act-now / emergency categories
 * bypass quiet hours entirely (e.g. a 2am calving confirmation still sends).
 * Window and category bypass flags are config-driven (config/reminders.php).
 */
final class QuietHours
{
    /** Whether the given moment falls inside the (possibly overnight) quiet window. */
    public function isWithinQuietHours(CarbonInterface $time): bool
    {
        $local = $time->copy()->setTimezone(config('app.timezone'));
        $start = $local->copy()->setTimeFromTimeString($this->start());
        $end = $local->copy()->setTimeFromTimeString($this->end());

        // Overnight window (e.g. 21:00 → 08:00) wraps past midnight.
        if ($start->greaterThan($end)) {
            return $local->greaterThanOrEqualTo($start) || $local->lessThan($end);
        }

        return $local->greaterThanOrEqualTo($start) && $local->lessThan($end);
    }

    /** The next moment a non-urgent message may send (unchanged if already allowed). */
    public function nextAllowedTime(CarbonInterface $time): CarbonInterface
    {
        if (! $this->isWithinQuietHours($time)) {
            return $time->copy();
        }

        $local = $time->copy()->setTimezone(config('app.timezone'));
        $end = $local->copy()->setTimeFromTimeString($this->end());

        if ($local->greaterThanOrEqualTo($end)) {
            $end = $end->addDay();
        }

        return $end->setTimezone($time->getTimezone());
    }

    /** Resolve the actual send time; emergency/act-now categories bypass quiet hours. */
    public function resolveSendTime(CarbonInterface $desired, bool $bypassQuietHours = false): CarbonInterface
    {
        if ($bypassQuietHours) {
            return $desired->copy();
        }

        return $this->nextAllowedTime($desired);
    }

    /** Resolve the send time for a preference category, honoring its bypass flag. */
    public function resolveSendTimeForCategory(int $category, CarbonInterface $desired): CarbonInterface
    {
        $bypass = (bool) config("reminders.categories.{$category}.bypasses_quiet_hours", false);

        return $this->resolveSendTime($desired, $bypass);
    }

    private function start(): string
    {
        return (string) config('reminders.quiet_hours.start');
    }

    private function end(): string
    {
        return (string) config('reminders.quiet_hours.end');
    }
}
