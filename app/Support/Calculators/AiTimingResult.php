<?php

declare(strict_types=1);

namespace App\Support\Calculators;

use App\Enums\AnimalType;
use App\Services\Protocol\ProtocolSchedule;
use Carbon\CarbonImmutable;

/**
 * Presentation view of a {@see ProtocolSchedule} for the public AI Timing
 * Calculator (spec §5.4, issue 2.5). Turns the tested M1 timing math into the
 * plain-English "what each visit is and when" the visitor sees, and into the
 * emailable/textable informal proposal. Contains NO timing arithmetic — it
 * only formats what ProtocolTimingService already computed.
 */
final readonly class AiTimingResult
{
    private const DATE_FORMAT = 'l, M j, Y';

    private const TIME_FORMAT = 'g:i A';

    /**
     * @param  list<array{key: string, title: string, when: string, window: string|null, explanation: string}>  $visits
     */
    public function __construct(
        public string $animalType,
        public string $timezone,
        public int $visitCount,
        public string $recommendedAt,
        public array $visits,
    ) {}

    public static function fromSchedule(ProtocolSchedule $schedule, AnimalType $animalType, ?string $timezone = null): self
    {
        $tz = $timezone ?? (string) config('protocol.timezone');
        $display = $schedule->displayTimezone($tz);

        $visits = [
            [
                'key' => 'visit_1',
                'title' => 'Visit 1 — CIDR in & sync started',
                'when' => self::moment($display['visit1_at']),
                'window' => null,
                'explanation' => 'We insert the CIDR (the progesterone device) and give the'
                    .' first shot. This is the day the 10-day sync protocol begins.',
            ],
            [
                'key' => 'visit_2',
                'title' => 'Visit 2 — CIDR out (exactly 7 days later)',
                'when' => self::moment($display['visit2_at']),
                'window' => null,
                'explanation' => 'Exactly 7 days after Visit 1 we pull the CIDR and give the'
                    .' prostaglandin shot, which brings the animal into a tightly'
                    .' synchronized heat.',
            ],
            [
                'key' => 'visit_3',
                'title' => 'Visit 3 — timed AI (breeding)',
                'when' => self::moment($display['visit3_recommended_at']),
                'window' => self::window($display['visit3_window_start'], $display['visit3_window_end']),
                'explanation' => 'This is the breeding itself. The recommended time is the'
                    .' midpoint of the window; anywhere inside the window is'
                    .' acceptable. Hitting this window is what makes the AI succeed.',
            ],
        ];

        return new self(
            animalType: $animalType->label(),
            timezone: $tz,
            visitCount: count($visits),
            recommendedAt: self::moment($display['visit3_recommended_at']),
            visits: $visits,
        );
    }

    private static function moment(CarbonImmutable $moment): string
    {
        return $moment->format(self::DATE_FORMAT.' \a\t '.self::TIME_FORMAT);
    }

    private static function window(CarbonImmutable $start, CarbonImmutable $end): string
    {
        return $start->format(self::DATE_FORMAT.', '.self::TIME_FORMAT)
            .' – '.$end->format(self::TIME_FORMAT);
    }

    /**
     * @return array{animal_type: string, timezone: string, visit_count: int, recommended_at: string, visits: list<array{key: string, title: string, when: string, window: string|null, explanation: string}>}
     */
    public function toArray(): array
    {
        return [
            'animal_type' => $this->animalType,
            'timezone' => $this->timezone,
            'visit_count' => $this->visitCount,
            'recommended_at' => $this->recommendedAt,
            'visits' => $this->visits,
        ];
    }

    /**
     * A compact one-message summary for SMS (spec §5.4 — textable proposal).
     */
    public function smsSummary(): string
    {
        $lines = ["Your Handy Herdsman AI timing ({$this->animalType}):"];

        foreach ($this->visits as $visit) {
            $lines[] = "- {$visit['title']}: {$visit['when']}";
        }

        $lines[] = 'All times '.$this->timezone.'. Reply to book with Jeff.';

        return implode("\n", $lines);
    }
}
