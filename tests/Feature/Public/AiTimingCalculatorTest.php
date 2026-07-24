<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\AnimalType;
use App\Notifications\AiTimingResultNotification;
use App\Services\Protocol\ProtocolTimingService;
use App\Support\Calculators\AiTimingResult;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Spec §5.4, issue 2.5 — the public AI Timing Calculator. The timing math is
 * the M1 engine's job (covered by ProtocolTimingServiceTest); these assert the
 * public endpoints delegate to it correctly and behave for edge cases.
 */
class AiTimingCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_the_calculator(): void
    {
        $this->get(route('calculators.ai-timing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/AiTimingCalculator')
                ->has('animalTypes', 4)
            );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function breedableTypes(): array
    {
        return [
            'cow' => ['cow'],
            'heifer' => ['heifer'],
        ];
    }

    #[DataProvider('breedableTypes')]
    public function test_calculate_returns_the_windows_from_the_m1_engine(string $type): void
    {
        $visit1 = '2026-03-02 08:00';
        $animalType = AnimalType::from($type);

        $expected = AiTimingResult::fromSchedule(
            app(ProtocolTimingService::class)->scheduleFromVisit1(CarbonImmutable::parse($visit1), $animalType),
            $animalType,
        )->toArray();

        $this->post(route('calculators.ai-timing.calculate'), [
            'visit1_at' => $visit1,
            'animal_type' => $type,
        ])->assertInertia(fn (Assert $page) => $page
            ->component('public/AiTimingCalculator')
            ->where('ineligible', null)
            ->where('result.visit_count', 3)
            ->where('result.recommended_at', $expected['recommended_at'])
            ->where('result.visits.2.window', $expected['visits'][2]['window'])
            ->where('result.visits', $expected['visits'])
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function ineligibleTypes(): array
    {
        return [
            'bull' => ['bull'],
            'steer' => ['steer'],
        ];
    }

    #[DataProvider('ineligibleTypes')]
    public function test_bulls_and_steers_get_friendly_ineligibility_copy_and_no_result(string $type): void
    {
        $this->post(route('calculators.ai-timing.calculate'), [
            'visit1_at' => '2026-03-02 08:00',
            'animal_type' => $type,
        ])->assertInertia(fn (Assert $page) => $page
            ->component('public/AiTimingCalculator')
            ->where('result', null)
            ->where('ineligible', fn (?string $msg) => $msg !== null && str_contains($msg, 'heifers and cows'))
        );
    }

    public function test_calculate_validates_input(): void
    {
        $this->post(route('calculators.ai-timing.calculate'), [
            'visit1_at' => 'not-a-date',
            'animal_type' => 'unicorn',
        ])->assertSessionHasErrors(['visit1_at', 'animal_type']);
    }

    public function test_send_dispatches_the_queued_notification_to_provided_channels(): void
    {
        Notification::fake();

        $this->post(route('calculators.ai-timing.send'), [
            'visit1_at' => '2026-03-02 08:00',
            'animal_type' => 'cow',
            'email' => 'rancher@example.com',
            'phone' => '+15125551234',
        ])->assertRedirect();

        Notification::assertSentOnDemand(
            AiTimingResultNotification::class,
            function (AiTimingResultNotification $notification, array $channels, AnonymousNotifiable $notifiable) {
                $this->assertInstanceOf(ShouldQueue::class, $notification);
                $this->assertContains('mail', $channels);
                $this->assertContains('sentdm', $channels);
                $this->assertSame('rancher@example.com', $notifiable->routeNotificationFor('mail'));
                $this->assertSame('+15125551234', $notifiable->routeNotificationFor('sentdm'));

                return true;
            }
        );
    }

    public function test_send_requires_at_least_one_contact_channel(): void
    {
        Notification::fake();

        $this->post(route('calculators.ai-timing.send'), [
            'visit1_at' => '2026-03-02 08:00',
            'animal_type' => 'cow',
        ])->assertSessionHasErrors(['email', 'phone']);

        Notification::assertNothingSent();
    }

    public function test_send_rejects_ineligible_animal_types(): void
    {
        Notification::fake();

        $this->post(route('calculators.ai-timing.send'), [
            'visit1_at' => '2026-03-02 08:00',
            'animal_type' => 'bull',
            'email' => 'rancher@example.com',
        ])->assertStatus(422);

        Notification::assertNothingSent();
    }
}
