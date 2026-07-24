<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Support\QuietHours;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Quiet hours (spec §5.7, §10b). Non-urgent messages defer to the next allowed
 * window (~8am–9pm); act-now / emergency categories bypass quiet hours.
 */
class QuietHoursTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'UTC',
            'reminders.quiet_hours.start' => '21:00',
            'reminders.quiet_hours.end' => '08:00',
        ]);
    }

    /**
     * @return array<string, array{int, bool}>
     */
    public static function hourProvider(): array
    {
        return [
            '4am is quiet' => [4, true],
            '7am is quiet' => [7, true],
            '8am is allowed' => [8, false],
            'noon is allowed' => [12, false],
            '8pm is allowed' => [20, false],
            '9pm is quiet' => [21, true],
            '11pm is quiet' => [23, true],
        ];
    }

    #[DataProvider('hourProvider')]
    public function test_quiet_window_wraps_past_midnight(int $hour, bool $expectedQuiet): void
    {
        $time = Carbon::create(2026, 7, 24, $hour, 0, 0, 'UTC');

        $this->assertSame($expectedQuiet, (new QuietHours)->isWithinQuietHours($time));
    }

    public function test_non_urgent_message_defers_to_next_morning(): void
    {
        $quiet = new QuietHours;

        $lateNight = Carbon::create(2026, 7, 24, 23, 30, 0, 'UTC');
        $resolved = $quiet->resolveSendTimeForCategory(3, $lateNight);

        $this->assertSame('2026-07-25 08:00:00', $resolved->format('Y-m-d H:i:s'));
    }

    public function test_early_morning_message_defers_to_same_day_open(): void
    {
        $quiet = new QuietHours;

        $preDawn = Carbon::create(2026, 7, 24, 4, 0, 0, 'UTC');
        $resolved = $quiet->resolveSendTimeForCategory(3, $preDawn);

        $this->assertSame('2026-07-24 08:00:00', $resolved->format('Y-m-d H:i:s'));
    }

    public function test_message_during_open_hours_is_not_deferred(): void
    {
        $quiet = new QuietHours;

        $midday = Carbon::create(2026, 7, 24, 14, 0, 0, 'UTC');
        $resolved = $quiet->resolveSendTimeForCategory(3, $midday);

        $this->assertTrue($midday->equalTo($resolved));
    }

    public function test_emergency_category_bypasses_quiet_hours(): void
    {
        // Category 1 (time-sensitive / act-now) bypasses quiet hours (§10b).
        config(['reminders.categories.1.bypasses_quiet_hours' => true]);

        $quiet = new QuietHours;
        $twoAm = Carbon::create(2026, 7, 24, 2, 0, 0, 'UTC');

        $resolved = $quiet->resolveSendTimeForCategory(1, $twoAm);

        $this->assertTrue($twoAm->equalTo($resolved), 'Emergency messages send at any hour.');
    }
}
