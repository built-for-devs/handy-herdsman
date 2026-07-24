<?php

declare(strict_types=1);

namespace App\Support\Calculators;

use App\Services\Gestation\GestationEstimate;
use Carbon\CarbonImmutable;

/**
 * Presentation view of a {@see GestationEstimate} for the public Due Date
 * Calculator (spec §5.4b, issue 2.6). ALWAYS presents a range/estimate, never
 * a hard date, and carries the bull-calf caveat as copy (not math). Contains
 * no gestation arithmetic — it only formats what GestationService computed.
 */
final readonly class DueDateResult
{
    private const DATE_FORMAT = 'M j, Y';

    /** Human labels for the milestone keys in config/gestation.php. */
    private const MILESTONE_LABELS = [
        'dry_off' => 'Begin dry-off window',
        'minus_4wk' => '4 weeks out',
        'minus_2wk' => '2 weeks out',
        'minus_5d' => '5 days out',
        'minus_2d' => '2 days out',
        'minus_1d' => '1 day out',
    ];

    /**
     * @param  list<array{key: string, label: string, date: string}>  $milestones
     */
    public function __construct(
        public int $gestationDays,
        public string $estimatedDueDate,
        public string $windowStart,
        public string $windowEnd,
        public string $rangeLabel,
        public string $caveat,
        public array $milestones,
    ) {}

    public static function fromEstimate(GestationEstimate $estimate): self
    {
        return new self(
            gestationDays: $estimate->gestationDays,
            estimatedDueDate: $estimate->estimatedDueDate->format(self::DATE_FORMAT),
            windowStart: $estimate->windowStart->format(self::DATE_FORMAT),
            windowEnd: $estimate->windowEnd->format(self::DATE_FORMAT),
            rangeLabel: 'Expected around '.$estimate->estimatedDueDate->format(self::DATE_FORMAT)
                .' (between '.$estimate->windowStart->format('M j')
                .' and '.$estimate->windowEnd->format('M j').')',
            caveat: $estimate->caveat(),
            milestones: self::milestones($estimate->milestones),
        );
    }

    /**
     * @param  array<string, CarbonImmutable>  $milestones
     * @return list<array{key: string, label: string, date: string}>
     */
    private static function milestones(array $milestones): array
    {
        $mapped = [];

        foreach ($milestones as $key => $date) {
            $mapped[] = [
                'key' => $key,
                'label' => self::MILESTONE_LABELS[$key] ?? $key,
                'date' => $date->format(self::DATE_FORMAT),
            ];
        }

        return $mapped;
    }

    /**
     * @return array{gestation_days: int, estimated_due_date: string, window_start: string, window_end: string, range_label: string, caveat: string, milestones: list<array{key: string, label: string, date: string}>}
     */
    public function toArray(): array
    {
        return [
            'gestation_days' => $this->gestationDays,
            'estimated_due_date' => $this->estimatedDueDate,
            'window_start' => $this->windowStart,
            'window_end' => $this->windowEnd,
            'range_label' => $this->rangeLabel,
            'caveat' => $this->caveat,
            'milestones' => $this->milestones,
        ];
    }
}
