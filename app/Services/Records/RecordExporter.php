<?php

declare(strict_types=1);

namespace App\Services\Records;

use App\Models\Cattle;
use App\Models\HealthRecord;
use App\Models\Media;
use App\Models\Team;

/**
 * Builds a client's full herd-records export (spec §229, §10b — "Client records
 * are kept indefinitely. Clients can export their data at any time."). Every
 * query is scoped to the given team, so an export can never leak another
 * client's data. Emits structured arrays that the controller/command render as
 * JSON or CSV.
 */
class RecordExporter
{
    /**
     * @return array<string, mixed>
     */
    public function forTeam(Team $team): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
            'cattle' => $this->cattle($team),
            'health_records' => $this->healthRecords($team),
            'media' => $this->media($team),
        ];
    }

    /**
     * CSV tables keyed by filename stem → [header row, ...data rows].
     *
     * @return array<string, list<array<int, string|int|null>>>
     */
    public function toCsvTables(Team $team): array
    {
        $data = $this->forTeam($team);

        return [
            'cattle' => $this->toTable($data['cattle']),
            'health_records' => $this->toTable($data['health_records']),
            'media' => $this->toTable($data['media']),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cattle(Team $team): array
    {
        return Cattle::query()
            ->where('team_id', $team->id)
            ->orderBy('id')
            ->get()
            ->map(fn (Cattle $cattle) => [
                'id' => $cattle->id,
                'reg_name' => $cattle->reg_name,
                'herd_number' => $cattle->herd_number,
                'dob' => optional($cattle->dob)->toDateString(),
                'breed' => $cattle->breed,
                'animal_type' => $cattle->animal_type,
                'has_calved' => $cattle->has_calved,
                'status' => $cattle->status->value,
                'for_sale' => $cattle->for_sale,
                'notes' => $cattle->notes,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function healthRecords(Team $team): array
    {
        return HealthRecord::query()
            ->where('team_id', $team->id)
            ->orderBy('id')
            ->get()
            ->map(fn (HealthRecord $record) => [
                'id' => $record->id,
                'cattle_id' => $record->cattle_id,
                'visit_id' => $record->visit_id,
                'type' => $record->type,
                'bcs_score' => $record->bcs_score,
                'payload' => json_encode($record->payload),
                'recorded_at' => optional($record->recorded_at)->toIso8601String(),
                'added_role' => $record->added_role,
                'edited_role' => $record->edited_role,
                'edited_at' => optional($record->edited_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function media(Team $team): array
    {
        return Media::query()
            ->where('mediable_type', Cattle::class)
            ->whereIn('mediable_id', Cattle::query()->where('team_id', $team->id)->select('id'))
            ->orderBy('id')
            ->get()
            ->map(fn (Media $item) => [
                'id' => $item->id,
                'cattle_id' => $item->mediable_id,
                'visit_id' => $item->visit_id,
                'path' => $item->path,
                'caption' => $item->caption,
                'taken_at' => optional($item->taken_at)->toIso8601String(),
                'uploaded_role' => $item->uploaded_role,
            ])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<int, mixed>>
     */
    private function toTable(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $headers = array_keys($rows[0]);

        return array_merge(
            [$headers],
            array_map(static fn (array $row) => array_values($row), $rows),
        );
    }
}
