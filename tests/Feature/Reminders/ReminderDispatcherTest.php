<?php

declare(strict_types=1);

namespace Tests\Feature\Reminders;

use App\Jobs\SendReminder;
use App\Models\Reminder;
use App\Services\Reminders\ReminderDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The scheduler/queue backbone (#244, §5.7). A daily/hourly command finds due
 * reminders and queues send jobs. Non-urgent messages that land inside quiet
 * hours defer to the next allowed window; emergency (bypass) categories send at
 * any hour. The atomic claim makes it idempotent — re-running never double-sends.
 */
class ReminderDispatcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'UTC',
            'reminders.quiet_hours.start' => '21:00',
            'reminders.quiet_hours.end' => '08:00',
            'reminders.categories.1.bypasses_quiet_hours' => true,
            'reminders.categories.3.bypasses_quiet_hours' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function dispatcher(): ReminderDispatcher
    {
        return app(ReminderDispatcher::class);
    }

    public function test_non_urgent_reminder_at_4am_defers_to_8am(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 7, 24, 4, 0, 0, 'UTC'));

        $reminder = Reminder::factory()->category(3)->fireAt(now())->create();

        $this->dispatcher()->dispatchDue();

        Queue::assertNothingPushed();

        $reminder->refresh();
        $this->assertSame(Reminder::STATUS_PENDING, $reminder->status);
        $this->assertSame(
            '2026-07-24 08:00:00',
            $reminder->quiet_hours_deferred_to?->format('Y-m-d H:i:s'),
        );
    }

    public function test_category_1_emergency_sends_at_2am(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 7, 24, 2, 0, 0, 'UTC'));

        $reminder = Reminder::factory()->category(1)->fireAt(now())->create();

        $this->dispatcher()->dispatchDue();

        Queue::assertPushed(SendReminder::class, 1);
        $this->assertSame(Reminder::STATUS_QUEUED, $reminder->fresh()->status);
    }

    public function test_rerunning_never_double_sends(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 7, 24, 12, 0, 0, 'UTC'));

        Reminder::factory()->category(3)->fireAt(now()->subHour())->create();

        $this->dispatcher()->dispatchDue();
        $this->dispatcher()->dispatchDue();

        Queue::assertPushed(SendReminder::class, 1);
    }

    public function test_deferred_reminder_dispatches_once_the_window_opens(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 7, 24, 4, 0, 0, 'UTC'));

        $reminder = Reminder::factory()->category(3)->fireAt(now())->create();

        $this->dispatcher()->dispatchDue();
        Queue::assertNothingPushed();

        // The window opens at 8am — now it should dispatch.
        Carbon::setTestNow(Carbon::create(2026, 7, 24, 8, 0, 0, 'UTC'));
        $this->dispatcher()->dispatchDue();

        Queue::assertPushed(SendReminder::class, 1);
        $this->assertSame(Reminder::STATUS_QUEUED, $reminder->fresh()->status);
    }
}
